<?php

namespace App\Http\Controllers;

use Session;
use File;

use Illuminate\Http\Request;
use App\Article;
use App\ArticleQuestion;
use App\ArticleAnswer;
use Ejarnutowski\LaravelApiKey\Models\ApiKey;

class ArticleController extends Controller
{
    /**
    * Display a listing of the resource.
    *
    * @return Response
    */
    public function index()
    {
        return view('articles.index');

    }
     /**
     * Show the form for creating a new resource.
     *
     * @return Response
     */
    public function create()
    {
        $categories = Article::$categories;
        $sources = Article::$sources;
        return view('articles.create', ['categories' => $categories, 'sources' => $sources]);
    }

     /**
     * Return artciles data for lis
     *
     * @return Response
     */
    public function getData()
    {
        $res = Article::getData();
        return $res;
    }
     /**
     * Show the form for editing a new resource.
     *
     * @return Response
     */
    public function edit(Request $request, $id)
    {
        $categories = Article::$categories;
        $sources = Article::$sources;
        $article = Article::find($id);
        if (empty($article)) {
            abort(404);
        }
        $selectedCategoriesArray = [];
        $selectedCategories = json_decode($article->categories);
        //return $selectedCategories;
        foreach ($selectedCategories as $value) {
            $selectedCategoriesArray[$value] = $categories[$value];
        }
        //return $selectedCategoriesArray;
        return view('articles.edit', ['selectedCategories' => $selectedCategories, 'categories' => $categories, 'sources' => $sources, 'article' => $article]);
    }


    /**
     * Store a newly created resource in storage.
     *
     * @return Response
     */
    public function add(Request $request)
    {
        $article = new Article();

        $validatedData = $request->validate([
            'name' => 'required|unique:articles|max:200',
            'image_path' => 'mimes:png,jpg,gif,jpeg,mp4|max:2097152',
            'headline' => 'max:200',
            'summary' => 'max:250',
            'source' => 'required',
            'categories' => 'required'
        ]);

        $article->name = request('name');
        $article->headline = request('headline');
        $article->summary = request('summary');
        $article->full_text = request('full_text');

        $article->cognitive_value = request('cognitive_value');
        $article->emotion_value = request('emotion_value');
        $article->exercise_value = request('exercise_value');
        $article->nutrition_value = request('nutrition_value');

        $article->recovery_value = request('recovery_value');
        $article->taglist = request('taglist');
        $article->categories = json_encode(request('categories'));
        $article->source = request('source');

        $article->save();

        if($request->hasFile('image_path')) {
           $file = $request->file('image_path');

           $path = $file->getClientOriginalName();


           $file->move(public_path().'/uploads/article_images/'.$article->id.'/', $path);
                      
           $article->image_path = '/uploads/article_images/'.$article->id.'/'. $path;
           $article->save();
        }

        if (in_array('1', json_decode($article->categories))) {
             Session::flash('message', trans('shared.#article_created_successfully#'));
             return redirect('/article/questions/'.$article->id);        	
        }


        Session::flash('message', trans('shared.#article_created_successfully#'));
        return redirect('/articles');
    }

    /**
     * Update the resource in storage.
     *
     * @return Response
     */
    public function update(Request $request, $id)
    {
        //return request('cognitive_value');
        $article = Article::find($id);

        $validatedData = $request->validate([
            'name' => 'required|max:200',
            'image_path' => 'mimes:png,jpg,gif,jpeg,mp4|max:2097152',
            'headline' => 'max:200',
            'summary' => 'max:250',
            'source' => 'required',
            'categories' => 'required'
        ]);

        $article->name = request('name');
        $article->headline = request('headline');
        $article->summary = request('summary');
        $article->full_text = request('full_text');


        $article->cognitive_value = request('cognitive_value');
        $article->emotion_value = request('emotion_value');
        $article->exercise_value = request('exercise_value');
        $article->nutrition_value = request('nutrition_value');
        
        $article->recovery_value = request('recovery_value');
        $article->taglist = request('taglist');
        $article->categories = json_encode(request('categories'));
        $article->source = request('source');

        $article->save();

        if($request->hasFile('image_path')) {
           $file = $request->file('image_path');

           $path = $file->getClientOriginalName();


           $file->move(public_path().'/uploads/article_images/'.$article->id.'/', $path);
                      
           File::delete(public_path().$article->image_path);

           $article->image_path = '/uploads/article_images/'.$article->id.'/'. $path;
           $article->save();
        }

        Session::flash('message',trans('shared.#article_updated_successfully#'));
        if (!in_array('1', json_decode($article->categories))) {
            $questionsForDelete = $article->article_questions;
            foreach ($questionsForDelete as $question) {
                $question->remove();
            }           
        }
        if (in_array('1', json_decode($article->categories))) {
            return redirect('/article/questions/'.$article->id);           
        }

        return redirect('/articles');
    }

