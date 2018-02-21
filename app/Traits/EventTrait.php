<?php

namespace App\Traits;

/**
 * Created by PhpStorm.
 * User: edc59
 * Date: 8/11/2017
 * Time: 4:25 PM
 */
trait EventTrait{

	/**
	 * @return \App\Models\User
	 */
    public function getUser(){
        return $this->user;
    }

	/**
	 * @return \App\Models\Response
	 */
    public function getResponse(){
        return $this->response;
    }

	/**
	 * @return \App\Models\Jira\Issue
	 */
    public function getIssue(){
        return $this->issue;
    }

	/**
	 * @return \App\Models\Activity
	 */
    public function getActivity(){
        return $this->activity;
    }

	/**
	 * @return \Illuminate\Support\Collection|\App\Models\Directive
	 */
    public function getDirectives(){
        return $this->directives;
    }
}