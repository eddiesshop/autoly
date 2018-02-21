<?php
/**
 * Created by PhpStorm.
 * User: eddiecarrasco
 * Date: 10/19/2016
 * Time: 5:04 PM
 */

namespace App\Models\GitHub;

use App\Contracts\CommunicationInterface;

use App\Models\GitHub\GitHub;
use App\Models\GitHub\Branch;
use App\Models\User;
use App\Traits\CommunicationTrait;

use Carbon\Carbon;

use GuzzleHttp\Exception\ClientException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Symfony\Component\Debug\Exception\FatalErrorException;

use Illuminate\Support\Collection;

class Repository extends Model implements CommunicationInterface {

	use CommunicationTrait;

    const RESOURCE = 'repos/{owner}/{repo}';

    protected $resource;

    protected $data;

    private $repoData;

    private $fromGitHub = false;

    /**
     * @var Collection $branches
     */
    private $branches;

    protected $guarded = ['id'];

    protected $table = 'github_repositories';

    public function __construct(/*User $user, */$attributes = null){
//        $this->user = $user;

        if(is_string($attributes)){
            $exploded = explode('/', $attributes);

            if(count($exploded) == 2){
                //TODO when opening up to users outside of the org, need to change the instantiation of GitHub
                $git = new GitHub(User::first());

                $this->resource = strtr(self::RESOURCE, ['{owner}' => $exploded[0], '{repo}' => $exploded[1]]);

                $attributes = $git->get($this);
            }
        }

        //Need to manipulate incoming attributes array
        if(is_array($attributes) && !empty($attributes)){
            if(count($attributes) > 8){
                $this->repoData = $attributes;
                $attributes = [];
                $attributes['github_id'] = $this->repoData['id'];
                $attributes['name'] = $this->repoData['name'];
                $attributes['full_name'] = $this->repoData['full_name'];
                $attributes['owner'] = $this->repoData['owner']['login'];
                $attributes['owner_id'] = $this->repoData['owner']['id'];
                $attributes['created'] = $this->repoData['created_at'];

                $this->fromGitHub = true;
            }
        }

        if(is_null($attributes)) $attributes = [];

        parent::__construct($attributes);

        if($this->fromGitHub){

        	//Going to search for the particular Repo by GitHub ID
            $dbObject = parent::newBaseQueryBuilder()->select()->from($this->table)->where('github_id', $this->repoData['id'])->first();

            //If not found, insert into the Repositories Table
            if(!$dbObject){

	            $now = Carbon::now();
	            $attributes['created_at'] = $now->toDateTimeString();
	            $attributes['updated_at'] = $now->toDateTimeString();
	            parent::newBaseQueryBuilder()->getConnection()->table($this->table)->insert($attributes);
	            $this->wasRecentlyCreated = true;
            }else{//If it is found, check to see if we need to update anything

	            $updatedAttributes = array_diff_assoc($attributes, get_object_vars($dbObject));
	            unset($updatedAttributes['created']);

	            if(!empty($updatedAttributes)){

		            $updatedAttributes['updated_at'] = Carbon::now()->toDateTimeString();

	            	parent::newBaseQueryBuilder()->from($this->table)->where('github_id', $this->repoData['id'])->update($updatedAttributes);
	            }
            }

            $this->original = $attributes;
            $this->exists = true;
        }
    }

    public function environmentCommands(){
        return $this->hasMany('App\Models\EnvironmentCommand', 'repository_id', 'github_id');
    }

    public function isFromGitHub(){
        return $this->fromGitHub;
    }

    public function isContributor(User $user){

        $contributors = $this->getContributors();

        $userName = $user->gitHubAccount()->first()->user_name;

        $found = $contributors->pluck('author.login')->search($userName);

        return is_int($found);
    }

    public function getContributors(){

        $this->resource = strtr(self::RESOURCE, [
                '{owner}' => $this->owner,
                '{repo}' => $this->name,
            ]) . "/stats/contributors";

        //TODO when opening up to users outside of the org, need to change the instantiation of GitHub
        $gitHub = new GitHub(User::first());

        $contributors = $gitHub->get($this);

        //For some reason, GitHub doesn't always return a list of contributors on the first attempt.
        if(empty($contributors)){

            for ($i = 3; $i > 0; $i--){

                sleep(1);

                $contributors = $gitHub->get($this);

                if(!empty($contributors)) break;
            }
        }

        return new Collection($contributors);
    }

