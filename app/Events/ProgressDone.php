<?php

namespace App\Events;

use GuzzleHttp\Exception\ClientException;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Support\Collection;

use App\Contracts\EventInterface;
use App\Events\Event;
use App\Exceptions\SlackableException;
use App\Models\Activity;
use App\Models\ActivityData;
use App\Models\Directive;
use App\Models\Response;
use App\Models\User;
use App\Models\GitHub\Branch;
use App\Models\GitHub\GitHub;
use App\Models\GitHub\Repository;
use App\Models\Jira\Issue;
use App\Models\Jira\Enum\Status;
use App\Traits\EventTrait;

class ProgressDone extends Event implements EventInterface {
    use SerializesModels, EventTrait;

    /**
     * @var User
     */
    private $user;

    /**
     * @var Collection[Branch]
     */
    private $head;

    /**
     * @var Collection[Branch]
     */
    private $base;

	/**
	 * @var Collection[Branch]
	 */
    private $mergableBranches;

	/**
	 * @var Response
	 */
    private $response;

    /**
     * @var Activity
     */
    private $activity;

	/**
     * @var Issue
     */
    private $issue;

	/**
	 * Create a new event instance.
	 *
	 * @param User $user
	 * @param Issue $issue
	 * @param $base Collection[Branch]|Branch|string
	 * @param $head Collection[Branch]|Branch|string
	 * @param $response Response
	 * @param $directives Collection[Directive]
	 * @throws \Exception
	 */

	//TODO need to switch base and head in Directive Dispatcher
	public function __construct(User $user, Issue $issue = null, $base = null, $head = null, Response $response = null, Collection $directives = null){
//    public function __construct(UserResponse $response){
		//
		$this->user = $user;

		$this->directives = $directives;

		if(is_null($directives)){
			$this->directives = Directive::whereStatusId(1)->orderBy('order')->get();
		}

		$this->issue = $issue;

		if(is_null($issue)){

			$main = Directive::COMMAND_PREFIX . $this->directives->where('main', 1)->first()->command;

			if(is_null($response)) throw new \Exception("Jira Issue and Response cannot be null.");

			$mainKey = array_search($main, $response->response);

			$this->issue = new Issue($response->sliceCommandOptionParams($mainKey)[0]);
		}

		/////////////////////
		//Find the branches//
		/////////////////////
		/**
		 * For this will need to check with directives, see if working-branch was passed.
		 * Into should be passed in. Should just be able to pass in repo/branch-name and get the branch.
		 **/

		if($base instanceof Branch) $this->base = (new Collection())->push($base);

		if($base instanceof Collection && !$base->isEmpty()) $this->base = $base;

		if(is_string($base)) $this->base = (new Collection())->push(new Branch($base));

		if(is_null($this->base)){

			//Response cannot be null, will need to know which branch they want merged.
			if(is_null($response)) throw new \Exception("Base Branch and Response cannot be null.");

			$this->base = new Collection();

			$intoOption = Directive::OPTION_PREFIX . $this->directives->where('order', 3)->first()->command;

			$intoKey = array_search($intoOption, $response->response);

			$branches = $response->sliceCommandOptionParams($intoKey);

			foreach ($branches as $branchName){

				//TODO need to make sure that User object is passed in
				$branch = new Branch($branchName);

				$this->base->push($branch);
			}
		}


		if($head instanceof Branch) $this->head = (new Collection())->push($head);

		if($head instanceof Collection && !$head->isEmpty()) $this->head = $head;

		if(is_string($head)) $this->head = (new Collection())->push(new Branch($head));

		if(is_null($this->head)){
			//Response cannot be null, will need to know which branch they want merged.
			if(is_null($response)){

				//TODO ask user to confirm that we've obtained the correct branches to merge.
				$this->head = Branch::whereIn('repository_id', $this->base->pluck('repository_id'))
					->whereMerged(false)
					->whereJiraKey($this->issue->getId())
					->get();

				if($this->head->isEmpty()) throw new \Exception("Response is null and cannot find any Head Branches to work with.");
			}else{

				$this->head = new Collection();

				$wbOption = Directive::OPTION_PREFIX . $this->directives->where('order', 2)->first()->command;

				$wbKey = array_search($wbOption, $response->response);

				$branches = $wbKey !== false ? $response->sliceCommandOptionParams($wbKey) : [];

				if(!empty($branches)){

					foreach ($branches as $branchName){

						$branch = new Branch($branchName);

						$this->head->push($branch);
					}
				}else{

					//TODO ask user to confirm that we've obtained the correct branches to merge.
					$this->head = Branch::whereIn('repository_id', $this->base->pluck('repository_id'))
						->whereMerged(false)
						->whereJiraKey($this->issue->getId())
						->get();
				}
			}
		}

		$this->response = $response;

		if(is_null($response)){
			$response = '';

			foreach ($this->directives as $directive){

				switch ($directive->order){

					case 1: //Merge

						$response .= Directive::COMMAND_PREFIX . "$directive->command {$this->issue->getId()} ";
						break;

					case 2:

						$response .= Directive::OPTION_PREFIX . "$directive->command ";

						$response .= $this->head->map(function($branch){
								return $branch->getRepository()->name . '/' . $branch->name;
							})->implode(" ") . " ";
						break;

					case 3:

						$response .= Directive::OPTION_PREFIX . "$directive->command ";

						$response .= $this->base->map(function($branch){
								return $branch->getRepository()->name . '/' . $branch->name;
							})->implode(" ") . " ";
						break;
				}
			}

			$this->response = Response::create([
				'user_id' => $this->user->id,
				'response' => $response
			]);
		}else if(!$response->exists){
			$this->response->save();
		}

		//TODO see comments below

		/*
		 * Need some protections in here
		 * 1. check ticket status, if --into flag contains a master branch
		 *      a. need to see if ticket is hotfix
		 *      b. has hotfix label
		 *      c. maybe add directive that ignores ticket status?
		 * */

		$this->mergableBranches = new Collection();

		foreach ($this->head as $headBranch){

			foreach ($this->base as $baseBranch){

				if($baseBranch->repository_id == $headBranch->repository_id){

					$this->mergableBranches->push([
						'base' => $baseBranch,
						'head' => $headBranch
					]);
					break;
				}
			}
		}

		if(is_null($this->response->activity)){
			$activityAttrs = [
				'user_id'           => $user->id,
				'service'           => 'Jira',
				'service_id'        => $this->issue->getId(),
				'status_id'         => 1,
				'response_required' => 0
			];

			$hash = array_merge($activityAttrs, ['status_change_time' => $this->issue->getLatestStatusChangeTime(Status::IN_PROGRESS, Status::IN_TESTING)]);

			$activityAttrs['hash'] = md5(implode('|', array_dot($hash)));

			$this->activity = Activity::firstOrCreate($activityAttrs);

			$this->response->activity()->associate($this->activity);
			$this->response->save();
		}else{
			$this->activity = $response->activity;
		}
	}

	/**
     * @var Collection[Directive]
     */
    private $directives;

	/**
     * Get the channels the event should be broadcast on.
     *
     * @return array
     */
    public function broadcastOn()
    {
        return [];
    }

    /**
     * @return Collection[Branch]
     */
    public function getHead(){
        return $this->head;
    }

    /**
     * @return Collection[Branch]
     */
    public function getBase(){
        return $this->base;
    }

    /**
     * @return Collection[Branch]
     */
    public function getMergableBranches(){
        return $this->mergableBranches;
    }
}
