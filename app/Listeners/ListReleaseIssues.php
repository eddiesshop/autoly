<?php

namespace App\Listeners;

use App\Events\PassedTesting;
use App\Models\Activity;
use App\Models\Directive;
use App\Models\Jira\Search;
use App\Models\Jira\Enum\Status as JiraStatus;
use App\Models\Slack\Messenger;
use App\Models\Status;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;

class ListReleaseIssues{

    /**
     * @var Directive
     */
    private $mainDirective;

    /**
     * @var Search
     */
    private $search;

    /**
     * @var Carbon;
     */
    private $now;


    /**
     * @var Messenger
     */
    private $messenger;

    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct(){
        //
        $this->mainDirective = Directive::whereMain(true)->whereCommand('list-release-issues')->first();
        $this->search = new Search();
        $this->messenger = new Messenger();
        $this->now = Carbon::now();
    }

    /**
     * Handle the event.
     *
     * @param  PassedTesting  $event
     * @return void
     */
    public function handle(PassedTesting $event){

        if($event->getDirectives()->first()->id != $this->mainDirective->id) return null;

        $days = $this->now->isMonday() ? 3 : 1;

        $issues = $this->search->getChangedIssues([], [JiraStatus::IN_REVIEW], null, $this->now->copy()->subDay($days)->startOfDay());

        //Get The User

        //Find the create pull activity

        $msg[] = "Release Issues for {$this->now->toFormattedDateString()}";

        foreach ($issues as $key => $issue){

            if($issue->getStatusId() != JiraStatus::IN_REVIEW) continue;

            $text = [];

            $text[] = "*<{$issue->getLink()}|[{$issue->getId()}]>* {$issue->getSummary()}";

            $assignee = $issue->getAssignee();
            $assignee = $assignee instanceof User ? $assignee->name : head(explode(' ', $issue->getFields()['assignee']['displayName']));

            $passedEvent = $issue->getHistory()->whereLoose('items.0.to', JiraStatus::IN_REVIEW)->first();

            $passer = User::whereEmail($passedEvent['author']['emailAddress'])->first();
            $passer = $passer ? $passer->name : $passedEvent['author']['displayName'];

            $text[] = "Passed By: _{$passer}_   *|*   Assigned To: _{$assignee}_";

            $passedActivity = Activity::whereService('Jira')
                ->whereServiceId($issue->getId())
                ->whereStatusId($this->mainDirective->status_id)
                ->orderBy('created_at', 'DESC')
                ->first();

            $pulls = [];

            if($passedActivity){

                $createPRStatus = Status::whereService('GitHub')
                    ->whereName('Pull Request Created')
                    ->first();

                $createdPRActivity = Activity::whereService('GitHub')
                    ->whereServiceId($passedActivity->id)
                    ->whereStatusId($createPRStatus->id)
                    ->orderBy('created_at', 'DESC')
                    ->first();

                if($createdPRActivity){

                    $logPRId = Directive::whereAction('Log Created Pull Request')->first()->id;

                    foreach ($createdPRActivity->data()->whereDirectiveId($logPRId)->get() as $pullRequestData){

                        //This will ensure that only the latest PR per Repo is used.
                        $pulls[$pullRequestData->response['base']['repo']['id']] = "<{$pullRequestData->response['_links']['html']['href']}|" . ucfirst($pullRequestData->response['base']['repo']['name']) . ">";
                    }
                }
            }

            if(!empty($pulls)) $text[] = '>Pull Requests: ' . implode(', ', $pulls);

            $msg[] = count($msg) . '. ' . implode("\n", $text) . "\n";
        }

        $this->messenger->to($event->getResponse()->getRespondTo())->send(implode("\n", $msg));
    }
}
