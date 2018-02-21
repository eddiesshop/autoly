<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Status extends Model{
    //
    protected $table = 'status';

    public function scopeFor($query, $service, $name){
    	return $query->whereService($service)->whereName($name);
    }

    public function scopeForJira($query, $name){
    	return $query->for('Jira', $name);
    }

    public function scopeForGitHub($query, $name){
    	return $query->for('GitHub', $name);
    }
}
