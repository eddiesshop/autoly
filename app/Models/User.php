<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name', 'email', 'password',
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'password', 'remember_token',
    ];


    public function services(){
        return $this->hasMany('App\Models\Service', 'user_id');
    }

    public function scopeJiraAccount($query){
        return $this->services()->whereServiceType('J');
    }

    public function scopeGitHubAccount($query){
        return $this->services()->whereServiceType('G');
    }

    public function scopeSlackAccounts($query){
        return $this->services()->whereIn('service_type', ['S-I', 'S-U']);
    }

    public function scopeSlackId($query){
        return $this->services()->whereServiceType('S-I');
    }

    public function scopeSlackUserName($query){
        return $this->services()->whereServiceType('S-U');
    }
}
