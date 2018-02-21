<?php

namespace App\Events;

use Illuminate\Support\Collection;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

use App\Exceptions\CommandNotFoundException;
use App\Exceptions\MissingCommandPrefixException;
use App\Exceptions\MissingRequiredCommandsException;
use App\Models\Activity;
use App\Models\Directive;
use App\Events\Event;
use App\Models\Response;
use App\Models\User;

class UserResponse extends Event
{
    use SerializesModels;

    private $user;

    private $commands;

    private $directives;

    /**
     * @var Response
     */
    private $response;

    private $channelId;

    /**
     * Create a new event instance.
     * @param User $user
     * @param array $commands
     * @return void
     */
    public function __construct(User $user, array $commands = [], $channelId = ''){
        //
        $this->user = $user;
        $this->commands = $commands;
        $this->channelId = !empty($channelId) ? $channelId : null;
        $this->recordResponse();
        $this->findDirectives();
    }


    public function getUser(){
        return $this->user;
    }

    public function getCommands(){
        return $this->commands;
    }

    /**
     * @return Collection|Directive
     */
    public function getDirectives(){
        return $this->directives;
    }

    /**
     * @return Response
     */
    public function getResponse(){
        return $this->response;
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

    private function recordResponse(){
        $this->response = Response::create([
            'user_id' => $this->user->id,
            'response' => implode(' ', $this->commands),
            'channel_id' => $this->channelId
        ]);
    }

    private function findDirectives(){
        $mainCommandKey = false;

        foreach ($this->commands as $key => $command){

            if(starts_with($command, Directive::COMMAND_PREFIX)){
                $mainCommandKey = $key;
                break;
            }
        }

        if($mainCommandKey === false) throw new MissingCommandPrefixException($this->getResponse()->getRespondTo());

        $commandExtract = strtr($this->commands[$mainCommandKey], [Directive::COMMAND_PREFIX => '']);
        $mainCommand = Directive::whereMain(true)->whereCommand($commandExtract)->first();

        if(!$mainCommand) throw new CommandNotFoundException($this->getResponse()->getRespondTo(), $this->commands[$mainCommandKey]);

        $directivesQuery = Directive::whereStatusId($mainCommand->status_id)
            ->where('order', '>=', $mainCommand->order)
            ->orderBy('order');

        $requiredDirectivesQuery = Directive::whereStatusId($mainCommand->status_id)
            ->where('order', '>', $mainCommand->order)
            ->whereMain(false)
            ->whereRequired(true);

        $otherMainCommand = Directive::whereMain(true)
            ->whereStatusId($mainCommand->status_id)
            ->where('id', '!=', $mainCommand->id)
            ->where('order', '>', $mainCommand->order)
            ->orderBy('order')
            ->first();

        if(!is_null($otherMainCommand)){

            $directivesQuery->where('order', '<', $otherMainCommand->order);

            $requiredDirectivesQuery->where('order', '<', $otherMainCommand->order);
        }

        $this->directives = $directivesQuery->get();

        $requiredDirectives = $requiredDirectivesQuery->get();

        $requiredDirectives->transform(function($directive){
            $commandKey = array_search(Directive::OPTION_PREFIX . $directive->command, $this->commands);

            $directive->found = $commandKey === false ? false : true;

            return $directive;
        });

        $missingDirectives = $requiredDirectives->where('found', false);

        if(!$missingDirectives->isEmpty()) throw new MissingRequiredCommandsException($this->getResponse()->getRespondTo(), $missingDirectives);

        //TODO reorder commands so that they match the order the directive expects them in.
    }
}
