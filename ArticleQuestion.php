<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class ArticleQuestion extends Model
{
    protected $fillable = [
        'article_id', 'text', 'difficulty_level'
    ];

    public function article() {
        return $this->belongsTo('App\Article');
    }

    public function article_answers() {
        return $this->hasMany('App\ArticleAnswer', 'question_id');
    }

    public function getAnswersString(){
    	$answers = $this->article_answers;
    	$answersString = '';
    	foreach ($answers as $counter => $answer) {
    	    $answersString = $answersString.$answer->text.',';
    	}
        
        $res = substr($answersString, 0, -1);
        
        if(!$res) {
          	$res = '';
        }
    	
    	return $res;
    }

    public function getAnswerbyText($text){
    	$answers = $this->article_answers;
    	foreach ($answers as $key => $answer) {
    		if ($answer->text == $text){
    			return $answer;
    		}
    	}
    	return [];

    }
    
    public function setRightAnswer($answer){
        if (!($this->id == $answer->question_id)){
            return 'this asnswer is not realted to this question';
        }
        $answers = $this->article_answers;
        foreach ($answers as $key => $item) {
            if ($item->id == $answer->id){
                $item->is_right = 1;   
            } else {
                $item->is_right = 0;
            }
            $item->save();
        }
        return 'success';

    }

}
