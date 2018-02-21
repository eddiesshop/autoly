<?php

namespace App\Exceptions;

use App\Exceptions\SlackableException;

use App\Models\User;
use App\Models\Directive;
use Illuminate\Support\Collection;

class CommandNotFoundException extends SlackableException {

    protected $message = "Are you sure that's right? That command `{command}` doesn't exist.";

    /**
     * @param User $user
     * @param string $command
     */
    public function __construct($recipient, $command){

        $message = strtr($this->message, ['{command}' => $command]);

        parent::__construct($recipient, $message);
    }
}