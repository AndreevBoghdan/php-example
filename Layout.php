<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Layout extends Model
{
	const PREVIEWS_FOLDER = '/slideScreenshots/layoutPreviews/';
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'slidetemplate_id', 'content', 'orientation', 'screenshot_path'
    ];

    /**
     * A layout is owned by many companies
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function companies() {
        return $this->belongsToMany('App\Company');
    }

        /**
     * Create slide.
     *
     * @param  int  $slide_id  ID of slide
     * @return JSON
     */
    public static function saveSlideAsLayout($slide_id){
        $output = new \stdClass();
        $slide = Slide::find($slide_id);
        //return $slide->getOrientation($slide_id);
        if (!($slide)) {
            $output->error = "Slide with passed ID not found.";
        } else {
        	try {
                // Create new layout

                $layout = Layout::create([
                    'slidetemplate_id' => $slide->slidetemplate_id,
                    'content' => $slide->content,
                    //'resolution'=> $slide->resolution,
                    'orientation'=> $slide->getOrientation($slide_id),
                    //'screenshot_path' => $screenshot_path
                ]);

                if ($layout) {
                    $layout_screenshot_path = self::PREVIEWS_FOLDER . 'preview' . $layout->id . '.jpeg';
                 
                    if (!copy(public_path() . $slide->screenshot_path, public_path() . $layout_screenshot_path)) {
                        $output->error = 'Some errors occured while copy screenshot preview';
                    }

                    $layout->screenshot_path = $layout_screenshot_path;
                    $layout->save();

                    $output->result = 1;
                    $output->id = $layout->id;
                } else {
                        $output->error = 'Some errors occured.';
                }
            } catch (\Illuminate\Database\QueryException $e) {
                $output->error = $e->getMessage();
            }

        }
    	return (array)$output;
    }
    /**
     * get slidetemplates method with additional data(is favorite and last_used)
     *
     * @param  int  $company - object
     * @param  int  $orientation - slidetemplate orientation ("portrait" or "landscape")
     * @param  int  $userView - type of view ("all" or "lastUsed" or "favorites")
     * @return slidetemplates array
     */
	public function layoutGetData($user_id, $company, $orientation, $userView, $responseType = 'json'){
		

		if($user_id > 0){
		    $data = array(); // result data

			// get user data
			$user = User::find($user_id);
			if(!empty($user)){
    		    $layouts = $company->getLayoutsByOrientation($orientation);
	
				$data['id'] = $user->id;
				$data['name'] = $user->name;

				// get last used slidetemplates
				$lastUsed = new LayoutUserLastUsed();
				$userLastUsedIDs = $lastUsed->getUserLastUsedLayouts($user_id);

				// get favorits slidetemplates
				$favorites = new LayoutUserFavorites();
				$userFavoriteIDs = $favorites->layoutGetUserFavorites($user_id);
				
				if($userView == 'lastUsed'){  
					// put last used at the beginning
					foreach($userLastUsedIDs as $layout_id => $last_used){
						foreach($layouts as $n => $st){
							if($st->id == $layout_id){
								$st->last_used = $last_used;
								$data['layouts'][] = $st;
								unset($layouts[$n]);
							}
						}
					}
					if(sizeof($layouts) > 0){
						foreach($layouts as $n => $st){
							$data['layouts'][] = $st;
						}
					}
				}
		
				if($userView == 'favorites'){  
					// put favorits at the beginning
					foreach($userFavoriteIDs as $slidetemplate_id => $i){
						foreach($layouts as $n => $st){
							if($st->id == $layout_id){
								$data['layouts'][] = $st;
								unset($layouts[$n]);
							}
						}
					}
					if(sizeof($layouts) > 0){
						foreach($layouts as $n => $st){
							$data['layouts'][] = $st;
						}
					}
				}

				if($userView != 'favorites' && $userView != 'lastUsed'){
					$data['layouts'] = $layouts;
				}
				
				// set favorite and last used options 
				foreach($data['layouts'] as $n => $st){
					if(isset($userLastUsedIDs[$st->id])){
						$st->last_used = $userLastUsedIDs[$st->id];
					} else {
						$st->last_used = 0;
					}
					if(isset($userFavoriteIDs[$st->id])){
						$st->favorite = 1;
					} else {
						$st->favorite = 0;
					}
					$data['layouts'][$n] = $st;
				}
			} else {
				$data['error'] =  trans("user.#error_no_user#");
			}
		} else {
			$data['error'] =  trans("user.#error_no_user#");
		}
		
		if($responseType == 'json'){
			return App\JsonHelper::objectToJson($data);
		} else {
			return $data;
		}
    }

    /**
     * Get layouts by orientation
     *
     * @param $orientation - "all", "landscape" or "portrait"
     * @return layout list
     */
	public static function getLayoutsByOrientation($user_id, $orientation){

		$user = User::find($user_id);

		if ( $user->admin == 1 ) {

            switch ($orientation) {
            	case 'landscape':
		    	    $list = Layout::where('orientation', 0)->get();
            		break;
            	case 'portrait':
		    	    $list = Layout::where('orientation', 1)->get();
            	    break;
            	default:
		    	    $list = Layout::all();
            		break;
            }
		} else {
			$company = Company::find($user->company_id);
            switch ($orientation) {
            	case 'landscape':
		    	    $list =$company->layouts()->where('orientation', 0)->get();
            		break;
            	case 'portrait':
		    	    $list = $company->layouts()->where('orientation', 1)->get();
            	    break;
            	default:
		    	    $list = $company->layouts()->get();
            		break;
            }
		}

		return $list;
	}

}
