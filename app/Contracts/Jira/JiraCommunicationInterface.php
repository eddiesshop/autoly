<?php
/**
 * Created by PhpStorm.
 * User: edc59
 * Date: 8/31/2016
 * Time: 11:45 PM
 */

namespace App\Contracts\Jira;


interface JiraCommunicationInterface{


    /**
     * @return String
     */
    public function getResource();

    /**
     * @return Array
     */
    public function getData();

}