     /**
     * Show the form for creating questions and answers for the quiz.
     *
     * @return Response
     */
    public function addQuestions($id)
    {
    	$quiz = Article::find($id);
        if (empty($quiz)) {
            abort(404);
        }
    	$questions = $quiz->article_questions;
    	//return $questions;
    	$questionDataArray = [];
    	foreach ($questions as $key => $question) {
    		//return var_dump(json_decode($question->article_answers)); 
            $questionItem = [];
    		$questionItem['id'] = $question->id;
    		$questionItem['text'] = $question->text;
    		$questionItem['difficalty_level'] = $question->difficulty_level;
    		
    		$questionItem['answers'] = $question->article_answers;
         	$questionDataArray[] = $questionItem;
    	}

    	//return $questionDataArray;



        return view('articles.quiz.questions', ['quiz'=>$quiz, 'questionsData' => $questionDataArray]);
    }

     /**
     * Store question item
     *
     * @return Response
     */
    public function storeQuestion(Request $request)
    {

    	$validatedData = $request->validate([
            'text' => 'required',
        ]);
        
        $question = new ArticleQuestion();
        $question->text = request('text');
        $question->article_id = request('article_id');
        $question->difficulty_level = request('difficalty_level');
        $question->save();

        $right_answer = request('right_answers');
        
        $newAnswersArray = (array) json_decode(request('all-answers'));

        foreach ($newAnswersArray as $key => $value) {
            $answer = ArticleAnswer::find($key);

            if (!$answer){
                $answer = new ArticleAnswer();
                $answer->question_id = $question->id;
                $answer->text = $value;
                $answer->save();
            };
            if ($key==$right_answer) {
                $question->setRightAnswer($answer);                
            };
        }


        return redirect('/article/questions/'.$question->article_id);
    }

     /**
     * Update question item
     *
     * @return Response
     */
    public function updateQuestion(Request $request, $id)
    {
        $newAnswersArray = (array) json_decode(request('all-answers'));
        if ($newAnswersArray==[]) {
            $validationRules = [
                'text' => 'required'
            ];
        } else {
            $validationRules = [
                'text' => 'required',
                'right_answers' => 'required'
            ];
        };
        $validatedData = $request->validate($validationRules);

        $question = ArticleQuestion::find($id);
        
        $question->text = request('text');
        $question->article_id = request('article_id');
        $question->difficulty_level = request('difficalty_level');
        $question->save();

        $right_answer = request('right_answers');
        
        $newAnswersArray = (array) json_decode(request('all-answers'));
        
        $currentAnswers = $question->article_answers;

        // delete answers that are not in array of new answers

        foreach ($currentAnswers as $key => $currentAnswer) {
            //return var_dump(in_array($currentAnswer->id, array_keys($newAnswersArray)));
            if (!in_array($currentAnswer->id, array_keys($newAnswersArray) )){
                $answerForDelete = ArticleAnswer::find($currentAnswer->id);
                $answerForDelete->delete();
            }
        }

        foreach ($newAnswersArray as $key => $value) {
            $answer = ArticleAnswer::find($key);

            if (!$answer){
                $answer = new ArticleAnswer();
                $answer->question_id = $question->id;
                $answer->text = $value;
                $answer->save();
            };
            if ($key==$right_answer) {
                $question->setRightAnswer($answer);                
            };
        }


        


        return redirect('/article/questions/'.$question->article_id);
    }

     /**
     * Remove question item
     *
     * @return Response
     */
    public function delete(Request $request, $id)
    {
        $article = Article::find($id);

        $article->delete();
        return 'success';
    }

     /**
     * Remove question item
     *
     * @return Response
     */
    public function removeQuestion(Request $request, $id)
    {
        $question = ArticleQuestion::find($id);

        $question->delete();
        return $question;
    }

