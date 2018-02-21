<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

use GuzzleHttp\Client;

use App\Models\User;

class MaintainSlackUserList extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'slack:maintain-user-list';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update database with new Slack users and delete old ones';


    private $client;

    private $token;

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
        $this->client = new Client();
        $this->token = getenv('SLACK_TOKEN');
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        //
        $endpoint = 'https://slack.com/api/users.list?token=' . $this->token;

        $response = $this->client->get($endpoint);

        $users = new Collection(json_decode($response->getBody()->getContents(), true)['members']);

        foreach ($users as $key => $user) {
            if (!$user['is_bot'] && array_key_exists('profile', $user) && array_key_exists('email', $user['profile'])) {
                $seededUser = User::firstOrNew(['email' => strtolower($user['profile']['email'])]);

                if ($user['deleted']) {
                    if ($seededUser->exists) {
                        $seededUser->delete();
                    }

                    continue;
                }

                $seededUser->name = array_key_exists('first_name', $user['profile']) ? ucfirst(strtolower($user['profile']['first_name'])) : strtolower($user['name']);

                $seededUser->save();

                $slackAccounts = $seededUser->slackAccounts()->get();

                if ($slackAccounts->isEmpty()) {
                    $seededUser->services()->create(['service_type' => 'S-I', 'user_name' => $user['id']]);
                    $seededUser->services()->create(['service_type' => 'S-U', 'user_name' => $user['name']]);
                } else {
                    foreach ($slackAccounts as $slackAccount){
                        switch ($slackAccount->service_type){
                            case 'S-I':
                                $slackAccount->user_name = $user['id'];
                                $slackAccount->save();
                                break;
                            case 'S-U':
                                $slackAccount->user_name = $user['name'];
                                $slackAccount->save();
                                break;
                        }
                    }
                }
            }
        }
    }
}
