<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class AchievementPatient extends Model
{
    protected $table = 'achievement_patient';

    protected $fillable = [
        'patient_id', 'achievement_id', 'level'
    ];
	
	
    /**
     * Get the last patient achievements 
     *
     * @return array
     */
	public function getLastPatientAchievements($patientId){
        $achievementPatient = AchievementPatient::where("patient_id", $patientId)->orderBy('created_at', 'DESC')->first();
		
        if ( isset($achievementPatient->achievement_id) ) {
            $result = [];
			$achievement = Achievement::find($achievement_id);
			if(isset($achievement->id)){
				$result['id'] = $achievement->id;
				$result['name'] = $achievement->name;
				$result['description_de'] = $achievement->description_de;
				$result['badge_graphics_location'] = $achievement->badge_graphics_location;
				$result['last_update'] = $achievement->updated_at->format("Y-m-d");
				$result['cognitive_value'] = $achievement->cognitive_value;
				$result['emotion_value'] = $achievement->emotion_value;
				$result['nutrition_value'] = $achievement->nutrition_value;
				$result['exercise_value'] = $achievement->exercise_value;
				$result['recovery_value'] = $achievement->recovery_value;
			} else {
				$result = ['message' => 'There are no acievement records for this patient!'];
			}
		} else {
            $result = ['message' => 'There are no acievement records for this patient!'];
		}
	}
}
