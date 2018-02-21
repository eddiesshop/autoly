<?php

namespace App\Console\Commands;

use App\Events\ProgressDone;
use App\Models\Activity;
use App\Models\ActivityData;
use App\Models\EnvironmentCommand;
use App\Models\GitHub\Branch;
use App\Models\GitHub\GitHub;
use App\Models\GitHub\Repository;
use App\Models\Jira\Enum\Status as JiraStatus;
use App\Models\Jira\Search;
use App\Models\Slack\Messenger;
use App\Models\Status;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

use SSH;

class JiraTicketsChangedToPreRelease extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'jira:prerelease-tickets';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Will find all tickets in Pre Release status and move them to In Review after 24 Hours.';

    protected $now;

    protected $targetStatus;

    protected $finalStatus;

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct(){
        parent::__construct();

        $this->now = Carbon::now();
        $this->targetStatus = Status::forJira('Ready For Review')->first();
        $this->finalStatus = Status::forJira('Passed Testing')->first();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle(){
        //

	    $search = new Search();

	    $preRelIssues = $search->getIssues(JiraStatus::PRE_RELEASE);

	    $activityIds = [];

	    $text = [];

		foreach($preRelIssues as $issue){

			$statusChangeTime = $issue->getLatestStatusChangeTime(null, JiraStatus::PRE_RELEASE);

			switch ($this->now->diffInDays(Carbon::parse($statusChangeTime))){
				case 0: //Under 24 Hours

					$activityAttrs = [
						'user_id'           => $issue->getAssignee()->id,
						'service'           => 'Jira',
						'service_id'        => $issue->getId(),
						'status_id'         => $this->targetStatus->id,
						'response_required' => false
					];

					$hash = md5(implode('|', array_dot(array_merge($activityAttrs, ['status_change_time' => $statusChangeTime]))));

					$activityAttrs['hash'] = $hash;

					$activity = Activity::firstOrNew($activityAttrs);

					if(!$activity->exists){

						$activity->save();

						//TODO This section will house the merge functionality

						$git = new GitHub();
						$org = $git->getOrganizations($issue->getAssignee())->first();

						$repoIds = Repository::whereOwnerId($org['id'])->get()->pluck('github_id');

						$baseBranches = Branch::whereIn('repository_id', $repoIds)
											->whereName(getenv('BUSINESS_REVIEW_BRANCH_NAME'))
											->get();

						$activityIds[] = event(new ProgressDone($issue->getAssignee(), $issue, $baseBranches));

						$mergedActivitiesData = ActivityData::whereActivityId(last($activityIds))->get();

						if($mergedActivitiesData->pluck('response.autoly.merged')->contains(false)){

							$text[] = ":x: *<{$issue->getLink()}|[{$issue->getId()}]>* (*{$issue->getStatus()}*) {$issue->getSummary()}";
						}else{

							$text[] = ":heavy_check_mark: *<{$issue->getLink()}|[{$issue->getId()}]>* (*{$issue->getStatus()}  - {$this->targetStatus->name}*) {$issue->getSummary()}";
						}

					}

					break;

				default: //Over 24 Hours

					$issue->changeStatus(JiraStatus::IN_REVIEW);
			}
		}

		$mergeActivitiesData = ActivityData::whereIn('activity_id', $activityIds)->get();

	    $pullRequired = false;

	    foreach ($mergeActivitiesData->pluck('response.autoly.merged_at') as $timestamp){

	    	if(!is_null($timestamp)){

	    		$pullRequired = true;
	    		break;
		    }
	    }

		if($pullRequired){

			//TODO SSH into Business Review Env and Pull
			$commands = EnvironmentCommand::whereIn('repository_id', array_unique($mergeActivitiesData->pluck('response.autoly.repository_id')->toArray()))
							->whereStatusId($this->targetStatus->id)
							->orderBy('repository_id', 'order')
							->get();

			$commands->groupBy('type')->each(function($commandsByEnvironmentType, $type){

				switch ($type){

					case 'single':

						$commandsByEnvironmentType->groupBy('environment')->each(function($commandsByEnvironment, $environment){

							SSH::into($environment)->run($commandsByEnvironment->pluck('env_var')->toArray());
						});
						break;

					case 'group':

						$commandsByEnvironmentType->groupBy('environment')->each(function($commandsByGroupOfEnvironment, $environments){

							SSH::group($environments)->run($commandsByGroupOfEnvironment->pluck('env_var')->toArray());
						});
				}
			});
		}

		$slack = new Messenger();
		$slack->to(getenv('READY_FOR_REVIEW_CHANNEL'))->send(implode("\n", $text));
    }
}
