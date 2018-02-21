<?php
/**
 * Created by PhpStorm.
 * User: eddiecarrasco
 * Date: 10/18/2016
 * Time: 5:42 PM
 */

namespace App\Models\GitHub;

use App\Contracts\CommunicationInterface;
use App\Models\Communicator;
use App\Models\User;
use App\Traits\CommunicationTrait;

use Exception;
use GuzzleHttp\Exception\ClientException;
use Illuminate\Support\Collection;

class GitHub extends Communicator implements CommunicationInterface{

	use CommunicationTrait;

    const URI_STRUCTURE = "{host}/{resource}";

    protected $host = "https://api.github.com";

    protected $version = "3";

    protected $resource;

    protected $data;

    private $commitMessage = 'Automagically Merged';

    public function __construct(User $user = null){
        parent::__construct();

        if(!is_null($user)) $this->setUserCredentials($user);

        $this->setHeaders();
    }

    public function setUserCredentials(User $user){
        $gitHubAccount = $user->gitHubAccount()->first();
        $this->userCredentials = base64_encode($gitHubAccount->user_name.":".$gitHubAccount->access_token);
    }

    protected function setEndpoint(CommunicationInterface $item){
        $this->endpoint = strtr(self::URI_STRUCTURE, ['{host}' => $this->getHost(), '{resource}' => $item->getResource()]);
    }

    protected function setHeaders($headers = []){
        $masterHeaders = ['Accept' => "application/vnd.github.v{$this->getVersion()}+json"];

        $diff = array_diff_assoc($headers, $masterHeaders);

        foreach ($diff as $key => $value){
            $masterHeaders[$key] = $value;
        }

        $this->headers = $masterHeaders;
    }

    protected function getHeaders()
    {
        try{
            $userCredentials = $this->getUserCredentials();

            if(array_key_exists('Authorization', $this->headers)){
                if($this->headers['Authorization'] != "Basic {$userCredentials}"){
                    $this->headers['Authorization'] = "Basic {$userCredentials}";
                }
            }else{
                $this->headers['Authorization'] = "Basic {$userCredentials}";
            }
        }catch (Exception $e){
            $clientCredentials = http_build_query(
                [
                    'client_id' => getenv('GIT_CLIENT_ID'),
                    'client_secret' => getenv('GIT_CLIENT_SECRET')
                ]
            );

            $this->endpoint .= str_contains($this->endpoint, '?') ? "&".$clientCredentials : "?".$clientCredentials;
        }

        return $this->headers;
    }

    /**
     * @param User $user
     * @return Collection|array
     */
    public function getOrganizations(User $user){
        $this->resource = 'user/orgs';
        $this->setUserCredentials($user);

        return new Collection($this->get($this));
    }

    public function getRepositories(User $user){
        $this->resource = 'user/repos';
        $this->setUserCredentials($user);

        $result = $this->get($this);

        $repositories = new Collection();

        foreach ($result as $repo){
            $repo = new Repository(/*$user, */$repo);

            if($repo->isContributor($user)) $repositories->push($repo);
        }

        if(!$repositories->isEmpty()){
            $storedRepositories = Repository::whereOwnerId($repositories->first()->owner_id)->get();

            $repositories->diff($storedRepositories)->each(function($repository){
                $repository->delete();
            });
        }

        return $repositories;
    }

    /**
     * @param User $user
     * @param Repository $repo
     * @param Branch $head
     * @param Branch $base
     * @param string $body
     * @return array|mixed
     */
    public function createPullRequest(User $user, Repository $repo, Branch $head, Branch $base, $body = ''){
        $this->resource = 'repos/'.$repo->getFullName().'/pulls';
        $this->setUserCredentials($user);

        $this->data = [
            'title' => "Pull Request Auto Magically Created for ".$head->getName(),
            'body' => $body,
            'head' => $head->getName(),
            'base' => $base->getName()
        ];

        return $this->post($this);
    }

    /**
     * @param User $user
     * @param Repository $repo
     * @param Branch $head
     * @param Branch $base
     * @param string $message
     * @return array|mixed
     */
    public function merge(User $user, Branch $head, Branch $base, $message = ''){
        $this->resource = "repos/{$base->getRepository()->getFullName()}/merges";
        $this->setUserCredentials($user);

        $this->data = [
            'base'              => $base->getName(),
            'head'              => $head->getName(),
            'commit_message'    => empty($message) ? $this->commitMessage : $message
        ];

        return $this->post($this);
    }

    /**
     * @param User $user
     * @param Repository $repo
     * @param integer $pullNumber
     * @return boolean
     */
    public function isPullMerged(User $user, Repository $repo, $pullNumber = 0){
        $this->resource = "repos/{$repo->getFullName()}/pulls/$pullNumber/merge";
        $this->setUserCredentials($user);

        try{
            $response = $this->get($this);

            return true;
        }catch (ClientException $e){
            if($e->getResponse()->getStatusCode() == 404) return false;

            throw $e;
        }
    }

    public function get(CommunicationInterface $item){
        if($item instanceof Repository && str_contains(strtolower($item->getResource()), 'branch')){
            $this->setHeaders(['Accept' => 'application/vnd.github.loki-preview+json']);
        }else{
            $this->setHeaders();//Reset headers
        }

        return parent::get($item);
    }

    public function post(CommunicationInterface $item){
        $this->setHeaders();

        return parent::post($item);
    }
}
