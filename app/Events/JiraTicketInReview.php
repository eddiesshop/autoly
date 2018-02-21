<?php

namespace App\Events;

use App\Events\Event;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

class JiraTicketInReview extends Event
{
    use SerializesModels;

    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct()
    {
        //
	//Should make a call to Jira
	//Find the current release - based on the filter Ross created
	//Check the tickets tied to Eddie (Need to figure out a way to make this by user)
	//If the ticket is in Review
		//TRUE:
			//Send a slack message notifying me that the ticket is in Reivew Status
			//Ask if you would like to proceed with creating Pull Request
				//TRUE
					//Hit github and find the working branch
					//Create pull request for versionXX_release
					//If successful - add versionXX_release label to Jira Ticket else send slack message saying this failed.
				//Do Nothing
		//Go to Next Ticket
			
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