    public function getBranches($callGitHub = false, Carbon $after = null){
        //Get All Branches From DB
        //If not empty OR switch was sent, call GitHub
        //Determine which branches are new or which were deleted.

        if(is_null($this->branches)) $this->branches = Branch::whereRepositoryId($this->github_id)->get();

        if($callGitHub || $this->branches->isEmpty()){
            //TODO when opening up to users outside of the org, need to change the instantiation of GitHub
            $gitHub = new GitHub(User::first());

            $this->resource = str_replace($gitHub->getHost()."/", '', str_replace('{/branch}', '', $this->getBranchesUrl()));

            $gitHubBranches = new Collection();
            foreach ($gitHub->get($this) as $key => $attributes){
                $attributes['repository_id'] = $this->github_id;

                $gitHubBranches->push(new Branch($attributes));
            }

            do{
                $linkHeader = $gitHub->getResponse()->getHeader('link');

                if(!empty($linkHeader)){
                    $branchLinksRaw = explode('; ', $linkHeader[0]);
                    $branchLinks['next'] = str_replace('<' . $gitHub->getHost() . '/', '', str_replace('>', '', $branchLinksRaw[0]));
                    $branchLinks['last'] = str_replace($gitHub->getHost() . '/', '', substr(substr($branchLinksRaw[1], strpos($branchLinksRaw[1], '<') + 1), 0, -1));

                    $this->resource = $branchLinks['next'];

                    $moreBranches = $gitHub->get($this);

                    foreach ($moreBranches as $anotherOne){
                        $anotherOne['repository_id'] = $this->github_id;
                        $anotherOne = new Branch($anotherOne);
                        $gitHubBranches->push($anotherOne);
                    }
                }else{
                    $branchLinks['next'] = [];
                    $branchLinks['last'] = [];
                }

            }while($branchLinks['next'] != $branchLinks['last']);

            $oldBranches = [];

            foreach ($this->branches as $storedBranch){
                foreach ($gitHubBranches as $key => $gitHubBranch){
                    if($storedBranch->name == $gitHubBranch->name){
                        break;
                    }

                    if($key == count($gitHubBranches) - 1){

                        $oldBranches[] = $storedBranch;
                    }
                }
            }

            foreach ($oldBranches as $oldBranch){
                $oldBranch->delete();
            }

            /*
            Possibly don't need this because on Branch instantiation, branch data is inserted into DB
            $newBranches = [];
            foreach ($gitHubBranches as $gitHubBranch){
                //Site branches, if they are NOT in the stored branches, need to save.
                foreach ($this->branches as $key => $storedBranch){
                    if($gitHubBranch->name == $storedBranch->name){
                        break;
                    }

                    if($key == count($this->branches) - 1){

                        $newBranches[] = $gitHubBranch;
                    }
                }
            }*/

        }

        $branchQuery = Branch::whereRepositoryId($this->github_id);

        if(!is_null($after)) $branchQuery->where('created_at', '>=', $after->toDateTimeString());

        return $this->branches = $branchQuery->get();
    }


    //TODO add flag to decide whether we want the exact match or are ok receiving more than one potential resulting branch
    /**
     * @param string $name
     * @param boolean $exact
     * @param boolean $bestGuess
     * @return Collection|App\Models\GitHub\Branch
     */
    public function findBranch($name, $exact = false, $bestGuess = false){
        //If Exact flag is given, don't filter first time, just do second filter and return
        //If Exact flag is not given, branches would probably already be loaded so try to run filter,
        //If nothing is there, check for the branch name permutations in DB hitting GitHub directly.

        if($exact){
            return $this->getBranches()->filter(function($value, $key) use ($name){
                return strcasecmp($value['name'], $name) == 0;
            });
        }

        if($bestGuess && $branch = $this->bestGuessExistsOnGitHub($name)){
            return new Collection([$branch]);
        }

        return $this->getBranches()->filter(function($value, $key) use ($name){
            return str_contains(strtolower($value['name']), strtolower($name));
        });
    }

    /**
     * Will attempt to take common Repository naming convention for repo along with
     * $name and hit GitHub directly to see if branch exists on this Repository.
     *
     * @param String $name
     * @return Branch|boolean
     */
    private function bestGuessExistsOnGitHub($name){
        $commonBranchPrefixes = Branch::selectRaw("substring_index(name, '/', length(name)-length(replace(name,'/',''))) as substring_name, count(*) as freq")
            ->whereRepositoryId($this->github_id)
            ->groupBy('substring_name')
            ->having('freq', '>' , 10)
            ->havingRaw('length(substring_name) > 0')
            ->get()
            ->sortByDesc('freq')
            ->pluck('substring_name');

        $commonBranchPrefixes->prepend('');

        foreach ($commonBranchPrefixes as $prefix){

            $this->resource = strtr(Branch::RESOURCE, [
                '{owner}' => $this->owner,
                '{repo}' => $this->name,
                '{branch}' => strlen($prefix) > 0 ? "$prefix/$name" : "$name"
            ]);

            echo "$this->resource\n";

            $git = new GitHub(User::first());

            try {
                $response = $git->get($this);
                $response['repository_id'] = $this->github_id;

                if($response){
                    return new Branch($response);
                }
            }catch(ClientException $e){
                if($e->getResponse()->getStatusCode() == 404) continue;

                throw $e;
            }
        }

        return false;
    }

    public function __call($name, $arguments){
        $fullClassName = get_class($this).'::'.$name;

        if(!empty($name) && str_contains($name, 'get') && empty($arguments)){
            if(!$this->isFromGitHub()){
                /*TODO when opening up to users outside of the org, need to change the instantiation of GitHub
                 *to be based on a user from the Org.
                 */

                $this->resource = strtr(self::RESOURCE, ['{owner}' => $this->owner, '{repo}' => $this->name]);

                $git = new GitHub(User::first());

                //TODO need to account for a deleted repo
                $this->repoData = $git->get($this);

                $this->fromGitHub = true;
            }

            $key = snake_case(str_replace_first('get', '', $name));

            if(!array_key_exists($key, $this->repoData)) throw new FatalErrorException("Call to undefined method $fullClassName()", 0, 1, null, 62);

            return $this->repoData[$key];
        }

        return parent::__call($name, $arguments);
    }
}