<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use App\Models\GitHub\Repository;

class EnvironmentCommand extends Model{
    //
    protected $table = 'environment_commands';

    protected $guarded = [];

    public function status(){
        return $this->belongsTo('App\Models\Status', 'status_id');
    }

    public function getRepository(){
        return Repository::whereGithubId($this->repository_id)->first();
    }

    public function getEnvVarAttribute($value){
        return getenv($value);
    }
}
