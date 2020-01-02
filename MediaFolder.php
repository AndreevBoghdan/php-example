<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Auth;

class MediaFolder extends Model
{
    const DEFAULT_COLLECTION = "root";
    const PREFIX_COLLECTION = "folder";
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name'
    ];

    public function safeData(){
        return $this->select('id', 'name');
    }

    public function company(){
        return $this->belongsTo('App\Company');
    }

    public function collection(){
        return self::PREFIX_COLLECTION . $this->id;
    }

    public static function getCollection($id){
        $folder = MediaFolder::find($id);
        $collection = self::DEFAULT_COLLECTION;
        if($folder){
            $collection =  $folder->collection();
        }
        return $collection;
    }

}
