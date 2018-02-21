<?php

namespace App\Http\Controllers;

use App\Events\UserResponse;
use App\Models\Activity;
use App\Models\User;
use App\Models\Slack\Messenger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

use App\Http\Requests;

use Log;

class MessageCallbackController extends Controller{
    //

    public function handle(Request $request){

        $payload = json_decode($request->get('payload'), true);

        $verifyToken = env('SLACK_VERIFY_TOKEN');

        if(!array_key_exists('token', $payload) || strcasecmp($verifyToken, $payload['token']) != 0){
            return new JsonResponse(null, JsonResponse::HTTP_NOT_ACCEPTABLE);
        }

        $activity = Activity::findOrFail($payload['callback_id']);

        $user = User::whereHas('slackId', function($q) use ($payload){
            $q->whereUserName($payload['user']['id']);
        })->first();

        $originalMsgAttachments = $payload['original_message']['attachments'][0];

        unset($originalMsgAttachments['actions']);
        unset($originalMsgAttachments['callback_id']);

        $text = $originalMsgAttachments['text'];
        $text = substr($text, 0, strpos($text, '*', 1) + 1);//Extract just the bold text which is the status update.

        $response = $payload['actions'][0]['value'];

        if(empty($response)){
            $originalMsgAttachments['text'] = $text;

            $originalMsg = [
                'replace_original' => true,
                'attachments' => [$originalMsgAttachments]
            ];

            return new JsonResponse($originalMsg);
        }

        switch ($response){
            case 'options':
                break;
            default:

                event(new UserResponse($user, explode(' ', $response)));

                $text .= "I'll let you know once I'm done.";

                $originalMsgAttachments['text'] = $text;

                $originalMsg = [
                    'replace_original' => true,
                    'attachments' => [$originalMsgAttachments]
                ];

                return new JsonResponse($originalMsg);
        }
    }
}
