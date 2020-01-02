<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Auth;

use App\Layout;
use App\LayoutUserFavorites;
use App\LayoutUserLastUsed;
use App\Company;

class LayoutController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $layoutModels = Layout::all();
        $layouts = array();
        if ( $layoutModels->count() > 0 ) {
            $i = 0;
            foreach ( $layoutModels as $layout ) {
                $layouts[$i]['id'] = $layout->id;
                $layouts[$i]['screenshot_path'] = $layout->screenshot_path;
                if ($layout->orientation==0){
                    $layouts[$i]['orientation'] = 'album';
                } else {
                    $layouts[$i]['orientation'] = 'portrait';
                };
                $layouts[$i]['companies_number'] = $layout->companies()->count();
                $companiesAssigned = $layout->companies()->get();
                $layouts[$i]['companies'] = array();
                if ( $companiesAssigned->count() > 0 ) {
                        foreach ( $companiesAssigned as $item ) {
                            $layouts[$i]['companies'][] = $item->name;
                       }
                }
                $i++;
            }
        }

        $companyModels = Company::all();
        $companies = array();
        if ( $companyModels->count() > 0 ) {
            $i = 0;
            foreach ( $companyModels as $company ) {
                $companies[$i]['id'] = $company->id;
                $companies[$i]['name'] = $company->name;
                $companies[$i]['nick'] = $company->nick;
                $companyLayouts = $company->layouts()->get();
                $companies[$i]['layouts'] = array();
                if ( $companyLayouts->count() > 0 ) {
                    foreach ( $companyLayouts as $companyLayout ) {
                        $companies[$i]['layouts'][] = $companyLayout->id;
                    }
                }

                $i++;
            }
            //var_dump($companies);die();
        }

        return view('layout/list', ['layouts' => json_encode($layouts), 'companies' => json_encode($companies)]);
    }

 
    /**
     * get layout data
     * 
     * @param int $id - layout_id
     * @return \Illuminate\Http\Response
     */
    public function getLayout($id)
    {
        // set last_used for template
        if(app('request')->input('user_id') !== null){
            $user_id = app('request')->input('user_id');
        } else {
            $user_id = Auth::user()->id;
        }
        
        if($user_id != '' && $id != ''){
            $result = LayoutUserLastUsed::layoutSetLastUsed($user_id, $id);
        }   

        return response()->json(Layout::where('id', $id)->first());
    }

    /* set user favorite layout on/off
     */
    public function setFavoriteLayout($layout_id, $favoriteValue){
        if(app('request')->input('user_id') !== null){
            $user_id = app('request')->input('user_id');
        } else {
            $user_id = Auth::user()->id;
        }
        if($favoriteValue){
            $resultFav  = LayoutUserFavorites::layoutSetFavorite($user_id, $layout_id);
        } else {
            $resultFav  = LayoutUserFavorites::layoutDeleteFavorite($user_id, $layout_id);
        }
        return $resultFav;
    }

    /**
     * Link or unlink layouts to companies
     *
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function link(Request $request)
    {
        $layoutIds = $request->input('layouts');
        $companiesIds = $request->input('companies');
        if ( $request->has('link') ) {
            $type = 'link';
        }
        else if ( $request->has('unlink') ){
            $type = 'unlink';
        }
        if ( !empty($layoutIds) && !empty($companiesIds) ) {
            foreach ($companiesIds as $id){
                $companyModel = Company::find($id);
                if ( $type === 'link' ) {
                    //return (array)$companyModel->layouts()->get();
                    $companyModel->layouts()->syncWithoutDetaching($layoutIds);
                }
                else if ( $type === 'unlink' ) {
                    $companyModel->layouts()->detach($layoutIds);
                }
            }
        }

        return redirect()->back();
    }

    /**
     * Delete a layoyt
     *
     * @param $id
     * @return \Illuminate\Http\RedirectResponse
     */
    public function delete($id)
    {
        $layout = Layout::find($id);
        if ( !empty($layout) ) {
            $file_path = public_path().$layout->screenshot_path;
            $result = $layout->delete();
            if ( $result ) {
                if ( File::exists($file_path) ) {
                    File::delete($file_path);
                }
            }
        }

        return redirect()->back();
    }

    /**
     * Delete a few layouts
     *
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function deleteList(Request $request)
    {
        $ids = $request->input('id');
        $layouts = Layout::find($ids);
        if ( !empty($layouts) ) {
            foreach ( $layouts as $layout ) {
                $file = public_path().$layout->screenshot_path;
                $result = $layout->delete();
                if ( $result ) {
                    if ( File::exists($file) ) {
                        File::delete($file);
                    }
                }
            }
        }

        return redirect()->back();
    }

}
