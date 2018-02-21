<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;

use App\Models\Jira\Search;
use App\Models\Jira\Issue;
use App\Models\Jira\Enum\Status;

use App\Models\Slack\Messenger;

use App\Models\User;

use App\Models\Status as ModelStatus;
use App\Models\Directive;
use App\Models\Activity;

class JiraTicketsChangedToInReview extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'jira:passed-tickets
                            {--S|since-time=15m : Elapsed time interval to look for tickets}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Will ping Jira and look for tickets in "In Review" status which do not have pull requests';

    protected $search;


    /**
     * @var Carbon
     */
    protected $time;


    /**
     * @var Messenger
     */
    protected $messenger;


    protected $targetStatus;


    /**
     * @var \Illuminate\Support\Collection
     */
    protected $directives;

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
        $this->targetStatus = ModelStatus::whereService('Jira')->whereName('Passed Testing')->first();
        $this->directives = Directive::whereStatusId($this->targetStatus->id)->get();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $givenMinutes = $this->option('since-time');

        if($givenMinutes){
            $this->time->subMinutes($givenMinutes);
        }else{
            $this->time->subMinute();
        }

        $users = User::has('jiraAccount')->get();

        foreach ($users as $user) {
            $issues = $this->search->getChangedIssues([Status::IN_TESTING], [Status::IN_REVIEW], $user, $this->time);

            foreach ($issues as $issue) {

                $createPullDirective = $this->directives->whereLoose('id', 3)->first();

                $statusChangeTime = $issue->getLatestStatusChangeTime(Status::IN_TESTING, Status::IN_REVIEW);

                $activityData = [
                    'user_id' => $user->id,
                    'service' => 'Jira',
                    'service_id' => $issue->getId(),
                    'status_id' => $this->targetStatus->id,
                    'response_required' => true
                ];

                $hash = array_merge($activityData, ['status_change_time' => $statusChangeTime]);

                $activityData['hash'] = md5(implode('|', array_dot($hash)));

                $activity = Activity::firstOrNew($activityData);

                if (!$activity->exists) {
                    $activity->save();

                    $this->messenger->attach([
                        'title'         => "[{$issue->getId()}] {$issue->getSummary()}",
                        'title_link'    => $issue->getLink(),
                        'fallback'      => "[{$issue->getId()}]\nCurrent Status: {$issue->getStatus()} - {$this->targetStatus->name}",
                        'callback_id'   => $activity->id,
                        'text'          => "*{$issue->getStatus()} - {$this->targetStatus->name}*\nHow would you like to proceed?",
                            'actions'   => [
                                [
                                    'name'      => 'main',
                                    'text'      => $createPullDirective->action,
                                    'type'      => 'button',
                                    'value'     => Directive::COMMAND_PREFIX . "$createPullDirective->command {$issue->getId()}",
                                    'style'     => 'primary'
                                ],
                                [
                                    'name'      => 'ignore',
                                    'text'      => 'Ignore For Now',
                                    'type'      => 'button',
                                    'value'     => '',
                                    'style'     => 'default'
                                ],
                                /*[
                                    'name'      => 'more_options',
                                    'text'      => 'Show Me Options',
                                    'type'      => 'button',
                                    'value'     => 'options',
                                    'style'     => 'default'
                                ]*/
                            ],
                    ]);
                }

                $this->messenger->to($user->email)->send();
            }
        }
    }
}
