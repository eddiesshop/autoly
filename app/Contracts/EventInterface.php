<?php
/**
 * Created by PhpStorm.
 * User: edc59
 * Date: 8/11/2017
 * Time: 4:22 PM
 */

namespace App\Contracts;


interface EventInterface{

    /**
     * @return \App\Models\User
     */
    public function getUser();

    /**
     * @return \App\Models\Response
     */
    public function getResponse();

    /**
     * @return \App\Models\Jira\Issue
     */
    public function getIssue();

    /**
     * @return \App\Models\Activity
     */
    public function getActivity();

    /**
     * @return \Illuminate\Support\Collection|\App\Models\Directive
     */
    public function getDirectives();
}