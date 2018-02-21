<?php

use Illuminate\Database\Seeder;

use App\Models\Status;
use App\Models\Directive;

class DirectivesTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        //
        $done = Status::whereService('Jira')->whereName('Progress Done')->first();
        $failed = Status::whereService('Jira')->whereName('Failed Testing')->first();
        $ready = Status::whereService('Jira')->whereName('Ready For Testing')->first();
        $passed = Status::whereService('Jira')->whereName('Passed Testing')->first();
        $review = Status::whereService('Jira')->whereName('Ready For Review')->first();

        $pullCreated = Status::whereService('GitHub')->whereName('Pull Request Created')->first();
        $pullMerged = Status::whereService('GitHub')->whereName('Pull Request Merged')->first();
        $branchCreated = Status::whereService('GitHub')->whereName('Branch Created')->first();
        $branchDeleted = Status::whereService('GitHub')->whereName('Branch Deleted')->first();

        $branchLinkedToIssue = Status::whereService('Autoly')->whereName('GitHub Branch Associated to Jira Issue')->first();

        Directive::firstOrCreate([
            'status_id'     => $failed->id,
            'action'        => 'Slack Assignee',
            'description'   => 'Send the last comment made to the Jira ticket to the Assignee',
            'order'         => 1,
            'main'          => false,
            'required'      => false,
            'immutable'     => true,
            'nixable'       => false
        ]);

        Directive::firstOrCreate([
            'status_id'     => $passed->id,
            'action'        => 'Slack Assignee',
            'description'   => 'Send all Issues which are awaiting a pull request',
            'order'         => 1,
            'main'          => false,
            'required'      => false,
            'immutable'     => true,
            'nixable'       => false
        ]);

        Directive::firstOrCreate([
            'status_id'     => $passed->id,
            'action'        => 'Create Pull Request',
            'command'       => 'create-pull',
            'example_param' => 'IP-6',
            'description'   => 'For the given Jira Issue in question, create a pull request',
            'order'         => 2,
            'main'          => true,
            'required'      => true,
            'immutable'     => true,
            'nixable'       => true
        ]);

        Directive::firstOrCreate([
            'status_id'     => $passed->id,
            'action'        => 'Find Review Branch',
            'command'       => 'review-branch',
            'example_param' => 'platform/feature/IP-6',
            'description'   => 'For the given Jira Issue in question, find the working branch needed for pull request',
            'order'         => 3,
            'main'          => false,
            'required'      => false,
            'immutable'     => false,
            'nixable'       => true
        ]);

        Directive::firstOrCreate([
            'status_id'     => $passed->id,
            'action'        => 'Find Release Branch',
            'command'       => 'release-branch',
            'example_param' => 'platform/version28_release',
            'description'   => 'For the given Jira Issue in question, find the release branch needed for pull request',
            'order'         => 4,
            'main'          => false,
            'required'      => false,
            'immutable'     => false,
            'nixable'       => true
        ]);

        Directive::firstOrCreate([
            'status_id'     => $passed->id,
            'action'        => 'Log Created Pull Request',
            'description'   => 'Used to indicate that a Pull Request has been created and to reference the contents from the GitHub response',
            'order'         => 5,
            'main'          => false,
            'required'      => false,
            'immutable'     => true,
            'nixable'       => false
        ]);

        Directive::firstOrCreate([
            'status_id'     => $passed->id,
            'action'        => "List Release Issues",
            'command'       => "list-release-issues",
            'example_param' => 'today, tomorrow',
            'description'   => "List Today's Jira Issues ready for release to Production",
            'order'         => 7,
            'main'          => true,
            'required'      => false,
            'immutable'     => false,
            'nixable'       => true
        ]);

        Directive::firstOrCreate([
            'status_id'     => $pullMerged->id,
            'action'        => 'Log Closed Pull Request',
            'description'   => 'Used to indicate that a Pull Request has been merged and to reference the contents from the GitHub response',
            'order'         => 1,
            'main'          => false,
            'required'      => false,
            'immutable'     => true,
            'nixable'       => false
        ]);

        Directive::firstOrCreate([
            'status_id'     => $pullMerged->id,
            'action'        => 'Refresh Pre-Production Environments',
            'description'   => 'SSH to Pre-Production Environments and run service specific Git commands or scripts',
            'order'         => 2,
            'main'          => false,
            'required'      => false,
            'immutable'     => true,
            'nixable'       => false
        ]);

        Directive::firstOrCreate([
            'status_id'     => $pullMerged->id,
            'action'        => 'Add Version Label',
            'description'   => 'Add the appropriate version label to the Jira Issue',
            'order'         => 3,
            'main'          => false,
            'required'      => false,
            'immutable'     => true,
            'nixable'       => false
        ]);

        Directive::firstOrCreate([
            'status_id'     => $pullMerged->id,
            'action'        => 'Slack QA',
            'description'   => 'Notify the QA team of the Jira Issue availability on pre-production environment',
            'order'         => 4,
            'main'          => false,
            'required'      => false,
            'immutable'     => true,
            'nixable'       => false
        ]);

        Directive::firstOrCreate([
            'status_id'     => $done->id,
            'action'        => 'Perform Merge',
            'command'       => 'merge',
            'example_param' => 'IP-6',
            'description'   => 'For the given Jira Issue in question, perform a merge of the working branch(es) into the specified head branch(es)',
            'order'         => 1,
            'main'          => true,
            'required'      => true,
            'immutable'     => false,
            'nixable'       => false
        ]);

        Directive::firstOrCreate([
            'status_id'     => $done->id,
            'action'        => 'Find Working Branch',
            'command'       => 'working-branch',
            'example_param' => 'platform/version28_release',
            'description'   => 'For the given Jira Issue in question, find the specified working branch(es)',
            'order'         => 2,
            'main'          => false,
            'required'      => false,
            'immutable'     => false,
            'nixable'       => true
        ]);

        Directive::firstOrCreate([
            'status_id'     => $done->id,
            'action'        => 'Find Destination Branch',
            'command'       => 'into',
            'example_param' => 'platform/version28_release',
            'description'   => 'For the given Jira Issue in question, find the specified destination branch(es)',
            'order'         => 3,
            'main'          => false,
            'required'      => true,
            'immutable'     => false,
            'nixable'       => false
        ]);

        Directive::firstOrCreate([
            'status_id'     => $done->id,
            'action'        => 'Commit Message',
            'command'       => 'message',
            'example_param' => "Fixed 'xyz' for Issue IP-6",
            'description'   => 'Leave a custom commit message',
            'order'         => 4,
            'main'          => false,
            'required'      => false,
            'immutable'     => false,
            'nixable'       => true
        ]);

        Directive::firstOrCreate([
            'status_id'     => $done->id,
            'action'        => 'Log Merge',
            'description'   => 'Used to indicate that a merge has occurred.',
            'order'         => 5,
            'main'          => false,
            'required'      => false,
            'immutable'     => true,
            'nixable'       => false
        ]);

        Directive::firstOrCreate([
        	'status_id'     => $review->id,
	        'action'        => 'Log Jira Issue Ready For Review',
	        'description'   => 'Used to indicate that a ticket is ready for Business Review.',
	        'order'         => 1,
	        'main'          => false,
	        'required'      => false,
	        'immutable'     => true,
	        'nixable'       => false
        ]);

        Directive::firstOrCreate([
        	'status_id'     => $review->id,
	        'action'        => 'Merge Into Review Branch',
	        'description'   => 'Merge changes related to Jira Issue into Review Branch if necessary.',
	        'order'         => 2,
	        'main'          => false,
	        'required'      => false,
	        'immutable'     => true,
	        'nixable'       => true,
        ]);

	    Directive::firstOrCreate([
		    'status_id'     => $review->id,
		    'action'        => 'Pull changes on Review Environment',
		    'description'   => 'Pull the latest changes from the Review Branch into the Review Environment.',
		    'order'         => 3,
		    'main'          => false,
		    'required'      => false,
		    'immutable'     => true,
		    'nixable'       => true,
	    ]);

	    Directive::firstOrCreate([
        	'status_id'     => $branchCreated->id,
	        'action'        => 'Link Branch to Jira Issue',
	        'command'       => 'link',
	        'example_param' => 'platform/feature/IP-6',
	        'description'   => 'For the given branch, populate the Jira-Key field with the provided Jira Issue ID.',
	        'order'         => 1,
	        'main'          => true,
	        'required'      => true,
	        'immutable'     => false,
	        'nixable'       => false
        ]);

	    Directive::firstOrCreate([
		    'status_id'     => $branchCreated->id,
		    'action'        => 'Link to Jira Issue',
		    'command'       => 'to',
		    'example_param' => 'IP-6',
		    'description'   => 'Update the Jira-Key column for a GitHub Branch to establish a relationship.',
		    'order'         => 2,
		    'main'          => false,
		    'required'      => true,
		    'immutable'     => false,
		    'nixable'       => false
	    ]);
    }
}
