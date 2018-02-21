<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ActivityData extends Model{
    //
    protected $table = 'activity_data';

    protected $guarded = [];

    protected $casts = [
        'response' => 'json'
    ];

    public function activity(){
        return $this->belongsTo('App\Models\Activity', 'activity_id');
    }

    public function directive(){
        return $this->belongsTo('App\Models\Directive', 'directive_id');
    }
}
