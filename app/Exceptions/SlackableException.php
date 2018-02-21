<?php

namespace App\Exceptions;

use App\Models\User;
use Exception;

use App\Models\Slack\Messenger;

use Illuminate\Support\Collection;

class SlackableException extends Exception{

    /**
     * @param User|Collection|string $users
     * @param string $message
     * @param integer $code
     * @param Exception $previous
     */
    public function __construct($recipient, $message, $code = 0, Exception $previous = null){

        $slack = new Messenger();

        if(is_string($recipient) || $recipient instanceof User) $recipient = (new Collection())->push($recipient);

        if($recipient instanceof Collection){

            foreach ($recipient as $user){
                $slack->to($user)->send($message);
            }
        }

        parent::__construct($message, $code, $previous);
    }
}