<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Response extends Model{
    //

    protected $table = 'user_responses';

    protected $guarded = ['id'];

    public function user(){
        return $this->belongsTo('App\Models\User', 'user_id');
    }

    public function activity(){
        return $this->belongsTo('App\Models\Activity', 'activity_id');
    }

    public function getResponseAttribute($value){
    	return explode(' ', $value);
    }

    /**
     * @param array $commands
     * @param integer $targetKey
     * @param integer|boolean $terminatingKey
     * @return array
     */
    public function sliceCommandOptionParams($targetKey, $terminatingKey = false){
        $start = null;
        $length = null;

        $start = $targetKey + 1;

        if($terminatingKey !== false && $targetKey < $terminatingKey){
            $length = $terminatingKey - $start;
        }

        if($terminatingKey === false){
            for($key = $start + 1; $key < count($this->response); $key++){
                if(starts_with($this->response[$key], Directive::OPTION_PREFIX)){
                    $length = $key - $start;
                    break;
                }
            }
        }

        return !is_null($length) ? array_slice($this->response, $start, $length) : array_slice($this->response, $start);
    }

    public function isViaChannel(){
        return !is_null($this->channel_id);
    }

    public function getRespondTo(){
        return $this->isViaChannel() ? $this->channel_id : $this->user;
    }
}