    /**
     * API: get quiz by level and worth value
     *
     * @param Request $request
     * @param $level
     * @return \Illuminate\Http\JsonResponse
     */
    public function getQuizWithWorth(Request $request, $level)
    {
        $apiKey = $request->header('X-Authorization');

        $apiKey = ApiKey::getByKey($apiKey);
        if ( empty($apiKey) ) {
            return response()->json(['error' => trans('licenses.#api_key_invalid#')], 424);
        }

        if ( $level == 1 ) {
            $difficulty_level = 1;
        }
        else {
            $difficulty_level = round($level / 2.4);
        }

        $query = Article::whereRaw('JSON_CONTAINS(categories, \'["1"]\')');
        if ($query->count() < 1) {
            return response()->json(['error' => trans('shared.#no_quiz_articles#')], 422);
        }

        $questionByArticle = null;
        if ($request->has('worth_values') && !empty($request->input('worth_values'))) {
            foreach ($request->input('worth_values') as $value) {
                $query->where($value."_value", '>=', '6');
            }

            try {
                $articles = $query->inRandomOrder()->get();
            } catch (\Exception $exception) {
                return response()->json(['error' => $exception->getMessage()], 500);
            }

            if ($articles->count() > 0) {
                foreach ($articles as $article) {
                    $questionByArticle = $article->article_questions()->where('difficulty_level', $difficulty_level)->inRandomOrder()->first();
                    if (!empty($questionByArticle)) {
                        break;
                    }
                }
            }
        }

        if (empty($questionByArticle)) {
            $questionByLevel = ArticleQuestion::where('difficulty_level', $difficulty_level)->inRandomOrder()->first();
            if (empty($questionByLevel)) {
                return response()->json(['error' => trans('shared.#no_questions_for_level#').' '.$level.'!'], 422);
            }
            $question = $questionByLevel;
        } else {
            $question = $questionByArticle;
        }

        $result["article_id"] = $question->article->id;
        $result["text_name"] = $question->article->headline;
        $result["text"] = $question->article->full_text;
        $result["question_id"] = $question->id;
        $result["question"] = $question->text;
        $result["answers"] = [];
        $answers = $question->article_answers;
        if ( $answers->count() > 0 ) {
            foreach ($answers as $answer) {
                $result["answers"][$answer->id] = $answer->text;
                if ( $answer->is_right === 1 ) {
                    $result["right_answer_id"] = $answer->id;
                }
            }
        }

        return response()->json($result, 200);
    }

    public function getInfoTeaser(Request $request) {
        $filterCategory = $category = 0;
        if ( $request->has('category') ) {
            $filterCategory = $category = $request->get('category');
        }

        $query = Article::whereRaw('JSON_CONTAINS(categories, \'["'.$category.'"]\')');
        if ( !$request->has('category') && $category == 0 && $query->count() == 0 ) {
            $filterCategory = 3;
            $query = Article::whereRaw('JSON_CONTAINS(categories, \'["3"]\')');
        }

        if ( $request->has('worth') ) {
            $worth = $request->get('worth');
            if ( $worth != 'all' ) {
                $query = $query->where($worth, '>', 0);
            }
        }
        $articles = $query->orderBy('created_at', 'desc')->paginate(4);

        if ( $request->ajax() ) {
            if ( $category == 0 && $articles->count() < 1 ) {
                $result['message'] = trans('shared.#no_news_in_categorie#');
            }
            else {
                $result['infos'] = view('articles.infos.list', ['articles' => $articles])->render();
                $result['pagination'] = strval($articles->appends(['category' => $category, 'worth' => $worth])->links('articles.infos.pagination'));
            }

            return response()->json($result);
        }

        return view('articles.infos.teaser', ['articles' => $articles, 'filterCategory' => $filterCategory]);
    }

    public function getFullInfo($id) {
        $article = Article::find($id);
        $source = '';
        if ( $article->source !== null ) {
            $source = Article::$sources[$article->source];
        }

        return view('articles.infos.full', ['article' => $article, 'source' => $source]);
    }

    public function showModalArticle($id) {
        $model = Article::where('name', 'like', '%'.$id.'%')->first();
        if ( empty($model) ) {
            abort(404);
        }
        $article = [];
        $article['headline'] = $model->headline;
        $article['image_path'] = $model->image_path;
        $article['full_text'] = $model->full_text;
        $source = ($model->source !== null) ? Article::$sources[$model->source] : '';
        $article['data'] = $model->created_at->format("l, d.m.Y").", ".$source;

        return view('articles.modal', ['article' => $article]);
    }
}