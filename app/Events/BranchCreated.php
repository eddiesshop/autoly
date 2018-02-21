<?php

namespace App\Events;

use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Support\Collection;

use App\Contracts\EventInterface;
use App\Events\Event;
use App\Models\Activity;
use App\Models\Directive;
use App\Models\GitHub\Branch;
use App\Models\Jira\Issue;
use App\Models\Response;
use App\Models\Status;
use App\Models\User;
use App\Traits\EventTrait;

class BranchCreated extends Event implements EventInterface
{
    use SerializesModels, EventTrait;

    /**
     * @var User
     */
    private $user;

	/**
	 * @var Activity
	 */
	private $activity;

	/**
	 * @var Collection[Branch]
	 */
	private $branches;

	/**
	 * @var Issue
	 */
	private $issue;

	/**
	 * @var Response
	 */
	private $response;

    /**
     * @var Status
     */
    private $status;

    /**
     * @var Collection | Directive;
     */
    private $directives;

    /**
     * Create a new event instance.
     *
     * @param User $user The user creating the branch
     * @param Collection[Branch]|string $branch The branch being created or which was recently created
     * @param Issue $issue The issue to associate the branch with
     * @param Response $response The response/command given by the User
     * @param Collection|Directive $directives The directives for this event
     * @throws \Exception
     *
     * @return void
     */
    public function __construct(User $user, $branch = null, Issue $issue = null, Response $response = null, Collection $directives = null){
        //

	    $this->user = $user;

		$this->status = Status::forGitHub('Branch Created')->first();

	    $this->directives = $directives;

		if(is_null($directives)){

			$this->directives = Directive::whereStatusId($this->status->id)->orderBy('order')->get();

			$mainkeys = $this->directives->where('main', 1)->keys();

			$multipleMain = $mainkeys->count() >= 2 ? true : false;

			if($multipleMain){

				//This is the first implementation of this status, so get first set of directives.

				$start = $mainkeys->get(0);
				$end = $mainkeys->get(1) - 1;

				$this->directives = $this->directives->slice($start, $end);
			}
		}

	    if($branch instanceof Branch){

		    $this->branches = (new Collection())->push($branch);
	    }

	    if(is_string($branch)) $this->branches = (new Collection())->push(new Branch($branch));

	    if($branch instanceof Collection && !$branch->isEmpty()) $this->branches = $branch;

	    if(is_null($this->branches)){

		    if(is_null($response)) throw new \Exception('Branch is null so Response cannot be null!');

			//Per Directives, First Param should be Branch
		    $main = Directive::COMMAND_PREFIX . $this->directives->where('main', 1)->first()->command;

		    $mainKey = array_search($main, $response->response);

		    $this->branches = new Collection();

		    foreach ($response->sliceCommandOptionParams($mainKey) as $branch){

			    $this->branches->push(new Branch($branch));
		    }
	    }

	    $this->issue = $issue;

	    if(is_null($this->issue)){

		    if(is_null($response)) throw new \Exception('Issue is null so Response cannot be null!');

		    //Per Directives, First Param should be Branch
		    $option = Directive::OPTION_PREFIX . $this->directives->where('id', 19)->first()->command;

		    $optionKey = array_search($option, $response->response);

		    $this->issue = new Issue($response->sliceCommandOptionParams($optionKey)[0]);
	    }

	    //See if Activity already exists.
		$this->activity = Activity::gitHub($this->branches->implode('id', ','))
	                        ->whereUserId($this->user->id)
	                        ->whereStatusId($this->status->id)
							->orderBy('created_at', 'DESC')
							->first();

		if(is_null($this->activity)){

			$activityAttrs = [
				'user_id'           => $this->user->id,
				'service'           => $this->status->service,
				'service_id'        => $this->branches->implode('id', ','),
				'status_id'         => $this->status->id,
				'response_required' => 0,
			];

			$hash = array_merge($activityAttrs, ['' => '']);

			$activityAttrs['hash'] = md5(implode('|', array_dot($hash)));

			$this->activity = Activity::create($activityAttrs);
		}

	    //Need section to generate $response;
	    $this->response = $response;

		if(is_null($this->response)){

			if(is_null($this->branches) && is_null($this->issue)){
				//TODO determine when running in CLI/Slack env. Throw Error Message according to env.

				throw new \Exception('Branch and Issue cannot be null when Response is null.');
			}

			$response = [];

			foreach ($this->directives as $directive){

				switch ($directive->id){

					case 18: //Link

						$branches = [];

						foreach ($this->branches as $branch){

							$branches[] = "{$branch->getRepository()->getName()}/{$branch->getName()}";
						}

						$response[] = Directive::COMMAND_PREFIX . $directive->command. ' ' . implode(' ', $branches);
						break;
					case 19: //To

						$response[] = Directive::OPTION_PREFIX . "$directive->command {$this->issue->getId()}";
						break;
				}
			}

			$this->response = Response::create([
				'user_id'   => $this->user->id,
				'response'  => implode(' ', $response)
			]);
		}

		$this->response->activity()->associate($this->activity);
		$this->response->save();
    }

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
	public function getBranches(){
    	return $this->branches;
	}
}
