<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class ArticleAnswer extends Model
{
    protected $fillable = [
        'question_id', 'text'
    ];

    public function question() {
        return $this->belongsTo('App\Article');
    }
}
