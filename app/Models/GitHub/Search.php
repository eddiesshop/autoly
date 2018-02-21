<?php
/**
 * Created by PhpStorm.
 * User: eddiecarrasco
 * Date: 10/19/2016
 * Time: 11:51 AM
 */

namespace App\Models\GitHub;


use App\Contracts\CommunicationInterface;

use App\Models\User;

use App\Models\GitHub\GitHub;

class Search implements CommunicationInterface {

    const RESOURCE = 'search/repositories';

    protected $resource;

    protected $data;

    /**
     * @return string
     */
    public function getResource(){
        return $this->resource;
    }

    /**
     * @return mixed
     */
    public function getData(){
        return $this->data;
    }

    public function find($repositoryTerm, User $user = null){
        $qualifiers = [
            'in' => 'name'
        ];

        if(!is_null($user)) $qualifiers['user'] = $user->gitHubAccount()->first()->user_name;

        $formattedQualifiers = [];
        foreach ($qualifiers as $key => $value){
            $formattedQualifiers[] = "$key:$value";
        }

        $this->resource = self::RESOURCE."?".http_build_query(['q' => $repositoryTerm.' '.implode(' ', $formattedQualifiers)]);

        $gitHub = new GitHub($user);
        return $gitHub->get($this);
    }

    public function getRepositories(User $user = null){

    }

    public function getPossibleWorkingBranches(){

    }

}