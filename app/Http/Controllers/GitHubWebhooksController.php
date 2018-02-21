<?php namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Request;

use App\Http\Requests;

use App\Exceptions\JiraIssueNotFoundException;
use App\Models\Activity;
use App\Models\ActivityData;
use App\Models\Directive;
use App\Models\GitHub\Branch;
use App\Models\GitHub\PullRequest;
use App\Models\GitHub\Repository;
use App\Models\Jira\Enum\Status as JiraStatus;
use App\Models\Jira\Issue;
use App\Models\Jira\Jira;
use App\Models\Jira\Search;
use App\Models\Slack\Messenger;
use App\Models\Status;
use App\Models\User;

use Illuminate\Support\Collection;
use Log;

class GitHubWebhooksController extends Controller{

	protected $passedTestingStatus;
	protected $pullRequestCreatedStatus;
	protected $pullRequestMergedStatus;

    protected $pullRequestKeys = [
        'action',
        'number',
        'pull_request'
    ];

    protected $branchCreateKeys = [
        'ref_type',
        'ref',
        'master_branch',
        'description'
    ];

    public function handle(Request $request){

        Log::info("We've Been Hit");
        $keys = array_keys($request->all());
        Log::info(['Request Keys: ' => $keys]);

        if(empty(array_diff_key(array_flip($this->pullRequestKeys), array_flip($keys)))){ //Pull Request

            Log::info('Identified as PR');
            $this->passedTestingStatus = Status::forJira('Passed Testing')->first();
            $this->pullRequestCreatedStatus = Status::forGitHub('Pull Request Created')->first();
            $this->pullRequestMergedStatus = Status::forGitHub('Pull Request Merged')->first();
            return $this->handlePullRequest($request);
        }else if(empty(array_diff_key(array_flip($this->branchCreateKeys), array_flip($keys)))){//Branch Created

            if($request->ref_type != 'branch') return null;

            Log::info('Identified as Branch Created');
            return $this->handleBranchCreate($request);
        }
    }
    //

