<?php

namespace App\Listeners;

use App\Models\Directive;
use App\Models\Slack\Messenger;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;

use App\Events\BranchCreated;

class LinkBranchToIssue
{

	/**
	 * @var Directive
	 */
	private $mainDirective;

	/**
	 * @var Messenger;
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
	    $this->mainDirective = Directive::whereMain(true)->whereCommand('link')->first();
	    $this->messenger = new Messenger();
    }

    /**
     * Handle the event.
     *
     * @param  BranchCreated  $event
     * @return void
     */
    public function handle(BranchCreated $event)
    {
        //

	    if($event->getDirectives()->first()->id != $this->mainDirective->id) return null;

	    $event->getBranches()->each(function($branch) use ($event){
            
            $branch->update([
                'jira_key' => $event->getIssue()->getId()
            ]);
        });
    }
}
