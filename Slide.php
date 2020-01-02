<?php

namespace App;

use Faker\Provider\DateTime;
use Illuminate\Database\Eloquent\Model;

class Slide extends Model
{
    const SCREENSHOTS_FOLDER = '/slideScreenshots/';
    const DEFAULT_SCREENSHOT_FILE = '/defaultSreenshot/default.jpeg';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name', 'slidetemplate_id', 'content', 'cas_status', 'email_sent', 'status_changed_at', 'resolution', 'user_last_changed_id', 'screenshot_path', 'color', 'published'
    ];

    /**
     * @return array
     */
    public static function getStatus() {
        $roles = [
            '0' => trans("slide.#not_applicable#"),
            '1' => trans("slide.#approved#"),
            '2' => trans("slide.#waiting_for_approval#"),
            '3' => trans("slide.#denied#"),
        ];
        return $roles;
    }

    /**
     * Create slide.
     *
     * @param  int  $slidetemplate_id  ID of related slidetemplate
     * @param  JSON  $playlistRelation  { "playlist_id": int, "slide_position": int, "slide_duration": int }
     * @param  JSON  $data  Slide fields values
     * @return JSON
     */
    public function slideCreate($slidetemplate_id, $playlistRelation, $data) {
        $output = new \stdClass();
        
        // Find, if passed slidetemplate exists.
        $slidetemplate = Slidetemplate::find($slidetemplate_id);

        // Find, if passed playlist exists.
        $playlist = Playlist::find($playlistRelation->playlist_id);

        $playlist->name;

        $cas_status_slide = 0;

        if (!($slidetemplate)) {
            $output->error = "Slidetemplate with passed ID not found.";
        } elseif (!($playlist)) {
            $output->error = "Playlist with passed ID not found.";
        } else {
            try {
                // Create new slide.
                $slide = Slide::create([
                    'name' => $data->name,
                    'content' => isset($data->content) ? $data->content : NULL,
                    'resolution'=> isset($data->resolution) ? $data->resolution : "1920x1080",
                    'color' => isset($data->color) ? $data->color : 'cccccc',
                    'slidetemplate_id' => $slidetemplate_id,
                    'cas_status' => $cas_status_slide,
                    'published' => isset($data->published) ? $data->published : 0,
                    'user_last_changed_id' =>  isset($data->user_last_changed_id) ? $data->user_last_changed_id : NULL,
                ]);

                if ($slide) {
                    $slide->screenshot_path = Slide::generateScreenshot($slide->id);
                    $slide->save();

                    // Sync relation playlist:slide, using $playlistRelation.
                    $slide->playlists()->attach([$playlistRelation->playlist_id => ['slide_position' => $playlistRelation->slide_position, 'slide_duration' => $playlistRelation->slide_duration]]);

                    $output->result = 1;
                    $output->id = $slide->id;
                    $output->created_at = $slide->created_at;
                } else {
                    $output->error = 'Some errors occured.';
                }
            } catch (\Illuminate\Database\QueryException $e) {
                $output->error = $e->getMessage();
            }
        }

        return $output;
    }

    /**
     * Delete slide.
     *
     * @param  integer  $id  Slide's id
     * @return  JSON
     */
    public function slideDelete($id) {
        $output = new \stdClass();

        $slide = Slide::find($id);

        if (!($slide)) {
            $output->error = "Slide not found.";
        } elseif ($slide->delete()) {
            PlaylistSlide::where('slide_id', $id)->delete();
            $output->result = 1;
            $output->id = $id;
        } else {
            $output->error = 'Some errors occurred.';
        }

        return $output;
    }

    /**
     * Get data for one, several or all slides.
     *
     * @param  string|int|array{int}  $id  Slide's id(s) or "all" for all slides.
     * @return array
     */
    public function slideGetData($id) {
        // Read data from database.
        $slides = null;

        if ($id == 'all') {
            $slides = \DB::table('slides')
                ->leftJoin('playlist_slide', 'slides.id', '=', 'playlist_slide.slide_id')->orderBy('slide_id')
                ->leftJoin('playlists', 'playlist_slide.playlist_id', '=', 'playlists.id')
                ->leftJoin('slidetemplates', 'slides.slidetemplate_id', '=', 'slidetemplates.id')
               ->select('slides.id as slide_id', 'slides.name as name', 'slides.slidetemplate_id as slidetemplate_id', 'slides.screenshot_path as screenshot_path', 'slides.resolution as resolution', 'slides.user_last_changed_id as user_id', 'slides.cas_status as cas_status', 'slides.email_sent as email_sent', 'slides.created_at as created_at', 'slides.updated_at as updated_at', 'slides.status_changed_at as status_changed_at', 'playlist_slide.id as relation_id', 'playlist_slide.playlist_id as playlist_id', 'playlist_slide.slide_position as slide_position', 'playlist_slide.slide_duration as slide_duration', 'playlists.name as playlist_name', 'slidetemplates.name as slidetemplate_name', 'slides.content as html', 'slides.color as color', 'slides.published as published')
                ->get();
        } elseif(is_array($id)) {
            $slides = \DB::table('slides')->whereIn('slides.id', $id)
                ->leftJoin('playlist_slide', 'slides.id', '=', 'playlist_slide.slide_id')->orderBy('slide_id')
                ->leftJoin('playlists', 'playlist_slide.playlist_id', '=', 'playlists.id')
                ->leftJoin('slidetemplates', 'slides.slidetemplate_id', '=', 'slidetemplates.id')
                ->select('slides.id as slide_id', 'slides.name as name', 'slides.slidetemplate_id as slidetemplate_id', 'slides.screenshot_path as screenshot_path', 'slides.resolution as resolution', 'slides.user_last_changed_id as user_id', 'slides.cas_status as cas_status', 'slides.email_sent as email_sent','slides.created_at as created_at', 'slides.updated_at as updated_at', 'slides.status_changed_at as status_changed_at', 'playlist_slide.id as relation_id', 'playlist_slide.playlist_id as playlist_id', 'playlist_slide.slide_position as slide_position', 'playlist_slide.slide_duration as slide_duration', 'playlists.name as playlist_name', 'slidetemplates.name as slidetemplate_name', 'slides.content as html', 'slides.color as color', 'slides.published as published')
                ->get();
        } elseif ($id >= 1) {
            $slides = \DB::table('slides')->where('slides.id', $id)
                ->leftJoin('playlist_slide', 'slides.id', '=', 'playlist_slide.slide_id')->orderBy('slide_id')
                ->leftJoin('playlists', 'playlist_slide.playlist_id', '=', 'playlists.id')
                ->leftJoin('slidetemplates', 'slides.slidetemplate_id', '=', 'slidetemplates.id')
                ->select('slides.id as slide_id', 'slides.name as name', 'slides.slidetemplate_id as slidetemplate_id', 'slides.screenshot_path as screenshot_path', 'slides.resolution as resolution', 'slides.user_last_changed_id as user_id', 'slides.cas_status as cas_status', 'slides.email_sent as email_sent', 'slides.created_at as created_at', 'slides.updated_at as updated_at', 'slides.status_changed_at as status_changed_at', 'playlist_slide.id as relation_id', 'playlist_slide.playlist_id as playlist_id', 'playlist_slide.slide_position as slide_position', 'playlist_slide.slide_duration as slide_duration', 'playlists.name as playlist_name', 'slidetemplates.name as slidetemplate_name', 'slides.content as html', 'slides.color as color', 'slides.published as published')
                ->get();
        }

        // Create array, which will store a new output structure. 
        $output = [];

        if (count($slides) > 0) {
            $slideArray = [];
            $id_name = 0;
            foreach ($slides as $key => $slide) {

                $user = User::find($slide->user_id);
                $username = isset($user->name) ? $user->name : NULL;

                if ($id_name != $slide->slide_id) {
                    // If this ID is the first of the same IDs, we have no data about this slide in output. Push last slide to output and fill the new one.
                    if ($key != 0) {
                        // Do not push empty $slideArray.
                        $output[] = $slideArray;
                        $slideArray = [];
                    }

                    $slideArray["id"] = $slide->slide_id;
                    $slideArray["name"] = $slide->name;
                    $slideArray["cas_status"] = $slide->cas_status;
                    $slideArray["email_sent"] = $slide->email_sent;
                    $slideArray["created_at"] = $slide->created_at;
                    $slideArray["updated_at"] = $slide->updated_at;
                    $slideArray["status_changed_at"] = $slide->status_changed_at;
                    $slideArray["resolution"] = $slide->resolution;
                    $slideArray["html"] = $slide->html;
                    $slideArray["color"] = $slide->color;
                    $slideArray["published"] = $slide->published;

                    $slideArray["last_editor"] = $username;
                    //$slideArray["last_editor"] = '2';
                    $slideArray["slidetemplate"] = [];
                    $slideArray["slidetemplate"]["id"] = $slide->slidetemplate_id;
                    $slideArray["slidetemplate"]["name"] = $slide->slidetemplate_name;
                    $slideArray["playlist_relation"] = [];

                    $slideArray["screenshot_path"] = $slide->screenshot_path;
                }

                if (array_key_exists("playlist_relation", $slideArray)) {
                    // These fields should be added everytime, if relation playlist:slide is set in "playlist_slide" table.
                    if ($slide->relation_id) {
                        $playlistRelation = [];
                        $playlistRelation["relation_id"] = $slide->relation_id;
                        $playlistRelation["playlist_id"] = $slide->playlist_id;
                        $playlistRelation["playlist_name"] = $slide->playlist_name;
                        $playlistRelation["slide_position"] = $slide->slide_position;
                        $playlistRelation["slide_duration"] = $slide->slide_duration;

                        $slideArray["playlist_relation"][] = $playlistRelation;
                    }
                }

                $id_name = $slide->slide_id;
            }

            // Push the last slide.
            $output[] = $slideArray;
        } else {
            $output["error"] = "Slides not found.";
        }
        return $output;
    }

    /**
     * Get html content of slide.
     *
     * @param  int  $id  ID of requested slide
     * @return JSON
     */
    public function slideGetHtml($id) {
        // Create array, which will store a new output structure. 
        $output = [];

        // Get slide's data.
        $slide = Slide::find($id);

        if (!($slide)) {
            $output["error"] = trans("slide.#error_no_slide#", ['id' => $id]);
        } else {
            $output["id"] = $slide->id;
            $output["html"] = $slide->content;
            $output["template"] = $slide->slidetemplate_id;
            $output["resolution"] = $slide->resolution;
        }

        return $output;
    }

    /**
     * Get cas status of slide.
     *
     * @param  int  $id  ID of requested slide
     * @return array
     */
    public function slideGetStatus($id) {
        // Create array, which will store a new output structure. 
        $output = [];

        // Get slide's data.
        $slide = Slide::find($id);

        if (!($slide)) {
            $output["error"] = trans("slide.#error_no_slide#", ['id' => $id]);
        } else {
            $output["id"] = $slide->id;
            $output["status"] = $slide->cas_status;
        }

        return $output;
    }

    /**
     * Get cas status of slide.
     *
     * @param  int  $id  ID of requested slide
     * @return 0 or 1
     */
    public function getOrientation($id) {
        // Create array, which will store a new output structure. 

        // Get slide's data.
        $slide = Slide::find($id);
        $resolution = $slide->resolution;
        $resolutionArr = explode('x', $resolution);
        //return $resolutionArr;
        $width = $resolutionArr[0];
        $height = $resolutionArr[1];
        if ($height < $width) {
            return 0; 
        } else {
            return 1;
        }
        return 1;
    }


    /**
     * Returns a portion of slide data used in Screenapp:
     * { id: slide_id,
     *   html: content,
     *   name: slide_nmae
     * }
     * $return mixed
     */
    public function getDataForScreenapp() {
        $data = new \stdClass();
        $data->id = $this->id;
        //remove own domain preffixes to leave relative paths
        $html = $this->content;
        //replace absolute paths with relative paths
        $html = str_ireplace(url("/")."/", "",  $html);
        //make elements uneditable
        $html = str_ireplace("contenteditable=\"true\"", "", $html);
        //path should not start with slash /
        $html = str_replace("'/", "'", $html);
        $html = str_replace('"/', '"', $html);
        $html = str_ireplace('&quot;/', '&quot;', $html);
        $data->html = $html;
        $data->name = $this->name;
        $data->resolution = $this->resolution;
        $data->published = $this->published;
        return $data;
    }

    /**
     * Publish slide.
     *
     * @param  int  $id  ID of requested slide
     * @return JSON
     */
    public function publish($id) {
        $output = new \stdClass();
        $slide = Slide::find($id);
        if (!($slide)) {
            $output->error = "ID not found.";
        } else {
            $slide->published = 1;
            $slide->save();
        }
        $output->result = 1;
        $output->id = $slide->id;
        return $output;
    }

    /**
     * Unpublish slide.
     *
     * @param  int  $id  ID of requested slide
     * @return JSON
     */
    public function unpublish($id) {
        $output = new \stdClass();
        $slide = Slide::find($id);
        if (!($slide)) {
            $output->error = "ID not found.";
        } else {
            $slide->published = 0;
            $slide->save();
        }
        $output->result = 1;
        $output->id = $slide->id;
        return $output;
    }

    /**
     * Get screenshot path. Create if needed
     *
     * @param  int  $id  ID of requested slide
     * @return JSON
     */
    public static   function generateScreenShot($id) {
        $screenshot_path = self::SCREENSHOTS_FOLDER . 'screenshot' . $id . '.jpeg';
        if (!file_exists($screenshot_path)){
            copy(public_path() . self::DEFAULT_SCREENSHOT_FILE, public_path() . $screenshot_path);
        }
        return $screenshot_path;
    }

    /**
     * Update slide.
     *
     * @param  int  $id  ID of requested slide
     * @param  int  $slidetemplate_id  ID of related slidetemplate
     * @param  array{obj}  $playlistRelations  [{ "playlist_id": int, "slide_position": int, "slide_duration": int }, { "playlist_id": int, "slide_position": int, "slide_duration": int } ...]
     * @param  object  $data  Slide fields values
     * @return JSON
     */
    public function slideUpdate($id, $slidetemplate_id, $playlistRelations, $data) {
        $output = new \stdClass();
        
        // Find, if passed slide exists.
        $slide = Slide::find($id);

        // Check is slide content is really changed

        if ( (isset($data->content)) and ($data->content != $slide->content)  ) {
            $data->status = 0;
        }
        
        // Find, if passed slidetemplate exists.
        $slidetemplate = Slidetemplate::find($slidetemplate_id);

        // Find, if passed playlists exists.
        $playlistIds = [];
        if (!is_null($playlistRelations)) {
            foreach ($playlistRelations as $key => $playlistRelation) {
                if (!in_array($playlistRelation->playlist_id, $playlistIds)) {
                    $playlistIds[] = $playlistRelation->playlist_id;
                }
            }
        }
        $playlists = [];
        if (count($playlistIds) >= 1) {
            $playlists = Playlist::whereIn('id', $playlistIds)->get();
        }
        
        if (!($slide)) {
            $output->error = "ID not found.";
        } elseif (!($slidetemplate) && !is_null($slidetemplate_id)) {
            $output->error = "Slidetemplate with passed ID not found.";
        } elseif (count($playlists) != count($playlistIds)) {
            $output->error = "Not all playlists with passed ID exist.";
        } else {
            $slide->name    = isset($data->name) ? $data->name : $slide->name;
            $slide->content = isset($data->content) ? $data->content : $slide->content;
            $slide->cas_status = isset($data->status) ? $data->status : $slide->cas_status;
            $slide->color = isset($data->color) ? $data->color : $slide->color; 
            $slide->status_changed_at = isset($data->status) ? date('Y-m-d H:i:s') : $slide->status_changed_at;
            $slide->email_sent = isset($data->email_sent) ? $data->email_sent : $slide->email_sent;
            $slide->resolution = isset($data->resolution) ? $data->resolution : $slide->resolution;
            $slide->published = isset($data->published) ? $data->published : $slide->published;
            $slide->user_last_changed_id = isset($data->user_last_changed_id) ? $data->user_last_changed_id : $slide->user_last_changed_id;
            $slide->screenshot_path = isset($data->screenshot_path) ? $data->screenshot_path : $slide->screenshot_path;
            $newSlidetemplateId = NULL;
            if ($slidetemplate_id >= 1) {
                $newSlidetemplateId = $slidetemplate_id;
            } elseif (is_null($slidetemplate_id)) {
                $newSlidetemplateId = $slide->slidetemplate_id;
            }
            $slide->slidetemplate_id = $newSlidetemplateId;

            try {
                // Save slide.
                $slide->save();
                $slide->touch();

                // Sync relation playlist:slide with passed $playlistRelations array.
                if (count($playlistIds) >= 1) {
                    // Create object for synchronization.
                    $playlistRelationSync = [];
                    foreach ($playlistRelations as $key => $playlistRelation) {
                        $playlistRelationSync[$playlistRelation->playlist_id] = ['slide_position' => $playlistRelation->slide_position, 'slide_duration' => $playlistRelation->slide_duration];
                    }

                    $slide->playlists()->sync($playlistRelationSync);
                } elseif (empty($playlistIds) && (!is_null($playlistRelations))) {
                    $slide->playlists()->sync([]);
                }
                
                $output->result = 1;
                $output->id = $slide->id;
                $output->email_sent = $slide->email_sent;
                $output->updated_at = $slide->updated_at;
            } catch (\Illuminate\Database\QueryException $e) {
                $output->error = $e->getMessage();
            }
        }

        return $output;
    }

    /**
     * Update slide (set new slidetemplate for slide).
     *
     * @param  int  $id  ID of requested slide
     * @param  int  $slidetemplate_id  ID of related slidetemplate
     * @return JSON
     */
    public function slideUpdateTemplate($id, $slidetemplate_id, $user_id) {
        $output = new \stdClass();
        
        // Find, if passed slide exists.
        $slide = Slide::find($id);
        
        // Find, if passed slidetemplate exists.
        $slidetemplate = Slidetemplate::find($slidetemplate_id);
        
        if (!($slide)) {
            $output->error = "ID not found.";
        } elseif (!($slidetemplate)) {
            $output->error = "Slidetemplate with passed ID not found.";
        } else {
            $slide->slidetemplate_id = $slidetemplate_id;
            $slide->user_last_changed_id = $user_id;

            try {
                // Save slide.
                $slide->save();
                
                $output->result = 1;
                $output->id = $slide->id;
                $output->updated_at = $slide->updated_at;
            } catch (\Illuminate\Database\QueryException $e) {
                $output->error = $e->getMessage();
            }
        }

        return $output;
    }

    /**
     * Get the playlists associated with the given slide.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function playlists() {
        return $this->belongsToMany('App\Playlist')->withPivot('slide_position', 'slide_duration');
    }

    /**
     * Get the email of user who edited the slide
     *
     * @return string
     */
    public static function getLastEditorEmail($slide_id) {
        $user_id = Slide::find($slide_id)->user_last_changed_id;
        return User::find($user_id)->email;
    }

    /**
     * A slide is owned by a slidetemplate.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function slidetemplate() {
        return $this->belongsTo('App\Slidetemplate');
    }

    /**
     * A slide can have many comments.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function comments() {
        return $this->hasMany('App\Comment');
    }

    /**
     * A slide can have many schedules.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function schedules() {
        return $this->hasMany('App\Schedule');
    }
}
