<?php

namespace App\Http\Controllers;

use App\Slide;
use App\Playlist;
use App\Comment;
use App\Company;
use App\Layout;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Mail;
use Validator;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Input;

class SlideController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth', ['except' => ['get_weather']]);
    }

    /**
     * Show the application dashboard.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        return view('slide/list');
    }

    /**
     * Request to create a new slide.
     *
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function create(Request $request)
    {
        $passedData = $request->all();

        // Relation between slide and playlist.
        $relations = new \stdClass();
        if ($passedData['playlist'] == 'new'){
            $playlistInstance = new Playlist();
            $data = new \stdClass();
            $data->name = $passedData['playlistName'];
            $playlist = $playlistInstance->playlistCreate($passedData['company'], $data);
            $relations->playlist_id = $playlist->id;
        } else {
            $relations->playlist_id = $passedData['playlist'];
        }
        $relations->slide_duration = $passedData['duration'];
        $relations->slide_position = $passedData['position'];

        // Slide's content and name.
        $slideData = new \stdClass();
        $slideData->content = $passedData['content'];
        $slideData->name = $passedData['name'];
        $slideData->resolution = $passedData['resolution'];

        $slideInstance = new Slide();
        $slideData->user_last_changed_id = Auth::user()->id;
        $slide = (array)$slideInstance->slideCreate($passedData['template'], $relations, $slideData);
        $res = (string)$relations->playlist_id;
        if ($passedData['playlist'] == 'new'){
            $slide['playlist'] = $relations->playlist_id;
        }

        // CREATE screenshot slide icon file .jpeg
        if ( $request->has('base64_image') )
        {
            $slideData->screenshot_path = $this->saveScreenshot($request->base64_image, $slide['id']);
        }

        return json_encode($slide);
        
    }


    /**
     * Request to delete slide.
     *
     * @param integer $id  Slide's id
     * @return \Illuminate\Http\Response
     */
    public function delete($id)
    {
        $slideInstance = new Slide();
        $result = $slideInstance->slideDelete($id);
        if (isset($result->error)) {
            return $result->error;
        } else {

            // DELETE screenshot slide icon file .jpeg
            $file = public_path() . Slide::SCREENSHOTS_FOLDER . "/screenshot" . $id . ".jpeg";
            if ( File::exists($file) ) {
                File::delete($file);
            }

            return "Slide was deleted";
        }
    }

    /**
     * Request to get slide's content.
     *
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function getContent(Request $request)
    {
        $passedData = $request->all();

        // Get html of slide with passed ID.
        $slideInstance = new Slide();
        return json_encode((array)$slideInstance->slideGetHtml($passedData['id']));
    }

    /**
     * Request to update slide's content.
     *
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function updateContent(Request $request)
    {
        $passedData = $request->all();

        // Set only content, because name is without changes.
        $slideData = new \stdClass();
        $slideData->content = $passedData['content'];
        $slideData->user_last_changed_id = Auth::user()->id;
        $slideData->published = 0;

        // CREATE (update) screenshot slide icon file .jpeg
        if ( $request->has('base64_image') )
        {
            $slideData->screenshot_path = $this->saveScreenshot($request->base64_image, $passedData['id']);
        }

        // Send request to update slide's content.
        $slideInstance = new Slide();
        return json_encode((array)$slideInstance->slideUpdate($passedData['id'], $passedData['template'], NULL, $slideData));
    }

    /** Request to publish slide
     *
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function publish($id){
        $slideInstance = new Slide();
        return json_encode((array)$slideInstance->publish($id));
    
    }

    /** Save screenshot in folder
     * 
     * @return string image path
    **/
    
    public function saveScreenshot($base64_image, $slideId)
    {
        $folder = public_path() . Slide::SCREENSHOTS_FOLDER;
        if (!is_dir($folder)) {
            mkdir($folder, 0755, true);
        }
        $fileName = 'screenshot' . $slideId . '.jpeg';

        $imgData = base64_decode($base64_image);
        $image = imagecreatefromstring($imgData);
        
        $originalWidth = imagesx($image);
        $originalHeight = imagesy($image);

        if( $originalWidth > $originalHeight )
        {
            $thumbWidth = 188;
            $thumbHeight = 110;
        } 
        else 
        {
            $thumbWidth = 84;
            $thumbHeight = 140;
        }
            
        $tmp = imagecreatetruecolor($thumbWidth, $thumbHeight);

        imagecopyresampled($tmp, $image, 0, 0, 0, 0, $thumbWidth, $thumbHeight, $originalWidth, $originalHeight);
        imagejpeg($tmp, $folder . $fileName);

        return Slide::SCREENSHOTS_FOLDER . $fileName;
    }

    public function saveAsLayout($slideId){
        return Layout::saveSlideAsLayout($slideId);
    }

    /**
     * Request to unpublish slide
     *
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function unpublish($id){
        $slideInstance = new Slide();
        return json_encode((array)$slideInstance->unpublish($id));
    }

    /**
     * Request to approve slide's content.
     *
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function requestApprove(Request $request)
    {
        $passedData = $request->all();
        //$slide = Slide::find($passedData['id']);
        $playlist = Playlist::find($passedData['playlist']);
        $data = new \stdClass();
        $data->status = 2;
        $data->email_sent = $date = date('Y-m-d H:i:s');
        $slideInstance = new Slide();
        $slide = $slideInstance->slideUpdate($passedData['id'], NULL, NULL, $data);
        
        $cas_email = $playlist->cas_user;

        if (!$cas_email) {
            $cas_email = Company::companyGetCasUser($company_id);
        }

        $data = array();
   
        Mail::send(['text'=>'slide.mail'], $data, function($message) use ($cas_email){
            $message->to($cas_email, 'Tutorials Point') ->subject
               ('Accenta content approval system notification') ;
        });


        return json_encode($slide);
    }

    /**
     * Page for slides waiting for approve or denied
     *
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function approve(Request $request)
    {
        $logout_only = True;
        $company_id = Auth::user()->company_id;
        \Session::put('selected_company_id', $company_id);
        return view('slide/approve', ['logout_only' => $logout_only]);
    }

    /**
     * Page for slide approve\deny
     *
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function approvePage($id, Request $request)
    {
        $logout_only = True;
        $slideInstance = new Slide();
        $slide = $slideInstance->slideGetHtml($id);
        $slide_status = $slideInstance->slideGetStatus($id)['status'];
        //return $slide;
        return view('slide/approve_page', ['slide' => $slide, 'slide_status' => $slide_status, 'logout_only' => $logout_only]);
    }

    /**
     * Request to approve slide's content.
     *
     * @param Request $request, int slide id
     * @return json
     */
    public function approveSlide($id, Request $request)
    {

        $slideInstance = new Slide();
        $data = new \stdClass();
        $data->status = 1;
        $slide = $slideInstance->slideUpdate($id, NULL, NULL, $data);
        
        $company_id = Auth::user()->company_id;
        

        $email_address = Slide::getLastEditorEmail($id);

        $info = [];
        $info['id'] = $id;
        $info['new_status'] = 'approved';


        Mail::send(['text'=>'slide.mail_slide_approved'], $info, function($message) use ($email_address){
            $message->to($email_address, 'Tutorials Point') ->subject
               ('One more slide was approved') ;
        });


        return json_encode($slide);
    }


    /**
     * Request to deny slide's content.
     *
     * @param Request $request, int slide id
     * @return json
     */
    public function denySlide($id, Request $request)
    {

        $passedData = app('request')->all();

        $slideInstance = new Slide();
        $commentInstance = new Comment();

        $dataCommentValidation = json_decode(json_encode($passedData), true);
        $dataCommentValidation['user_id'] = Auth::user()->id;
        $slide_data = $slideInstance->slideGetData($id);
        $dataCommentValidation['playlist_id'] = $slide_data[0]['playlist_relation'][0]['playlist_id'];

        //return var_dump(empty($dataCommentValidation['text']));

        Validator::make($dataCommentValidation, $commentInstance->denyValidationRules())->validate();

        $new_comment = $commentInstance->commentCreate($dataCommentValidation);


        $data = new \stdClass();
        $data->status = 3;
        $slide = $slideInstance->slideUpdate($id, NULL, NULL, $data);
        
        $company_id = Auth::user()->company_id;
        

        $email_address = Slide::getLastEditorEmail($id);

        $info = [];
        $info['id'] = $id;
        $info['new_status'] = 'denied';


        Mail::send(['text'=>'slide.mail_slide_approved'], $info, function($message) use ($email_address){
            $message->to($email_address, 'Tutorials Point') ->subject
               ('One more slide was denied') ;
        });


        return json_encode($slide);
    }

   /**
     * Request to deny slide's content.
     *
     * @param Request $request, int slide id
     * @return json
     */
    public function commentSlide($id, Request $request)
    {

        $passedData = app('request')->all();
        //return json_encode($passedData);

        $slideInstance = new Slide();
        $commentInstance = new Comment();

        $dataCommentValidation = json_decode(json_encode($passedData), true);
        $dataCommentValidation['user_id'] = Auth::user()->id;
        $slide_data = $slideInstance->slideGetData($id);
        $dataCommentValidation['playlist_id'] = $slide_data[0]['playlist_relation'][0]['playlist_id'];

        //return var_dump(empty($dataCommentValidation['text']));

        Validator::make($dataCommentValidation, $commentInstance->denyValidationRules())->validate();

        $new_comment = $commentInstance->commentCreate($dataCommentValidation);

        $cas_email = '';
        
        $data = array();
        $text='';
        if(Auth::user()->role == 1){
            $cas_email = Slide::getLastEditorEmail($id);
            $data['playlist'] = $dataCommentValidation['playlist_id'];
            $data['slide_id'] = $id;
            Mail::send(['text'=>'slide.mail_comment'], $data, function($message) use ($cas_email){
                $message->to($cas_email, 'Tutorials Point') ->subject
                   ('Accenta content approval system notification') ;
            });
        

        } else {
            $playlist = Playlist::find($dataCommentValidation['playlist_id']);
            $cas_email = $playlist->cas_user;
            if (!$cas_email) {
                $cas_email = Company::companyGetCasUser($company_id);
                
            }
            Mail::send(['text'=>'slide.mail_comment_playlist'], $data, function($message) use ($cas_email){
                $message->to($cas_email, 'Tutorials Point') ->subject
                   ('Accenta content approval system notification') ;
            });

        }
        



        return json_encode($new_comment );
    }


    /**
     * Request to get slides for approve
     *
     * @param Request $request
     * @return array
     */
    public function slidesForApprove(Request $request)
    {
        $result = [];
        $user = Auth::user();
        $company_id = $user->company_id;
        $companyInstance = new Company();
        $slideInstance = new Slide();
        $playlists = $companyInstance->companyGetPlaylistList($company_id);
        foreach ($playlists as $playlist => $value) {
            $playlist_item = Playlist::find($playlist);
            //return $playlist_item;
            if ($playlist_item->cas_use==0) {
                continue;
            }
            $pl_slides = Playlist::playlistGetSlideList($playlist);
            foreach ($pl_slides as $slide => $val) {
                $slide_item = Slide::find($val->slide_id);
                //return $slide_item;
                if (($slide_item->cas_status==2) || ($slide_item->cas_status==3)){
                    // set result
                    $val->name = $slide_item->name;
                    $val->created_at = $slide_item->created_at;
                    $val->updated_at = $slide_item->updated_at;
                    $val->playlist = $value->name;
                    $val->cas_status = $slide_item->cas_status;
                    $slide_data = $slideInstance->slideGetData($val->slide_id);
                    $val->last_editor = array_pop($slide_data)['last_editor'];
                    $comment = Comment::getLastCommentBySlideId($val->slide_id);
                    if ($comment){
                        //return $comment->text;
                        $val->comment = $comment->text; 
                    } else {
                        $val->comment = '';
                    }
                    //return (array)$comment;
                    
                    $result[] = $val;
                }
            }
//            return $val->slide_id;
        }

        return $result;
    }


    // set all slides schedules 1 or 0

    public function editSchedules(Request $request)
    {
        //return $request->all();
        //$scheduleInstance  = new Schedule();
        $slide_id           = $request['slide_id'];
        //$playlist_id        = $request['playlist_id'];
        $slide = Slide::find($slide_id);
        $schedules = $slide->schedules();
        foreach ($schedules as $key => $schedule) {
            $schedule['priority'] = $request['priority'];
            $schedule->save();
        }
        return response()->json($schedules);
    }


	/**
     * get weather from yahoo service
     * The response is JSONP!: 
     * callback(json_result);  
     */
	public function get_weather($city, $country, $zip, $forecastDays){
		
		/* yahoo credentials */
		$url = 'https://weather-ydn-yql.media.yahoo.com/forecastrss';
		$app_id = 'eWHy855c';
		$consumer_key = 'dj0yJmk9U3duUFZsbXBXbVdTJnM9Y29uc3VtZXJzZWNyZXQmc3Y9MCZ4PWZh';
		$consumer_secret = '4b63f8afedaa82d8d545edb04a2f12786a419652';

		$query = array(
			'location' => $city.','.$country.','.$zip,
			'format' => 'json',
			'u' => 'c'
		);

		$oauth = array(
			'oauth_consumer_key' => $consumer_key,
			'oauth_nonce' => uniqid(mt_rand(1, 1000)),
			'oauth_signature_method' => 'HMAC-SHA1',
			'oauth_timestamp' => time(),
			'oauth_version' => '1.0'
		);
	
		$base_info = $this->buildBaseString($url, 'GET', array_merge($query, $oauth));
		$composite_key = rawurlencode($consumer_secret) . '&';
		$oauth_signature = base64_encode(hash_hmac('sha1', $base_info, $composite_key, true));
		$oauth['oauth_signature'] = $oauth_signature;

		$header = array(
			$this->buildAuthorizationHeader($oauth),
			'Yahoo-App-Id: ' . $app_id
		);

		$options = array(
			CURLOPT_HTTPHEADER => $header,
			CURLOPT_HEADER => false,
			CURLOPT_URL => $url . '?' . http_build_query($query),
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_SSL_VERIFYPEER => false
		);
	
		$ch = curl_init();
		curl_setopt_array($ch, $options);
		$response = curl_exec($ch);
		curl_close($ch);

		//print_r($response);
		$return_data = json_decode($response, true);
	
		//print_r($return_data);

		$counter = 0;
		$result = array();
		$result['query'] = array(
					'count' => 0, 
					'results' => array(
								'channel' => array()
								)
		);
	
		if(isset($return_data['forecasts']) && is_array($return_data['forecasts'])){
			foreach($return_data['forecasts'] as $num => $item){
				if($counter < $forecastDays){
					
					$itemDateUnix = strtotime(gmdate("Y-m-d", $item['date']));
					$curDateUnix = strtotime(date('Y-m-d'));
					
					if($itemDateUnix >= $curDateUnix){
						$result['query']['results']['channel'][] = array('item' => array(
							'forecast' => array(
								'date' => gmdate("d.m.Y", $item['date']),
								'code' => $item['code'],
								'high' => $item['high'],
								'low' => $item['low']
							)
						));
						$counter++;
					}
				}
			}
		}
		$result['query']['count'] = $counter;
        
		return Input::get('callback')."('".json_encode($result)."');";
	}
	
	private function buildBaseString($baseURI, $method, $params) {
		$r = array();
		ksort($params);
		foreach($params as $key => $value) {
			$r[] = "$key=" . rawurlencode($value);
		}
		return $method . "&" . rawurlencode($baseURI) . '&' . rawurlencode(implode('&', $r));
	}

	private function buildAuthorizationHeader($oauth) {
		$r = 'Authorization: OAuth ';
		$values = array();
		foreach($oauth as $key=>$value) {
			$values[] = "$key=\"" . rawurlencode($value) . "\"";
		}
		$r .= implode(', ', $values);
		return $r;
	}
}
