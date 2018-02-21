<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Activity extends Model{
    //
    protected $table = 'activity';

    protected $guarded = [];

    public function user(){
        return $this->belongsTo('App\Models\User', 'user_id');
    }

    public function status(){
        return $this->belongsTo('App\Models\Status', 'status_id');
    }

    public function responses(){
        return $this->hasMany('App\Models\Response', 'activity_id');
    }

    public function data(){
        return $this->hasMany('App\Models\ActivityData', 'activity_id');
    }

    public function scopeJira($query, $serviceId){
        return $query->whereService('Jira')->whereServiceId($serviceId);
    }

    public function scopeGitHub($query, $serviceId){
        return $query->whereService('GitHub')->whereServiceId($serviceId);
    }

    public function scopeDone($query){
        return $query->whereStatusId(Status::whereName('Progress Done')->first()->id);
    }

    public function scopeInTesting($query){
        return $query->whereStatusId(Status::whereName('Ready For Testing')->first()->id);
    }

    public function scopeFailed($query){
        return $query->whereStatusId(Status::whereName('Failed Testing')->first()->id);
    }

    public function scopePassed($query){
        return $query->whereStatusId(Status::whereName('Passed Testing')->first()->id);
    }
}
