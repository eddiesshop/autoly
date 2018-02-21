<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
        // Commands\Inspire::class,
        Commands\JiraTicketsChangedToInReview::class,
        Commands\JiraTicketsChangedToInProgress::class,
        Commands\JiraTicketsChangedToPreRelease::class,
        Commands\StartupSlack::class,
        Commands\MaintainSlackUserList::class,
        Commands\GitHubMergedPullRequests::class,
        Commands\GitHubBranchRefresh::class,
    ];

    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        // $schedule->command('inspire')
        //          ->hourly();

        $schedule->command('slack:maintain-user-list')->weekdays()->twiceDaily(1,12);
        $schedule->command('slack:startup')->everyMinute()->weekdays()->withoutOverlapping();
        $schedule->command('jira:failed-tickets')->cron('* 9-19 * * 1-5');
        $schedule->command('jira:passed-tickets -S 10')->cron('* 9-19 * * 1-5');
        $schedule->command('jira:prerelease-tickets')->cron('* 9-19 * * 1-5');
//        $schedule->command('github:merged-pulls')->everyMinute()->weekdays();
//        $schedule->command('github:branch-refresh')->everyThirtyMinutes()->weekdays();
    }
}
