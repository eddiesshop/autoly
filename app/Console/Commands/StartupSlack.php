<?php

namespace App\Console\Commands;

use App\Events\UserResponse;
use App\Exceptions\SlackableException;
use App\Models\Slack\Messenger;
use App\Models\User;
use Illuminate\Console\Command;

use React\EventLoop\Factory;
use Slack\RealTimeClient;

use App\Models\Activity;
use App\Models\Directive;

use Log;

class StartupSlack extends Command
{

    private $messenger;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'slack:startup';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    private $commandPrefix = '=';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
        $this->messenger = new Messenger();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        //
        $loop = Factory::create();

        $client = new RealTimeClient($loop);
        $client->setToken(getenv('SLACK_TOKEN'));

        $client->on('message', function($data) use ($client){

            Log::info([
                '*****## Message Received ##*****: ' => $data->getData()
            ]);

            //Need to get the User that sent the message
            //Break up the message that they sent. Look for '/' and command
                //if command is found, look for Directives and options
                    //Once Directives are found, get the mutable options, get the nixable options
                    //Figure out how to run
                //If not found, try to give suggestions.

            $data = $data->getData();

            if(!array_key_exists('type', $data) || $data['type'] != 'message'){

                Log::info("Either 'type' key is missing or 'type' != 'message'");
                return null;
            }

            Log::info([
                'Type: ' => $data['type']
            ]);

            if(isset($data['bot_id'])){

                Log::info("This is a Bot Message, no need to respond");
                return null;
            }elseif(isset($data['subtype']) && str_contains($data['subtype'], ['bot_message', 'message_deleted', 'message_changed'])){

				Log::info("This is a '{$data['subtype']}', no need to respond");
				return null;
		    }

            $channelId = $data['channel'];

            switch (substr($channelId, 0, 1)){

                case 'D':

                    Log::info([
                        'Channel Type: ' => 'Direct Message'
                    ]);

                    $user = User::whereHas('slackId', function($query) use ($data){

                        $query->where('user_name', $data['user']);
                    })->first();

                    if(!$user){

                        Log::info('Not a message from a user on Autoly, can ignore');
                        return null;
                    }

                    $commands = array_filter(explode(' ', $data['text']), function ($value) {

                        return !empty($value);
                    });

                    try{

                        event(new UserResponse($user, $commands));
                    }catch(SlackableException $e){

                        //get exception message and send to the user;
                        Log::info('Exception happened');

                        $e->getMessage();
                    }

                    break;

                case 'G':
                case 'C':

                    Log::info([
                        'Channel Type: ' => 'Group/Channel',
                        'Is Command Message?: ' => starts_with(trim($data['text']), Directive::COMMAND_PREFIX)
                    ]);

                    if(!starts_with(trim($data['text']), Directive::COMMAND_PREFIX)){

                        Log::info('Group Channel Message, without a command prefix, can ignore');
                        return null;
                    }

                    $commands = array_filter(explode(' ', $data['text']), function ($value) {

                        return !empty($value);
                    });

                    $user = User::whereHas('slackId', function($query) use ($data){

                        $query->where('user_name', $data['user']);
                    })->first();


                    try{

                        event(new UserResponse($user, $commands, $data['channel']));
                    }catch(SlackableException $e){

                        //get exception message and send to the user;
                        Log::info('Exception happened');
                    }

                    break;
            }
        });

        $client->connect()->then(function(){
            echo "Connected\n";
        });

        $loop->run();
    }
}
