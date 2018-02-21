<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Directive extends Model{
    //
    protected $table = 'directives';

    protected $guarded = [];

    const COMMAND_PREFIX = '=';
    const OPTION_PREFIX = '--';

    public function status(){
    	return $this->belongsTo('App\Models\Status', 'status_id');
    }
}
