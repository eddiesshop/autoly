<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Service extends Model{
    protected $table = 'user_services';

    protected $guarded = ['id'];

    public function user(){
        return $this->belongsTo('App\Models\User');
    }
}
