<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Article extends Model
{
    protected $fillable = [
        'name', 'headline', 'summary', 'full_text', 'image_path', 'taglist', 'categories', 'source',
        'cognitive_value', 'emotion_value', 'nutrition_value', 'exercise_value', 'recovery_value'
    ];

    public static $categories = [
        '0' => 'News',
        '1' => 'Quiz',
        '2' => 'Advertisement',
        '3' => 'Knowledge database',
    ];

    public static $sources = [
        '0' => 'Bewango News',
        '1' => 'Schlafcoach',
        '2' => 'Trainingscoach',
    ];

    public function article_questions() {
        return $this->hasMany('App\ArticleQuestion');
    }

    public static function getData() {
        $articles = Article::all();
        $res = [];
        foreach ($articles as $key => $article) {
            $categoriesString = '';
            $categoriesInfo = json_decode($article->categories);
            //return var_dump($categories);
            foreach ($categoriesInfo as $categoryID) {
                $categoryStr = Article::$categories[$categoryID];
                $categoriesString .= $categoryStr.', ';
            }
            if ($categoriesString) {
                $categoriesString = substr($categoriesString, 0, -2);
            }
            if (array_key_exists($article->source, Article::$sources)){
                $article->source = Article::$sources[$article->source];
            }

            $article->categories = $categoriesString;
            $res[] = $article; 
        }
        return $res;
    }

}
