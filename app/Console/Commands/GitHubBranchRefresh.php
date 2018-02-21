<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

use App\Models\User;
use App\Models\GitHub\GitHub;

class GitHubBranchRefresh extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'github:branch-refresh';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command to ensure that all branches are seeded on our DB';

    private $git;

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
        $this->git = new GitHub();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle(){
        //
        //TODO will need to load at least one user per org in order to seed their branches

        $users = User::whereId(1)->get();

        foreach ($users as $user){
            $this->git->getRepositories($user)->each(function($repository, $key){
                $repository->getBranches(true);
            });
        }
    }
}
