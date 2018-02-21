<?php

namespace App\Exceptions;

use App\Exceptions\SlackableException;
use App\Models\Directive;

class JiraIssueNotFoundException extends SlackableException{

    protected $notFound = "Sorry, I can't seem to find this Jira Issue in my memory banks: {issueId}.";
    protected $notFoundForAction = "Sorry, I can't proceed with *{action}* because I don't see that this Jira Issue (_{issueId}_) is '{status}'.";

    public function __construct($recipient, $issueId, Directive $directive = null, $code = 0){

        $message = '';

        switch ($code){
            case 1:

                $message .= strtr($this->notFoundForAction, [
                    '{action}' => $directive->action,
                    '{issueId}' => $issueId,
                    '{status}' => $directive->status->name
                ]);

                break;
            default:
                $message .= strtr($this->notFound, ['{issueId}' => $issueId]);
        }


        parent::__construct($recipient, $message, $code);
    }
}