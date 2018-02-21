<?php

namespace App\Listeners;

use App\Events\ProgressDone;
use App\Models\Activity;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;

use GuzzleHttp\Exception\ClientException;

use App\Models\ActivityData;
use App\Models\Status;
use App\Models\GitHub\GitHub;
use App\Models\Slack\Messenger;

use Carbon\Carbon;

class GitHubMerge
{
    /**
     * @var Activity
     */
    private $activity;

    private $git;

    /**
     * @var Messenger
     */
    private $messenger;

    /**
     * @var Status
     */
    private $status;

    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct(){
        //
        $this->git = new GitHub();
        $this->messenger = new Messenger();
        $this->status = Status::whereService('GitHub')->whereName('Performed Merge')->first();
        $this->activity = new Activity([
            'service'           => 'GitHub',
            'status_id'         => $this->status->id,
            'response_required' => 0
        ]);
    }

    /**
     * Handle the event.
     *
     * @param  ProgressDone  $event
     * @return void
     */
    public function handle(ProgressDone $event){

        $this->activity->user_id = $event->getUser()->id;
        $this->activity->service_id = $event->getActivity()->id;
        $this->activity->save();

        ////////////////////////////////////////////
        // Where the magic happens, Perform Merge //
        ////////////////////////////////////////////

        $commitMsg = '';

        $msgOption = $event->getDirectives()->where('order', 4)->first()->command;

        $msgKey = array_search($msgOption, $event->getResponse()->response);

        if($msgKey !== false) $commitMsg = implode(' ', $event->getResponse()->sliceCommandOptionParams($msgKey));

        $slackResponse = [];
        $jiraComment = [$this->status->name];
        $hashes = [];

        foreach ($event->getMergableBranches() as $branchPair){

            try{

                $response = $this->git->merge($event->getUser(), $branchPair['head'], $branchPair['base'], $commitMsg);

                switch ($this->git->getResponse()->getStatusCode()){
                    case 201: //Merge Successful
                        //Create activity data and save. Add to message to send to user
                        $activityData = new ActivityData([
                            'directive_id' => $event->getDirectives()->where('order', 5)->first()->id,
                            'response' => array_merge($response, [
                            	'autoly' => [
                                    'repository_id' => $branchPair['head']->repository_id,
		                            'head' => $branchPair['head']->getRepository()->name . '/' . $branchPair['head']->name,
		                            'base' => $branchPair['base']->getRepository()->name . '/' . $branchPair['base']->name,
		                            'merged' => true,
		                            'merged_at' => Carbon::now()->toDateTimeString()
	                            ]
                            ])
                        ]);

                        $hashes[] = $response['sha'];

                        $this->activity->data()->save($activityData);

                        $slackResponse[] = ucfirst($branchPair['base']->getRepository()->getName()) . ": <{$response['html_url']}|{$branchPair['base']->getName()} &lt;- {$branchPair['head']->getName()}> :heavy_check_mark:";
                        $jiraComment[] = ucfirst($branchPair['base']->getRepository()->getName()) . ": [{$branchPair['base']->getName()} <- {$branchPair['head']->getName()}|{$response['html_url']}]";

                        break;
                    case 204: //Already merged
                        //Nothing to do here
                        $activityData = new ActivityData([
                            'directive_id' => $event->getDirectives()->where('order', 5)->first()->id,
                            'response' => [
                            	'autoly' => [
                                    'repository_id' => $branchPair['head']->repository_id,
		                            'head' => $branchPair['head']->getRepository()->name . '/' . $branchPair['head']->name,
		                            'base' => $branchPair['base']->getRepository()->name . '/' . $branchPair['base']->name,
		                            'merged' => true,
		                            'merged_at' => null
	                            ]
                            ]
                        ]);

                        $hashes[] = $branchPair['head']->getCommit()['sha'];

                        $this->activity->data()->save($activityData);

                        $slackResponse[] = ucfirst($branchPair['base']->getRepository()->getName()) . ": {$branchPair['base']->getName()} <- {$branchPair['head']->getName()} :ok:";
                        $jiraComment[] = ucfirst($branchPair['base']->getRepository()->getName()) . ": {$branchPair['base']->getName()} <- {$branchPair['head']->getName()} (Already merged, nothing to do here)";

                        break;
                }

            }catch (ClientException $e) {

                if ($e->getResponse()->getStatusCode() == 409) {
                    //Merge Conflict
                    //Add to message to send to user
	                $activityData = new ActivityData([
		                'directive_id' => $event->getDirectives()->where('order', 5)->first()->id,
		                'response' => [
		                	'autoly' => [
                                'repository_id' => $branchPair['head']->repository_id,
				                'head' => $branchPair['head']->getRepository()->name . '/' . $branchPair['head']->name,
				                'base' => $branchPair['base']->getRepository()->name . '/' . $branchPair['base']->name,
				                'merged' => false,
				                'merged_at' => null
                            ]
		                ]
	                ]);

	                $this->activity->data()->save($activityData);

                    $slackResponse[] = ucfirst($branchPair['base']->getRepository()->getName()) . ": {$branchPair['base']->getName()} <- {$branchPair['head']->getName()} :warning:";
                    $jiraComment[] = ucfirst($branchPair['base']->getRepository()->getName()) . ": {$branchPair['base']->getName()} <- {$branchPair['head']->getName()} (Merge conflict; Dev to resolve)";

                    continue;
                }

                throw $e;
            }
        }

        $this->activity->update([
            'hash' => md5(implode('|', $hashes))
        ]);

        $this->messenger->attach([
            'title' => "[{$event->getIssue()->getId()}] {$event->getIssue()->getSummary()}",
            'title_link' => $event->getIssue()->getLink(),
            'fallback' => "[{$event->getIssue()->getId()}]\nCurrent Status: {$event->getIssue()->getStatus()} - {$this->status->name}",
            'text' => "*{$event->getIssue()->getStatus()} - {$this->status->name}*\n".implode("\n", $slackResponse)
        ]);

        $this->messenger->to($event->getResponse()->getRespondTo())->send();

        $event->getIssue()->makeComment(implode("\n", $jiraComment));

        return $this->activity->id;
    }
}
