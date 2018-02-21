<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

use App\Models\Jira\Search;
use App\Models\Jira\Enum\Status;

use App\Models\Activity;
use App\Models\User;
use App\Models\Status as ModelStatus;
use App\Models\Slack\Messenger;

use Carbon\Carbon;

class JiraTicketsChangedToInProgress extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'jira:failed-tickets';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command to send a message to developers of Failed Tickets';

    private $search;

    private $time;

    private $messenger;

    private $status;

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
        $this->search = new Search();
        $this->time = Carbon::now();
        $this->messenger = new Messenger();
        $this->status = ModelStatus::whereService('Jira')->whereName('Failed Testing')->first();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        //
        $users = User::has('jiraAccount')->get();

        foreach($users as $user){

            $issues = $this->search->getChangedIssues([Status::IN_TESTING, Status::IN_REVIEW], [Status::IN_PROGRESS], $user, $this->time->subHour());

            foreach($issues as $issue){

                $stageFail = $issue->getHistory()
                    ->whereLoose('items.0.from', Status::IN_TESTING)
                    ->whereLoose('items.0.to', Status::IN_PROGRESS);

                $stageFailTime = !$stageFail->isEmpty() ? Carbon::parse($stageFail->last()['created']) : Carbon::parse("1970-01-01 00:00:00");

                $targetChangeTime = $stageFailTime;

                $preprodFail = $issue->getHistory()
                    ->whereLoose('items.0.from', Status::IN_REVIEW)
                    ->whereLoose('items.0.to', Status::IN_PROGRESS);

                if(!$preprodFail->isEmpty()){
                    $preprodFailTime = Carbon::parse($preprodFail->last()['created']);

                    if($preprodFailTime->gt($stageFailTime)) $targetChangeTime = $preprodFailTime;
                }

                $lastComment = $issue->getComments()->last();
                $lastCommentTime = Carbon::parse($lastComment['created'])->addMinutes(15);

                if($lastCommentTime->gte($targetChangeTime) && !empty($lastComment['author']['name']) && $lastComment['author']['name'] != $user->jiraAccount()->first()->user_name && $lastComment['author']['name'] != 'autoly'){
                    //TODO Need to add another check to verify message hasn't already been sent regarding this issue

                    $targetUser = $user;
                    $authorJiraUserName = $lastComment['author']['name'];
                    $authorSlackUserName = User::whereHas('jiraAccount', function($query) use ($authorJiraUserName){
                        $query->where('user_name', $authorJiraUserName);
                    })->first()->slackId()->first()->user_name;

                    $commentBody = $lastComment['body'];

                    $startPos = strpos($commentBody, '[~');

                    if($startPos !== false){
                        $endPos = strpos($commentBody, ']', $startPos);

                        $targetJiraUserName = substr($commentBody, $startPos+2, $endPos - ($startPos+2));

                        $targetUser = User::whereHas('jiraAccount', function($query) use ($targetJiraUserName){
                            $query->where('user_name', $targetJiraUserName);
                        })->first();

                        $commentBody = str_replace("[~$targetJiraUserName]", $targetUser->name, $commentBody);
                    }

                    $activityData = [
                        'user_id' => $targetUser->id,
                        'service' => 'Jira',
                        'service_id' => $issue->getId(),
                        'status_id' => $this->status->id,
                        'response_required' => false
                    ];

                    $hash = array_merge($activityData, ['status_change_time' => $targetChangeTime]);

                    $activityData['hash'] = md5(implode('|', array_dot($hash)));

                    $activity = Activity::firstOrNew($activityData);

                    if(!$activity->exists){
                        $activity->save();

                        $this->messenger->attach([
                            'title'     => "[{$issue->getId()}] {$issue->getSummary()}",
                            'color'     => '#e87157',
                            'title_link'=> $issue->getLink(),
                            'fallback'  => "[{$issue->getId()}] {$issue->getSummary()}\nCurrent Status: {$issue->getStatus()} - {$this->status->name}",
                            'text'      => "*{$issue->getStatus()} - {$this->status->name}*\n{$lastComment['author']['displayName']} (<@{$authorSlackUserName}>) has written the following:\n>>>_{$commentBody}_",
                        ]);

                        $this->messenger->to($targetUser->email)->send();
                    }
                }
            };
        };

    }
}
