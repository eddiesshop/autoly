<?php

namespace App\Listeners;

use App\Events\BranchCreated;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection;

use App\Events\PassedTesting;
use App\Events\ProgressDone;
use App\Events\UserResponse;
use App\Exceptions\JiraIssueNotFoundException;
use App\Exceptions\SlackableException;
use App\Models\Activity;
use App\Models\Directive;
use App\Models\Jira\Issue;
use App\Models\Slack\Messenger;

class DirectiveDispatcher{

    /**
     * @var Messenger
     */
    private $messenger;

    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct()
    {
        //
        $this->messenger = new Messenger();
    }

    /**
     * Handle the event.
     *
     * @param  UserResponse  $event
     * @return void
     * @throws SlackableException
     */
    public function handle(UserResponse $event)
    {
        //
        /**
         * I think eventually it would be better to find/create the activity within the events themselves as opposed to doing it here.
         */

        $directive = $event->getDirectives()->first();
        $mainCommandKey = array_search(Directive::COMMAND_PREFIX . $directive->command, $event->getCommands());
        $serviceId = isset($event->getCommands()[$mainCommandKey + 1]) ? $event->getCommands()[$mainCommandKey + 1] : null;

        switch ($directive->status_id){
            case 1: //Progress Done
                /**
                 * The directive will come in with Status for Progress Done
                 * This Status may or may not have an Activity tied to it.
                 * Will do first or create for Activity.
                 */

                event(new ProgressDone($event->getUser(), new Issue($serviceId), null, null, $event->getResponse(), $event->getDirectives()));

                return null;
            case 4: //Passed Testing
                /**
                 * For this Status ID I know that the Jira Ticket ID is supposed to come after the main command.
                 * Also assuming that the command will be written in the expected order.
                 **/

                if(is_null($serviceId)){

                    event(new PassedTesting($event->getUser(), null, $event->getResponse(), $event->getDirectives()));
                }else{

                    try{

                        event(new PassedTesting($event->getUser(), new Issue($serviceId), $event->getResponse(), $event->getDirectives()));
                    }catch (\Exception $e){

                        throw new JiraIssueNotFoundException($event->getResponse()->getRespondTo(), $serviceId, $directive, 1);
                    }
                }

                return null;
            case 8:

            	event(new BranchCreated($event->getUser(), null, null, $event->getResponse(), $event->getDirectives()));
        }

        $this->messenger->to($event->getUser())->send("Sorry I can't quite help you at the moment");
    }
}
