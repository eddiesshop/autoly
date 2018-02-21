<?php

namespace App\Exceptions;

use App\Exceptions\SlackableException;
use App\Models\Directive;
use App\Models\User;

use Illuminate\Support\Collection;

class MissingCommandPrefixException extends SlackableException{

    protected $message = "I don't know where to start, you're missing the command prefix ('{command_prefix}')!";

    /**
     * @param User|Collection|string $recipient
     */
    public function __construct($recipient){

        $message = strtr($this->message, ['{command_prefix}' => Directive::COMMAND_PREFIX]);

        parent::__construct($recipient, $message);
    }
}