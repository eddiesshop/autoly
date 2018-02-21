<?php

namespace App\Events;

use App\Events\Event;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

use React\EventLoop\Factory;
use Slack\RealTimeClient;

class SlackBotSetup extends Event
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

        $loop = Factory::create();

        $client = new RealTimeClient($loop);
        $client->setToken(getenv('SLACK_TOKEN'));

        $client->on('message', function($data) use ($client){
            echo 'Message Received';
            var_dump($data);
        });

        $client->connect()->then(function(){
            echo "Connected\n";
        });

        $loop->run();
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
