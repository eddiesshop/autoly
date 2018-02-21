<?php
/**
 * Created by PhpStorm.
 * User: eddiecarrasco
 * Date: 10/18/2016
 * Time: 5:51 PM
 */

namespace App\Contracts;


interface CommunicationInterface{

    /**
     * @return String
     */
    public function getResource();

    /**
     * @return Array
     */
    public function getData();

    /**
     * @return null
     */
    public function resetData();
}