    public function handlePullRequest(Request $request){

        Log::info('Handling PR');
        $user = User::whereHas('githubAccount', function($q) use ($request){
            $q->whereUserName($request->pull_request['user']['login']);
        })->first();

        $branchName = last(explode('/', $request->pull_request['head']['ref']));

        $passedTestingActivity = Activity::jira($branchName)
	                                ->whereStatusId($this->passedTestingStatus->id)
	                                ->orderBy('created_at', 'DESC')
	                                ->first();

        Log::info([
            'PR Action: ' => isset($request->pull_request['action']) ? $request->pull_request['action'] : false,
            'User: ' => isset($request->pull_request['user']) ? $request->pull_request['user'] : false,
            'Branch Name: ' => $branchName,
            'Passed Activity Found? ' => $passedTestingActivity ? true : false
        ]);

        $now = Carbon::now();
        $now->setToStringFormat('Y_m_d_His');
        file_put_contents(storage_path("PR_request_{$now->__toString()}.json"), (new Collection($request->all()))->toJson());

        switch ($request->action){

            case 'opened':

                Log:info('Opened Case Matched');

                if($passedTestingActivity) {

                    Log::info(['Processed with Autoly? ' => !$passedTestingActivity->responses->isEmpty()]);
                    //Only need to create activity if the user did not respond to Autoly msg and is manually creating PR
                    if(!$passedTestingActivity->responses->isEmpty()) {

                        return null;
                    }

                    $createdPRActivity = Activity::gitHub($passedTestingActivity->id)
	                                        ->whereStatusId($this->pullRequestCreatedStatus->id)
	                                        ->where('created_at', '>=', $passedTestingActivity->created_at)
	                                        ->first();

                    if(!$createdPRActivity) $createdPRActivity = Activity::create([
                        'user_id' => $user->id,
                        'service' => 'GitHub',
                        'service_id' => $passedTestingActivity->id,
                        'status_id' => Status::forGitHub('Pull Request Created')->first()->id
                    ]);
                }else{

                	$createdPRActivity = Activity::whereService('GitHub-Webhook-PR')
		                                            ->whereServiceId($request->pull_request['head']['ref'])
		                                            ->whereStatusId($this->pullRequestCreatedStatus->id)
		                                            ->orderBy('created_at', 'DESC')
		                                            ->first();

                    if(!$createdPRActivity) $createdPRActivity = Activity::firstOrCreate([
                        'user_id' => $user->id,
                        'service' => 'GitHub-Webhook-PR',
                        'service_id' => $request->pull_request['head']['ref'],
                        'status_id' => $this->pullRequestCreatedStatus->id
                    ]);
                }

                $activityData = new ActivityData([
                    'directive_id' => Directive::whereAction('Log Created Pull Request')->first()->id,
                    'response' => $request->pull_request
                ]);

                $createdPRActivity->data()->save($activityData);

                try{

                    $issue = new Issue($branchName);

                    $pull = new PullRequest($request->pull_request);

                    $issue->makeComment("{$pull->getRepository()->name}: {$pull->get_links()['html']['href']}");
                    
                    $this->notifySlackChannelToPullRequest($createdPRActivity, $pull, $user, $issue);
                }catch (JiraIssueNotFoundException $e){

                    $this->notifySlackChannelToPullRequest($createdPRActivity, new PullRequest($request->pull_request), $user);
                }

                break;

            case 'closed':

                if(!$request->pull_request['merged']) break; //If the pull request was closed but not merged, we can ignore.

                //Need to find Create PR activity in DB
                //Count the number of branches involved.
                //Find the corresponding Open PR request
                //Mark as closed
                //Once the closed matches the number of opens, All PRs have officially been merged. Ticket is ready to be tested.

				$activityQuery = Activity::whereStatusId($this->pullRequestCreatedStatus->id);

                if($passedTestingActivity){

                	Branch::whereJiraKey($passedTestingActivity->service_id)
	                            ->whereMerged(false)
	                            ->whereRepositoryId($request->repository['id'])
	                            ->update([
	                                'merged' => 1
	                            ]);

                	$activityQuery->gitHub($passedTestingActivity->id)
		                ->where('created_at', '>=', $passedTestingActivity->created_at);
                }else{

                	Branch::whereName($request->pull_request['head']['ref'])
		                        ->whereMerged(false)
		                        ->whereRepositoryId($request->repository['id'])
		                        ->update([
		                        	'merged' => 1
		                        ]);

                	$activityQuery->whereService('GitHub-Webhook-PR')
		                ->whereServiceId($request->pull_request['head']['ref'])
		                ->orderBy('created_at', 'DESC');
                }

                $createdPRActivity = $activityQuery->first();

	            if(!$createdPRActivity){

                	//I want to know about this situation
		            Log::info(['Could not find Created PR for Closed PR Request' => ['WebHook' => $request->all()]]);
		            throw new \Exception('Could not find Created PR for Closed PR Request');
	            }

	            $closedPRActivity = Activity::gitHub($createdPRActivity->service_id)
		                                ->whereStatusId($this->pullRequestMergedStatus->id)
		                                ->orderBy('created_at', 'DESC')
		                                ->first();

	            if(!$closedPRActivity) $closedPRActivity = Activity::create([
	            	'user_id' => $user->id,
		            'service' => 'GitHub',
		            'service_id' => $createdPRActivity->id,
		            'status_id' => $this->pullRequestMergedStatus->id,
	            ]);

	            $activityData = new ActivityData([
		            'directive_id' => Directive::whereAction('Log Closed Pull Request')->first()->id,
		            'response' => $request->pull_request
	            ]);

	            $closedPRActivity->data()->save($activityData);

	            if($createdPRActivity->data->count() == $closedPRActivity->data->count()){

	            	if($passedTestingActivity){

			            $issue = new Issue($passedTestingActivity->service_id);
		            }else{

	            		$exploded = explode('/', $createdPRActivity->service_id);

			            preg_match('/^[a-zA-Z]*[-,_][0-9]*/', last($exploded), $matches);

			            if(!empty($matches)){

				            $issue = new Issue(head($matches));

				            $issue->changeStatus(JiraStatus::DEPLOYED);
			            }
		            }
	            }

                break;
        }

    }

