<?php

namespace App\Events;

use App\Contracts\EventInterface;
use App\Events\Event;
use App\Models\Activity;
use App\Models\Directive;
use App\Models\Jira\Issue;
use App\Models\Response;
use App\Models\Status;
use App\Traits\EventTrait;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Support\Collection;

use App\Models\User;

class PassedTesting extends Event implements EventInterface {
    use SerializesModels, EventTrait;

    private $user;

    private $activity;

    /**
     * @var Response
     */
    private $response;

    private $directives;

    private $issue;
    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct(User $user, Issue $issue = null, Response $response = null, Collection $directives = null){
        //
        $this->user = $user;
        //TODO build response if passed in $response is null
        //TODO need to create artificial response like in ProgressDone
        $this->response = $response;

        $this->directives = $directives;

        $this->issue = $issue;
        //Check if Issue is Null
        //If is not null, get Activity
        if(!is_null($issue)){

            $this->activity = Activity::whereService('Jira')
                ->whereServiceId($issue->getId())
                ->whereUserId($user->id)
                ->whereResponseRequired(1)
                ->orderBy('created_at', 'DESC')
                ->first();

            if(!is_null($this->response)){

                $this->response->activity()->associate($this->activity);
                $this->response->save();
            }

            if(!is_null($directives)){

                //Going to assume that the directives we want are for create pull request.
                $passedTesting = Status::whereService('Jira')->whereName('Passed Testing')->first();

                $createPullDirective = Directive::whereStatusId($passedTesting->id)->whereCommand('create-pull')->whereMain(true)->first();

                $otherMain = Directive::whereMain(true)->whereStatusId($passedTesting->id)->where('order', '>', $createPullDirective->order)->first();

                $directiveQuery = Directive::whereStatusId($passedTesting->id)
                    ->where('order', '>=', $createPullDirective->order)
                    ->orderBy('order');

                if($otherMain) $directiveQuery->where('order', '<', $otherMain->order);

                $this->directives = $directiveQuery->get();
            }
        }
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
}
