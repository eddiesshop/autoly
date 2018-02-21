<?php

namespace App\Console\Commands;

use App\Models\Directive;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Console\Command;

use App\Models\Activity;
use App\Models\Status as ModelStatus;
use App\Models\GitHub\GitHub;
use App\Models\GitHub\Repository;
use App\Models\Jira\Issue;
use App\Models\Jira\Enum\Status;
use App\Models\Slack\Messenger;

use SSH;

class GitHubMergedPullRequests extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'github:merged-pulls';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command to check if Pull Requests created within the last day have been merged, and if so, refresh affected environments';


    private $createStatus;

    private $mergedStatus;

    private $now;

    private $git;

    private $messenger;

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
        $this->createStatus = ModelStatus::whereService('GitHub')->whereName('Pull Request Created')->first();
        $this->mergedStatus = ModelStatus::whereService('GitHub')->whereName('Pull Request Merged')->first();
        $this->now = Carbon::now()->subDay();
        $this->git = new GitHub();
        $this->messenger = new Messenger();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        //
        $pullRequests = Activity::whereService('GitHub')
            ->whereStatusId($this->createStatus->id)
            ->where('created_at', '>=', $this->now->toDateTimeString())
            ->get();

        $mergedPullRequests = Activity::whereService('GitHub')
            ->whereStatusId($this->mergedStatus->id)
            ->whereIn('service_id', $pullRequests->pluck('service_id'))
            ->get();

        $unmergedPullRequests = Activity::whereService('GitHub')
            ->whereStatusId($this->createStatus->id)
            ->where('created_at', '>=', $this->now->toDateTimeString())
            ->whereNotIn('service_id', $mergedPullRequests->pluck('service_id'))
            ->with([
                'user',
                'data'
            ])
            ->get();

        foreach ($unmergedPullRequests as $unmergedActivity){

            $createPullActivity = Activity::find($unmergedActivity->service_id);
            $issue = new Issue($createPullActivity->service_id);

            $mergedRepos = [];
            foreach ($unmergedActivity->data()->whereDirectiveId(Directive::whereAction('Log Created Pull Request')->first()->id)->get() as $data){

                $repository = Repository::whereGithubId($data->response['head']['repo']['id'])->first();

                $pullNumber = $data->response['number'];

                if(!$this->git->isPullMerged($unmergedActivity->user, $repository, $pullNumber)) break;

                $repository->environmentCommands = $repository->environmentCommands()->whereStatusId($this->mergedStatus->id)->orderBy('order')->get();
                $mergedRepos[] = $repository;
            }

            //Only proceed when all pulls have been merged
            if(count($mergedRepos) == $unmergedActivity->data()->whereDirectiveId(Directive::whereAction('Log Created Pull Request')->first()->id)->get()->count()){
                //Run Repo Specific Commands
                //Add Release Label to Jira
                //Notify QA
                //Create New Activity with Merged Status

                foreach ($mergedRepos as $repository) {

                    $repository->environmentCommands->groupBy('type')->each(function ($commandsByEnvironmentType, $type) {
                        switch ($type) {
                            case 'single' :
                                $commandsByEnvironmentType->groupBy('environment')->each(function ($commandsByEnvironment, $environment) {
                                    SSH::into($environment)->run($commandsByEnvironment->pluck('env_var')->toArray());
                                });
                                break;
                            case 'group' :
                                $commandsByEnvironmentType->groupBy('environment')->each(function ($commandsByGroupOfEnvironment, $environments) {
                                    SSH::group($environments)->run($commandsByGroupOfEnvironment->pluck('env_var')->toArray());
                                });
                        }

                    });
                }

                $issue->addLabel($unmergedActivity->data()->whereDirectiveId(Directive::whereAction('Log Created Pull Request')->first()->id)->first()->response['base']['ref']);

                $activityAttrs = [
                    'user_id' => $unmergedActivity->user->id,
                    'service' => 'GitHub',
                    'service_id' => $createPullActivity->id,
                    'status_id' => $this->mergedStatus->id,
                    'response_required' => 0
                ];

                $hash = array_merge($activityAttrs, ['status_change_time' => $issue->getLatestStatusChangeTime(Status::IN_TESTING, Status::IN_REVIEW)]);

                $activityAttrs['hash'] = md5(implode('|', array_dot($hash)));

                Activity::firstOrCreate($activityAttrs);

                //TODO change this to run query on DB for users for a specific Organization with User Type = QA
                $users = User::whereIn('id', [explode(',', getenv('QA_USER_IDS'))])->with('slackAccounts')->get();
                $users->push($unmergedActivity->user);

                $attachment = [
                    'title' => "[{$issue->getId()}] {$issue->getSummary()}",
                    'title_link' => $issue->getLink(),
                    'fallback' => "[{$issue->getId()}] {$this->mergedStatus->name}",
                    'text' => "*{$issue->getStatus()} - {$this->mergedStatus->name}*\nThis item is now ready for regression testing!",
                ];

                foreach ($users as $user){
                    $this->messenger->attach($attachment)->to($user)->send();
                }
            }
        }
    }
}