    private function notifySlackChannelToPullRequest(Activity $createdPRActivity, PullRequest $pullRequest, User $user, Issue $issue = null){

        $passedStatus = Status::whereService('Jira')->whereName('Passed Testing')->first();

        $notifySlackChannelDirective = Directive::whereAction('Slack Channel')->whereStatusId($passedStatus->id)->first();

        $slackMsgData = $createdPRActivity->data()->whereDirectiveId($notifySlackChannelDirective->id)->orderBy('created_at', 'DESC')->first();

        $slack = new Messenger();

        if(!$slackMsgData){

            $text = [
                !is_null($issue) ? "*{$issue->getStatus()} - {$createdPRActivity->status->name}*" : "*{$createdPRActivity->status->name}*",
                ucfirst($pullRequest->getRepository()->name) . ": <{$pullRequest->getHtmlUrl()}|{$pullRequest->getBase()['ref']} &lt;- {$pullRequest->getHead()['ref']}>"
            ];

            $ts = Carbon::parse($pullRequest->getCreatedAt());
            $ts->setToStringFormat('U');

            $attachment = [
                'text' => implode("\n", $text),
                'fields' => [
                    [
                        'title' => 'Requires Migration',
                        'value' => $pullRequest->hasMigrations() ? "Yes" : "No",
                        'short' => true,
                    ]
                ],
                'footer' => "{$createdPRActivity->status->name} by {$user->name}",
                'ts' => $ts->__toString()
            ];

            if(!is_null($issue)){

                $attachment['title']= "[{$issue->getId()}] {$issue->getSummary()}";
                $attachment['title_link'] = $issue->getLink();
                $attachment['fallback'] = "[{$issue->getId()}] Current Status: {$issue->getStatus()} - {$createdPRActivity->status->name}";

                $attachment['fields'][] = [
                    'title' => 'Issue Priority',
                    'value' => $issue->getPriority(),
                    'short' => true,
                ];
            }else{
                $attachment['fallback'] = "{$user->name} {$createdPRActivity->status->name}";
            }

            $slack->attach($attachment);

            $response = $slack->to(getenv('PULL_REQUEST_NOTIFICATION_CHANNEL'))->send();

            $activityData = new ActivityData([
                'directive_id' => $notifySlackChannelDirective->id,
                'response' => $response
            ]);

            $createdPRActivity->data()->save($activityData);
        }else{

            $originalTimestamp = $slackMsgData->response['ts'];
            $originalChannel = $slackMsgData->response['channel'];

            $attachment = $slackMsgData->response['message']['attachments'][0];

            $text =  explode("\n", $attachment['text']);
            $count = count($text);
            $replaced = false;

            for($i = $count - 1; $i > 0; $i--){

                if(starts_with($text[$i], ucfirst($pullRequest->getRepository()->name))){

                    $text[$i] = ucfirst($pullRequest->getRepository()->name) . ": <{$pullRequest->getHtmlUrl()}|{$pullRequest->getBase()['ref']} &lt;- {$pullRequest->getHead()['ref']}>";
                    $replaced = true;
                }
            }

	        if(!$replaced) $text[] = ucfirst($pullRequest->getRepository()->name) . ": <{$pullRequest->getHtmlUrl()}|{$pullRequest->getBase()['ref']} &lt;- {$pullRequest->getHead()['ref']}>";

            $attachment['text'] = implode("\n", $text);

            $migrationField = $attachment['fields'][0];

            if($migrationField['value'] != "Yes" && $pullRequest->hasMigrations()){
                $migrationField['value'] = "Yes";
                $attachment['fields'][0] = $migrationField;
            }

            $slack->attach($attachment);
            $response = $slack->to($originalChannel)->send(null, $originalTimestamp);

            $activityData = new ActivityData([
                'directive_id' => $notifySlackChannelDirective->id,
                'response' => $response
            ]);

            $createdPRActivity->data()->save($activityData);
        }
    }

    public function handleBranchCreate(Request $request){

    	Log::info('Create Branch Request Received');

	    $user = User::whereHas('gitHubAccount', function($q) use ($request){

		    $q->whereUserName($request->sender['login']);
	    })->first();

        $exploded = explode('/', $request->ref);

        //assuming branch name to be last item in exploded array
        preg_match('/^[a-zA-Z]*[-,_][0-9]*/', last($exploded), $matches);

        if(!empty($matches)){

        	Log::info(['Matches Found' => $matches]);
            try{

            	$issueId = str_replace('_', '-', head($matches));

                $issue = new Issue($issueId);

                $attributes = [
                    'name' => $request->ref,
                    'repository_id' => $request->repository['id'],
                    'jira_key' => $issue->getId(),
                ];

                $branch = new Branch($attributes);
				$branch->api_url = strtr($request->repository['branches_url'], ['{/branch}' => '/' . $request->ref]);
				$branch->html_url = "{$request->repository['html_url']}/tree/$request->ref";
				$branch->save();
                Log::info(["Branch Created" => $branch->toArray()]);
            }catch (JiraIssueNotFoundException $e){

	            $this->askSlackUserForBranchConfirmation($user, $request->repository['full_name'] . '/' . $request->ref);
	            Log::info(['Issue Not Found' => $matches[0]]);
            }
        }else{

	        $this->askSlackUserForBranchConfirmation($user, $request->repository['full_name'] . '/' . $request->ref);
	        Log::info(['No Matches' => last($exploded)]);
        }
    }

	public function askSlackUserForBranchConfirmation(User $user, $branchName){

		//Get the latest issues for user
		//Order by Newest to oldest.
		//Send slack.

		$exploded = explode('/', $branchName);
		array_shift($exploded);//Remove Owner
		$displayBranchName = implode('/', $exploded);

		$attachment = [
			'text' => "Would you please tell me which Jira Issue this branch (`$displayBranchName`) is related to?",
			'actions' => [
				[
					'name' => 'issues_list',
					'text' => 'Select Jira Issue...',
					'type' => 'select',
				],
				[
					'name'  => 'ignore',
					'text'  => 'Leave Me Alone',
					'type'  => 'button',
					'value' => '',
					'style' => 'default'
				]
			]
		];

		$search = new Search();

		$options = [];

		$issues = $search->getIssues([JiraStatus::SELECTED_FOR_DEVELOPMENT, JiraStatus::TO_DO, JiraStatus::IN_PROGRESS], $user)
			->sortByDesc(function($issue){

				return $issue->getHistory()->first()['created'];
			});

		foreach($issues as $issue){

			$options[] = [
				'text' => $issue->getId(),
				'value' => "=link $branchName -to {$issue->getId()}"
			];
		}

		$attachment['actions'][0]['options'] = $options;

		$slack = new Messenger();

		$slack->attach($attachment)->to($user)->send();
	}
}
