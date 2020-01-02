<?php

namespace App\Http\Controllers;

use App\MediaFolder;
use App\SharedMediaFolder;
use App\Company;
use App\Slide;
use Illuminate\Http\Request;
use Auth;
use ReCaptcha\RequestMethod\Post;
use Spatie\MediaLibrary\Media;
use Spatie\MediaLibrary\Filesystem;
use App\Http\Requests;
use Validator;
use GlideImage;

class MediaController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'name' => 'required|max:255'
        ];
    }

    /**
     * Show the application dashboard.
     *
     * @return \Illuminate\Http\Response
     */
    public function index($folder=null)
    {
        $data    = array();
        $company = null;
        $shared = false;
        $companies = array();
        if ( Auth::user()->isAdmin() ) {
            $company_id = session('selected_company_id');
            $company    = Company::find($company_id);
        }
        else {
            $company = Auth::user()->company;
        }
        if ( $company ) {
            foreach($company->mediaFolder()->orderBy('name', 'asc')->get() as $fl){
                $data[] = $fl->toArray();
            }
            //return view('media/list', ['mediaFolders' => $data, 'folder' => $folder]);
        }
        else {
            $shared = true;
            $sharedFolders = SharedMediaFolder::orderBy('name', 'asc')->get();
            if ( $sharedFolders->count() > 0 ) {
                foreach ( $sharedFolders as $item ) {
                    $medias = $item->getMedia($item->collection());
                    if ( $medias->count() > 0 ) {
                        foreach ( $medias as $media ) {
                            $slide = Slide::where('content', 'like', '%/upload/'.$media->id.'/%')->first();
                            if ( !empty($slide) ) {
                                $item->is_used = true;
                                break;
                            }
                        }
                    }
                    $data[] = $item->toArray();
                }

                if ( !\Session::has('folder_id') ) {
                    \Session::put('folder_id', $data[0]["id"]);
                }
            }

            $companyModels = Company::all();
            if ( $companyModels->count() > 0 ) {
                $i = 0;
                foreach ( $companyModels as $company ) {
                    $companies[$i]['id'] = $company->id;
                    $companies[$i]['name'] = $company->name;
                    $companies[$i]['nick'] = $company->nick;
                    $companySharedFolders = $company->shared_folders()->get();
                    $companies[$i]['shared'] = array();
                    if ( $companySharedFolders->count() > 0 ) {
                        foreach ( $companySharedFolders as $item ) {
                            $companies[$i]['shared'][] = $item->name;
                        }
                    }
                    $i++;
                }
            }
        }

        return view('media/list', ['mediaFolders' => $data, 'folder' => $folder, 'shared' => $shared, 'companies' => json_encode($companies)]);
        //return redirect("admin_organization")->withErrors(['Select company first, please']);
    }

    public function selectBackground()
    {
        $data = array();
        $company = null;
        if(Auth::user()->isAdmin()){
            $company_id = session('selected_company_id');
            $company    = Company::find($company_id);
        }
        else{
            $company = Auth::user()->company;
        }
        if ( $company ) {
            $folders = $company->mediaFolder()->orderBy('name', 'asc')->get();
            foreach($folders as $folder){
                $data[] = $folder->toArray();
            }

            $sharedFolders = $company->shared_folders()->orderBy('name', 'asc')->get();
            $sharedMediaFolders = array();
            if ( $sharedFolders->count() > 0 ) {
                foreach ( $sharedFolders as $sFolder ) {
                    $sharedMediaFolders[] = $sFolder->toArray();
                }
            }

            return view('media/dialog', ['mediaFolders' => $data, 'sharedMediaFolders' => $sharedMediaFolders]);
        }
    }

    /**
     * Show the form for creating a new media.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        return view('media/create');

    }

    /**
     * Remove the specified media from database.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function delete($id)
    {
        $media = Media::find($id);
        //$folder = str_replace(MediaFolder::PREFIX_COLLECTION, "", $media->collection_name);
        $result = $media->delete(); // All associated files will be deleted as well
        //return redirect()->back()->with(["folder" => $folder]);
        return response()->json($result);
    }

    /**
     * Show the form for editing the specified media.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        $media = Media::find($id);
        $name = $media->file_name;
        //$folder = str_replace(MediaFolder::PREFIX_COLLECTION, "", $media->collection_name);
        //if($folder=='root') $folder = '';
        $folder = '';
        return view('media/edit', ['mediaId' => $id, 'mediaName' => $name, 'folder' => $folder]);
    }

    public function replace(Request $request)
    {
        //validate
        $validator = Validator::make($request->all(),[
            'files.*' =>
                'required|file:1,1000|mimes:png,jpg,gif,jpeg,mp4|max:2097152'  //h265, 2Gb
        ]);

        if ($validator->fails()){
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()->toArray()
            ]);
        }

        /*$company = null;
        if(Auth::user()->isAdmin()){
            $company_id = session('selected_company_id');
            $company    = Company::find($company_id);
        }
        else{
            $company = Auth::user()->company;
        }*/


        /*if ( $company ) {
            $media = $company->getMedia()->keyBy('id')->get($id);
        }*/
        $id = $request->input('id');
        $media = Media::find($id);

        if($media){
            foreach($request->file('files') as $file) {
                if($file->isValid()){
                    $media->name = $file->getClientOriginalName();
                    //sanitize name
                    $media->file_name = str_replace(['#', '/', '\\'], '-', $file->getClientOriginalName());
                    $media->size = filesize($file->getPathname());
                    $media->save();
                    //delete media files
                    $fs = app(Filesystem::class);
                    $fs->removeFiles($media);
                    //add file and update property
                    $fs->add($file->getPathname(), $media, $media->file_name);
                }
                //only 1 file is allowed
                break;
            }
        }
        return response()->json(1);
        //return redirect("admin_organization")->withErrors(['Select company first, please']);
    }

    public function upload(Request $request)
    {
        $data = array();

        // All items from input.
        $allInput = $request->all();
        $allInput['filenames'] = array();
        $niceNames = array();

        // Prepare data for validation.
        foreach($request->file('files') as $file) {
            // Read filename without extension.
            $fileName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
            // Store filenames in array to validate.
            $allInput['filenames'][$fileName] = $fileName;
            // Array to replace urgent output in error messages on nice filenames with extensions.
            $niceNames['filenames.' . $fileName] = $file->getClientOriginalName();
        }

        // Validation messages.
        $validationMessages = [
            'filenames.*.max' => \Lang::get('media.#max_file_name#'),
            'filenames.*.regex' => \Lang::get('media.#file_name_spec_chars#')
        ];

        // Validation rules.
        $validator = Validator::make(
            $allInput,
            [
                'files.*' => 'required|file:1,1000|mimes:png,jpg,gif,jpeg,mp4,m4v|max:2097152', //h265, 2Gb
                'filenames.*' => 'max:128|regex:/^[a-zA-Z0-9_-][a-zA-Z0-9\._-]*$/'
            ],
            $validationMessages
        );

        // Apply nice filenames for error output.
        $validator->setAttributeNames($niceNames);

        // Check, if validation fails.
        if ($validator->fails()){
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()->toArray()
            ]);
        }

        $folder_id = $request->input('folder_id');
        //return response()->json(["test" => $folder_id]);
        $company = null;
        if(Auth::user()->isAdmin()){
            $company_id = session('selected_company_id');
            $company    = Company::find($company_id);
        }
        else {
            $company = Auth::user()->company;
        }

        if ( $company ) {
            $folder = $company->mediaFolder()->find($folder_id);
            $collection = MediaFolder::DEFAULT_COLLECTION;
            if($folder){
                $collection =  $folder->collection();
            }

            foreach($request->file('files') as $file) {
                $med = $company->addMedia($file)->toCollection($collection);
                $data[] = $med->toArray();
            }
            //return response()->json(array($data));
        }
        else {
            $folder = SharedMediaFolder::find($folder_id);
            $collection = "sharedFolder1";
            if ( !empty($folder) ) {
                $collection =  $folder->collection();
            }

            foreach ( $request->file('files') as $file ) {
                $med = $folder->addMedia($file)->toCollection($collection);
                $data[] = $med->toArray();
            }
        }

        return response()->json(array($data));
        //return redirect("admin_organization")->withErrors(['Select company first, please']);
    }

    public function show(Request $request, $id = null)
    {
        $data = array();
        $company = null;
        if ( Auth::user()->isAdmin() ) {
            $company_id = session('selected_company_id');
            $company    = Company::find($company_id);
        }
        else {
            $company = Auth::user()->company;
        }

        if ( empty($id) && $id != "0" ) $id = \Session::get('folder_id');

        if ( $company && !$request->has('shared') ) {
            $folder = $company->mediaFolder()->find($id);
            $collection = MediaFolder::DEFAULT_COLLECTION;
            if ( $folder ) {
                $collection =  $folder->collection();
            }

            $medias = $company->getMedia($collection);

            //return response()->json($data);
        }
        else {
            $folder = SharedMediaFolder::find($id);
            if ( !empty($folder) ) {
                $collection = $folder->collection();
                $medias = $folder->getMedia($collection);
            }
        }

        if ( isset($medias) && $medias->count() > 0 ) {
            foreach ( $medias as $media ) {
                $media->name = $media->file_name;
                $media->path = $media->getUrl();
                $media->type = $media->getTypeAttribute();
                $width  = 100;
                $height = 100;
                if ( $media->getTypeAttribute() == 'video' ) {
                    $media->preview = '/blocks/media/images/video.png';
                    $media->resolution = 'media.#unknown#';
                }
                else {
                    $media->preview = $media->getUrl('thumb');
                    if(file_exists($media->getPath())){
                        list($width, $height, $type, $attr) = getimagesize($media->getPath());
                    }
                    $media->resolution = $width.'x'.$height;
                }
                $media->size = $media->humanReadableSize;

                if ( $media->model_type == 'App\SharedMediaFolder') {
                    $slide = Slide::where('content', 'like', '%/upload/'.$media->id.'/%')->first();
                    if ( !empty($slide) ) {
                        $media->is_used = true;
                    }
                }

                if ( $width == $height ) $media->orientation = '/blocks/media/images/orientation_square.gif';
                else if ($width > $height) $media->orientation = '/blocks/media/images/orientation_landscape.gif';
                else $media->orientation = '/blocks/media/images/orientation_portrait.gif';
                $data[] = $media;
            }
        }
        \Session::put('folder_id', $id);
        return response()->json($data);
        //return redirect("admin_organization")->withErrors(['Select company first, please']);
    }

    public function folderAdd(Request $request)
    {
        $this->validate($request, [
            'name' => 'required|max:255',
        ]);

        $company = null;
        if ( Auth::user()->isAdmin() ) {
            $company_id = session('selected_company_id');
            $company    = Company::find($company_id);
        }
        else {
            $company = Auth::user()->company;
        }

        if ( $company && $request->input('shared') == false ) {
            $folder = $company->mediaFolder()->create(['name' => $request->input("name")]);
            //return response()->json($folder);
        }
        else {
            $folder = SharedMediaFolder::create(['name' => $request->input("name")]);
            //return null;
        }
        return response()->json($folder);
    }

    public function folderEdit(Request $request, $id)
    {
        $this->validate($request, [
            'name' => 'required|max:255',
        ]);

        $company = null;
        if(Auth::user()->isAdmin()){
            $company_id = session('selected_company_id');
            $company    = Company::find($company_id);
        }
        else{
            $company = Auth::user()->company;
        }

        if ( $company ) {
            $folder = $company->mediaFolder()->find($id);
        }
        else {
            $folder = SharedMediaFolder::find($id);
        }

        if ( $folder ) {
            $folder->name = $request->input("name");
            if($folder->save()){
                return response()->json($folder);
            }
        }
        return response()->json(['error', 'Unable to edit folder.']);
    }

    public function folderDelete($id)
    {
        $company = null;
        if(Auth::user()->isAdmin()){
            $company_id = session('selected_company_id');
            $company    = Company::find($company_id);
        }
        else{
            $company = Auth::user()->company;
        }

        $result = false;
        if ( $company ) {
            $folder = $company->mediaFolder()->find($id);
            if ( $folder ) {
                $company->clearMediaCollection($folder->collection()); // All media will be deleted
                $result = $folder->delete();
            }
        }
        else {
            $folder = SharedMediaFolder::find($id);
            if ( $folder ) {
                $folder->clearMediaCollection($folder->collection()); // All media will be deleted
                $result = $folder->delete();
            }
        }

        return response()->json(['delete' => $result]);
    }

    public function snippet($folder_id = null)
    {
        $data    = array();
        $company = null;
        $index = 0;
        if(Auth::user()->isAdmin()){
            $company_id = session('selected_company_id');
            $company    = Company::find($company_id);
        }
        else{
            $company = Auth::user()->company;
        }
        if ($company) {
            $collections = array( MediaFolder::DEFAULT_COLLECTION );
            foreach($company->mediaFolder()->orderBy('name', 'asc')->get() as $key => $folder){
                $collections[] = $folder->collection();
            }

            foreach($collections as $key => $collection){

                $medias = $company->getMedia($collection);

                foreach($medias as $media){
                    $media->name = $media->file_name;
                    $media->path = $media->getUrl();
                    $media->type = $media->getTypeAttribute();
                    $media->folder_id = $index;
                    if($media->getTypeAttribute()=='video'){
                        $media->preview = '/blocks/media/images/video.png';
                    }
                    else {
                        $media->preview = $media->getUrl('thumb');
                    }

                    $data[] = $media->toArray();
                }
                $index++;
            }

            $sharedFolders = $company->shared_folders()->orderBy('name', 'asc')->get();
            if ( $sharedFolders->count() > 0 ) {
                foreach ( $sharedFolders as $sFolder ) {
                    $sMedias = $sFolder->getMedia($sFolder->collection());
                    foreach ( $sMedias as $sMedia ) {
                        $sMedia->name = $sMedia->file_name;
                        $sMedia->path = $sMedia->getUrl();
                        $sMedia->type = $sMedia->getTypeAttribute();
                        $sMedia->folder_id = $index;
                        if($sMedia->getTypeAttribute()=='video'){
                            $sMedia->preview = '/blocks/media/images/video.png';
                        }
                        else {
                            $sMedia->preview = $sMedia->getUrl('thumb');
                        }

                        $data[] = $sMedia->toArray();
                    }
                    $index++;
                }
            }

            return view('media/snippet', ['medias' => $data]);
        }
    }

    public function countMedias()
    {
        $company = null;
        if(Auth::user()->isAdmin()){
            $company_id = session('selected_company_id');
            $company    = Company::find($company_id);
        }
        else{
            $company = Auth::user()->company;
        }
        if($company){
            $medias = $company->getMedia();
            $ct     = count($medias);
            return response()->json($ct);
        }
    }

    public function resize(Request $request, $id)
    {
        $media_type =  $request['media_type'];

        /*$company    = null;

        if(Auth::user()->isAdmin()){
            $company_id = session('selected_company_id');
            $company    = Company::find($company_id);
        }
        else{
            $company = Auth::user()->company;
        }*/
        //if($company){
        $media = Media::find($id);
        if($media){
            if(file_exists($media->getPath())){
                list($width, $height, $type, $attr) = getimagesize($media->getPath());
            }

            if($media_type=='resize_fullHD'){
                if($width > $height){
                    GlideImage::create($media->getPath())
                        ->modify(['w' => 1920, 'h' => 1080, 'fit'=>'max'])
                        ->save($media->getPath());
                }
                else{
                    GlideImage::create($media->getPath())
                        ->modify(['w' => 1080, 'h' => 1920, 'fit'=>'max'])
                        ->save($media->getPath());
                }
            }
            else{ //resize_4k
                if($width > $height){
                    GlideImage::create($media->getPath())
                        ->modify(['w' => 3840, 'h' => 2160, 'fit'=>'max'])
                        ->save($media->getPath());
                }
                else{
                    GlideImage::create($media->getPath())
                        ->modify(['w' => 2160, 'h' => 3840, 'fit'=>'max'])
                        ->save($media->getPath());
                }
            }
            $media->size = filesize($media->getPath());
            $media->save();
        }
        return response()->json(1);
        //}
        //return redirect("admin_organization")->withErrors(['Select company first, please']);
    }

    public function linkSharedFolder(Request $request)
    {
        $folderId = $request->input('shared_folder');
        $companiesIds = $request->input('companies');
        if ( $request->has('link') ) {
            $type = 'link';
        }
        else if ( $request->has('unlink') ){
            $type = 'unlink';
        }
        if ( !empty($folderId) && !empty($companiesIds) ) {
            foreach ($companiesIds as $id){
                $companyModel = Company::find($id);
                if ( $type === 'link' ) {
                    $companyModel->shared_folders()->syncWithoutDetaching([$folderId]);
                }
                else if ( $type === 'unlink' ) {
                    $companyModel->shared_folders()->detach([$folderId]);
                }
            }
        }

        return redirect()->back();
    }
}
