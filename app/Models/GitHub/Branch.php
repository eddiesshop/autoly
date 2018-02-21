<?php

namespace App\Models\GitHub;

use App\Contracts\CommunicationInterface;
use App\Exceptions\SlackableException;
use App\Exceptions\JiraIssueNotFoundException;
use App\Models\GitHub\Repository;
use App\Models\Jira\Issue;
use App\Models\User;
use App\Traits\CommunicationTrait;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

use GuzzleHttp\Exception\ClientException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Eloquent\SoftDeletes;
use Symfony\Component\Debug\Exception\FatalErrorException;


class Branch extends Model implements CommunicationInterface {

	use CommunicationTrait, SoftDeletes;
    //
    const RESOURCE = "repos/{owner}/{repo}/branches/{branch}";

    protected $table = "github_repository_branches";

    protected $guarded = ['id'];

    private $fromGitHub = false;

    private $branchData;

    private $data;

    private $resource;

    protected $dates = [
    	'deleted_at'
    ];

    /**
     * @throws ClientException
     */
    public function __construct($attributes = null){

        if(is_array($attributes) && !empty($attributes)){
            if(count($attributes) >= 5){
                $this->branchData = $attributes;
                $attributes = [];
                $attributes['name'] = $this->branchData['name'];
                $attributes['repository_id'] = $this->branchData['repository_id'];
            }
        }

        if(is_string($attributes)){
            $exploded = explode('/', $attributes);

            //TODO need to add a component to ensure that a User can only access their own Organization's repos and branches

            //Assuming this format for string: organization/repo-name/branch-name
	        try{

	        	$repo = Repository::whereFullName($exploded[0] . '/' . strtolower($exploded[1]))->firstOrFail();

	        }catch (ModelNotFoundException $e){

		        $repo = Repository::whereName(strtolower($exploded[0]))->firstOrFail();
	        }

	        $repoExploded = explode('/', $repo->full_name);

            $attributes = [
                'repository_id' => $repo->github_id,
                'name' => implode("/", array_diff($exploded, $repoExploded))
            ];

            $dbObject = parent::newBaseQueryBuilder()->select()->from($this->table)->where($attributes)->first();

            if($dbObject){
                $attributes = array_merge($attributes, array_diff_key((array) $dbObject, $attributes));
            }

            foreach ($attributes as $name => $value){
                $this->$name = $value;
            }

            //Need to force magic method to force GitHub call.
            $this->get_links()['self'];
        }

        if(is_null($attributes)) $attributes = [];

        parent::__construct($attributes);//This line is the pain in the a. One thing I dislike about Laravel

        if(isset($this->branchData)){//If we have branchData, its come directly from GitHub
            $explodedPath = explode('/', parse_url($this->branchData['commit']['url'], PHP_URL_PATH));

            $attributes['repository_id'] = Repository::whereFullName($explodedPath[2]."/".$explodedPath[3])->first()->github_id;

            $dbObject = parent::newBaseQueryBuilder()->select()->from($this->table)->where($attributes)->first();

            //Just need to check if we have the branch in DB, if not, insert
            if(!$dbObject){
                $now = Carbon::now();
                $attributes['created_at'] = $now->toDateTimeString();
                $attributes['updated_at'] = $now->toDateTimeString();

                //Using the magic method to force the GitHub call for this specific branch, in order to get the URL data
                $this->repository_id = $attributes['repository_id'];
                $this->name = $attributes['name'];
                $attributes['api_url'] = $this->get_links()['self'];
                $attributes['html_url'] = $this->get_links()['html'];

				preg_match('/^[a-zA-Z]*[-,_][0-9]*/', last(explode('/', $this->branchData['name'])));

                if(!empty($matches)){

                        try{

                                $issue = new Issue(head($matches));

                                $attributes['jira_key'] = head($matches);
                        }catch(JiraIssueNotFoundException $e){
                                //Nothing to do here
                        }
                }

                $this->id = parent::newBaseQueryBuilder()->getConnection()->table($this->table)->insertGetId($attributes);
                $this->wasRecentlyCreated = true;
            }

            $this->original = $attributes;
            $this->exists = true;
        }
    }

    public function getRepository(){
        return Repository::whereGithubId($this->repository_id)->first();
    }

    public function __call($name, $arguments){
        $fullClassName = get_class($this).'::'.$name;

        if(!empty($name) && str_contains($name, 'get') && empty($arguments)){
            if(!$this->fromGitHub){
                /*TODO when opening up to users outside of the org, need to change the instantiation of GitHub
                 *to be based on a user from the Org.
                 */

                $this->resource = strtr(self::RESOURCE, [
                    '{owner}' => $this->getRepository()->owner,
                    '{repo}' => $this->getRepository()->name,
                    '{branch}' => $this->name
                ]);

                $git = new GitHub(User::first());

                //TODO need to account for a deleted repo
                $this->branchData = $git->get($this);

                $this->fromGitHub = true;
            }

            $key = snake_case(str_replace_first('get', '', $name));

            if(!array_key_exists($key, $this->branchData)) throw new FatalErrorException("Call to undefined method $fullClassName()", 0, 1, null, 62);

            return $this->branchData[$key];
        }

        return parent::__call($name, $arguments);
    }
}
