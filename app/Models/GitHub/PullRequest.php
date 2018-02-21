<?php
/**
 * Created by PhpStorm.
 * User: edc59
 * Date: 11/13/2016
 * Time: 8:31 PM
 */

namespace App\Models\GitHub;

use Illuminate\Database\Eloquent\Model;

use App\Contracts\CommunicationInterface;
use App\Models\Communicator;
use App\Models\User;
use App\Traits\CommunicationTrait;

class PullRequest implements CommunicationInterface {

	use CommunicationTrait;

    const RESOURCE = 'repos/{owner}/{repo}/pulls/{number}';

    protected $pullData;

    protected $resource;

    protected $data;

    protected $user;

    protected $repository;

    public function __construct(){

        $paramCount = func_num_args();

        $params = func_get_args();

        if($paramCount == 1 && is_array($attributes = head($params))){
            $this->pullData = $attributes;

            $this->user = User::whereHas('githubAccount', function($q){
                $q->whereUserName($this->getUser()['login']);
            })->first();
        }else if($paramCount >= 2){

            $fullRepositoryName = '';
            $pullNumber = null;
            $user = null;

            foreach ($params as $param){

                if(is_string($param) && str_contains($param, '/')){
                    $fullRepositoryName = $param;
                    continue;
                }

                if(is_numeric($param) && $param > 0){
                    $pullNumber = $param;
                    continue;
                }

                if($param instanceof User){
                    $user = $param;
                    continue;
                }
            }
            //$fullRepositoryName, $pullNumber, User $user = null
            //TODO Need to make User required, if doing this for everyone, if each org is its own entity, doesn't matter as much
            $this->user = !is_null($user) ? $user : User::first();
            $gitHub = new GitHub($this->user);

            list($owner, $repoName) = explode('/', $fullRepositoryName);

            $this->resource = strtr(self::RESOURCE, [
                '{owner}' => $owner,
                '{repo}' => $repoName,
                '{number}' => $pullNumber
            ]);

            $this->pullData = $gitHub->get($this);
        }

    }

    public function setHeadBranch(Branch $branch){
        $this->headBranch = $branch;
    }

    public function setBaseBranch(Branch $branch){
        $this->baseBranch = $branch;
    }

    /**
     * @return boolean
     */
    public function hasMigrations(){

        $this->resource = strtr(self::RESOURCE . "/files", [
            '{owner}' => $this->getBase()['repo']['owner']['login'],
            '{repo}' => $this->getBase()['repo']['name'],
            '{number}' => $this->getNumber()
        ]);

        $gitHub = new GitHub($this->user);

        $files = $gitHub->get($this);

        if($this->hasMigrationPath($files)) return true;

        do{

            $linkHeader = $gitHub->getResponse()->getHeader('link');

            if(!empty($linkHeader)){
                $pullReqLinkRaw = explode('; ', $linkHeader[0]);
                $pullReqLinks['next'] = str_replace('<' . $gitHub->getHost() . '/', '', str_replace('>', '', $pullReqLinkRaw[0]));
                $pullReqLinks['last'] = str_replace($gitHub->getHost() . '/', '', substr(substr($pullReqLinkRaw[1], strpos($pullReqLinkRaw[1], '<') + 1), 0, -1));

                $this->resource = $pullReqLinks['next'];

                if($this->hasMigrationPath($gitHub->get($this))) return true;

            }else{
                $pullReqLinks['next'] = [];
                $pullReqLinks['last'] = [];
            }

        }while($pullReqLinks['next'] != $pullReqLinks['last']);

        return false;
    }

    /**
     * @return Repository
     */
    public function getRepository(){
        if(is_null($this->repository)) return $this->repository = Repository::whereGithubId($this->getBase()['repo']['id'])->first();

        return $this->repository;
    }

    /**
     * @return boolean
     */
    private function hasMigrationPath(array $files){

        foreach ($files as $file){

            if(str_contains($file['filename'], 'database/migrations')) return true;
        }

        return false;
    }

    public function __call($name, $arguments){
        $fullClassName = get_class($this).'::'.$name;

        if(!empty($name) && str_contains($name, 'get') && empty($arguments)){

            $key = snake_case(str_replace_first('get', '', $name));

            if(!array_key_exists($key, $this->pullData)) throw new FatalErrorException("Call to undefined method $fullClassName()", 0, 1, null, 62);

            return $this->pullData[$key];
        }

        return parent::__call($name, $arguments);
    }
}