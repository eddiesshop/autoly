<?php

namespace App\Listeners;

use App\Events\PassedTesting;
use App\Exceptions\SlackableException;
use App\Models\Activity;
use App\Models\ActivityData;
use App\Models\Directive;
use App\Models\GitHub\GitHub;
use App\Models\GitHub\Repository;
use App\Models\Status as StatusModel;
use App\Models\Jira\Enum\Status;
use App\Models\Response;
use Carbon\Carbon;
use GuzzleHttp\Exception\ClientException;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;

use App\Models\Slack\Messenger;
use Illuminate\Support\Collection;

class CreatePullRequest
{

    private $messenger;

    private $status;

    private $mainDirective;

    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct()
    {
        //
        $this->messenger = new Messenger();
        $this->status = StatusModel::whereService('GitHub')->whereName('Pull Request Created')->first();
        $this->mainDirective = Directive::whereMain(true)->whereCommand('create-pull')->first();
    }

    /**
     * Handle the event.
     *
     * @param  PassedTesting  $event
     * @return void
     */
    public function handle(PassedTesting $event){
        //Check the commands to see if create-pull request is there
        //Check if Review Branch was given
        //Check if Release Branch was given
        //Call GitHub to create pull request
        //Update Jira Metadata
        //Update Response Table

        if($event->getDirectives()->first()->id != $this->mainDirective->id) return null;

        $user = $event->getUser();

        $commands = $event->getResponse()->response;

        $directives = $event->getDirectives();

        //First Directive: Create Pull Request
        $pullRequest = $directives->shift();

        $key = array_search('='.$pullRequest->command, $commands);

        //Issue should be in PassedTesting Event
        $issue = $event->getIssue();

        //Second Directive: Review Branches
        $reviewBranch = $directives->shift();
        $reviewBranchKey = array_search('--'.$reviewBranch->command, $commands);

        //Third Directive: Release Branch
        $releaseBranch = $directives->shift();
        $releaseBranchKey = array_search('--'.$releaseBranch->command, $commands);


        $git = new GitHub();
        $reposAndBranchesForPull = [];
        /**
         * reposAndBranchesForPull = [
         *      0 => [
         *          Repository,
         *          Working/Feature Branch
         *      ]
         * ]
         */

        /*
         * If explicit Release Branch is given for a ticket, need to find the branch for the given repo.
         * There could be a case where they don't give an explicit working branch, in that case, the code
         * may find the branch in multiple repos. This will have more than one entry in $reposAndBranchesForPull.
         * So the release branch needs to pair up with the working branch based on the repo. Should
         * probably remove the extra repos in $reposAndBranchesForPull afterwards also.*/

        if($reviewBranchKey !== false){
            //Obtain any and all review branches that were entered in the command
            $branchPaths = $this->sliceCommandOptionParams($commands, $reviewBranchKey, $releaseBranchKey);

            //TODO would need to get the Organization that the user belongs to
            foreach ($branchPaths as $branchPath){
                $reposAndBranchesForPull[] = $this->findBranchExplicitly($branchPath);
            }
        }else{
            foreach ($git->getRepositories($user) as $repo){
                var_dump("Repo Name: ".$repo->getName());
                $branches = $repo->findBranch($issue->getId(), false, true);

                //Either create an event or command to refresh branches every X amount of time
                //Or if no branhces found send User a msg asking for explicit
                /*if($branches->isEmpty()){
                    $afterNow = Carbon::now();
                    $repo->getBranches(true, $afterNow);
                    $branches = $repo->findBranch($issue->getId());
                }*/

                if(!$branches->isEmpty()){
                    $reposAndBranchesForPull[] = [$repo, $branches->first()];
                }
            }
        }


        //If an explicit command is provided we will attempt to find and use the branch provided by the user
        //Otherwise, the application will try to find the release branches based on the working branches which were found above
        if($releaseBranchKey !== false){

            $branchPaths = $this->sliceCommandOptionParams($commands, $releaseBranchKey, $reviewBranchKey);

            foreach ($branchPaths as $branchPath){
                $found = $this->findBranchExplicitly($branchPath);

                //Need to match up Release Branch with the specific Working Branch Repository
                foreach ($reposAndBranchesForPull as $arrayKey => $repos){
                    if(!empty($found) && $repos[0]->github_id == $found[0]->github_id){
                        //Add the found branch to the end of the Array structure in $reposAndBranchesForPull
                        $reposAndBranchesForPull[$arrayKey][] = $found[1];
                        break;
                    }
                }
            }
        }else{
            foreach ($reposAndBranchesForPull as $key => $repos){
                $relBranch = $repos[0]->findBranch('master', true)->first();

                $reposAndBranchesForPull[$key][] = $relBranch;
            }
        }

        /**
         * reposAndPulls = [
         *      0 => [
         *          Repository,
         *          Working/Feature Branch,
         *          Release Branch
         *      ]
         * ]
         */

        $responses = [];

        foreach ($reposAndBranchesForPull as $repoAndBranch){
            if(count($repoAndBranch) != 3) continue;

            //TODO Think I need to surround this in a try catch
            try {
                $response = $git->createPullRequest($user, $repoAndBranch[0] /*Repo*/, $repoAndBranch[1] /*Working Branch*/, $repoAndBranch[2] /*Release Branch*/, $issue->getLink());
            }catch(ClientException $e){

                if($e->getResponse()->getStatusCode() == 422){
                    $message = '(' . ucfirst($repoAndBranch[0]->getName()) . ') ';
                    $errors = json_decode($e->getResponse()->getBody()->getContents())->errors;

                    foreach ($errors as $error){
                        $message .= $error->message . "\n";
                    }

                    throw new SlackableException($event->getResponse()->getRespondTo(), $message);
                }

                throw $e;
            }
            $activityAttrs = [
                'user_id' => $event->getUser()->id,
                'service' => 'GitHub',
//                'service_id' => "{$issue->getId()}:{$repoAndBranch[0]->github_id}:{$response['number']}",
                'service_id' => $event->getActivity()->id,
                'status_id' => $this->status->id,
                'response_required' => 0
            ];

            $hash = array_merge($activityAttrs, ['status_change_time' => $event->getIssue()->getLatestStatusChangeTime(Status::IN_TESTING, Status::IN_REVIEW)]);

            $activityAttrs['hash'] = md5(implode('|', array_dot($hash)));

            $activity = Activity::firstOrCreate($activityAttrs);

            $activityData = new ActivityData([
                'directive_id' => $event->getDirectives()->where('action', 'Log Created Pull Request')->first()->id,
                'response' => $response
            ]);

            $activity->data()->save($activityData);

            $responses[] = ucfirst($repoAndBranch[0]->getName()).": <{$response['html_url']}|".$repoAndBranch[2]->getName()." &lt;- ".$repoAndBranch[1]->getName().">";
        }

        $issue->makeComment(implode("\n", $responses));

        $this->messenger->attach([
            'title' => "[{$issue->getId()}] {$issue->getSummary()}",
            'title_link' => $issue->getLink(),
            'fallback' => "[{$issue->getId()}] Current Status: {$issue->getStatus()} - {$this->status->name}",
            'text' => "*{$issue->getStatus()} - {$this->status->name}*\n" . implode("\n", $responses),
        ]);

        $this->messenger->to($event->getResponse()->getRespondTo())->send();

        $this->messenger->attach([
            'title' => "[{$issue->getId()}] {$issue->getSummary()}",
            'title_link' => $issue->getLink(),
            'fallback' => "[{$issue->getId()}] Current Status: {$issue->getStatus()} - {$this->status->name}",
            'text' => "*{$issue->getStatus()} - {$this->status->name}*\n" . implode("\n", $responses),
        ]);

        $this->messenger->to(getenv('PULL_REQUEST_NOTIFICATION_CHANNEL'))->send();
    }

    /**
     * @param array $commands
     * @param integer $targetKey
     * @param integer|boolean $terminatingKey
     * @return array
     */
    private function sliceCommandOptionParams(array $commands, $targetKey, $terminatingKey = false){
        $start = null;
        $length = null;

        $start = $targetKey + 1;

        if($terminatingKey !== false && $targetKey < $terminatingKey){
            $length = $terminatingKey - $start;
        }

        return !is_null($length) ? array_slice($commands, $start, $length) : array_slice($commands, $start);
    }

    private function findBranchExplicitly($fullBranchName){
        $repoAndPull = [];

        $explodedGivenReviewBranch = explode('/', $fullBranchName);

        //TODO need to ask for User Organization in this function
        $repo = new Repository(getenv('GIT_ORG').'/'.array_shift($explodedGivenReviewBranch));

        $branches = $repo->findBranch(implode('/', $explodedGivenReviewBranch), true);

        if($branches->isEmpty()){
            $afterNow = Carbon::now();
            $repo->getBranches(true, $afterNow);
            $branches = $repo->findBranch(implode('/', $explodedGivenReviewBranch), true);
        }

        if(!$branches->isEmpty()){
            $repoAndPull = [$repo, $branches->first()];
        }

        return $repoAndPull;
    }
}
