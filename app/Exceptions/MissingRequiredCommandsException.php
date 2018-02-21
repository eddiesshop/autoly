<?php

namespace App\Exceptions;

use App\Exceptions\SlackableException;
use App\Models\Directive;
use App\Models\User;

use Exception;

use Illuminate\Support\Collection;

class MissingRequiredCommandsException extends SlackableException {

    protected $message = "You seem to be missing a few commands: {requiredCommands}.";

    public function __construct($recipient, Collection $requiredDirectives){

        $requiredCommands = [];

        foreach ($requiredDirectives as $directive){
            $requiredCommands[] = Directive::OPTION_PREFIX . "$directive->command ($directive->example_param)";
        }

        $message = strtr($this->message, ['{requiredCommands}' => implode(", ", $requiredCommands)]);

        parent::__construct($recipient, $message);
    }
}