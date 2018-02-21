<?php namespace App\Models\Slack;

use App\Contracts\CommunicationInterface;
use App\Models\Communicator;
use App\Models\User;
use App\Traits\CommunicationTrait;

use GuzzleHttp\Client;
use Illuminate\Support\Collection;


class Messenger extends Communicator implements CommunicationInterface {

	use CommunicationTrait;

    const URI_STRUCTURE = '{host}/{method}';

    protected $host = 'https://slack.com/api/';

    protected $resource;

    protected $data;

    const TEST_METHOD = 'auth.test';
    const OPEN_CHANNEL_METHOD = 'im.open';
    const CHAT_MSG_METHOD = 'chat.postMessage';
    const CHAT_MSG_UPDATE_METHOD = 'chat.update';
    const USERS_METHOD = 'users.list';
    const CHANNELS_METHOD = 'channels.list';
    const GROUPS_METHOD = 'groups.list';

    private $token;

    private $users;

    private $escapeCharacters = ['&' => '&amp', '<' => '&lt', '>' => '&gt'];

    protected $replaceOriginal;

    protected $to;

    protected $channel;

    protected $message;

    protected $attachments;

    protected $attachmentStencil = [
        'fallback'          => null,
        'color'             => '#36a64f',
        'pretext'           => null,
        'title'             => null,
        'title_link'        => null,
        'text'              => null,
        'callback_id'       => null,
        'actions'           => null,
        'attachment_type'   => 'default',
        'fields'    => [
            [
                'title' => null,
                'value' => null,
                'short' => true
            ]
        ],
        'footer' => null,
        'ts' => null,
        'mrkdwn_in'         => ['pretext', 'text', 'fields']
    ];

    public function __construct(){
        $this->token = getenv('SLACK_TOKEN');
        $this->attachments = new Collection();
        $this->getUsers();

        parent::__construct();
    }

    protected function setEndpoint(CommunicationInterface $item){
        $this->endpoint = strtr(self::URI_STRUCTURE, ['{host}' => $this->getHost(), '{method}' => $item->getResource()]);
    }

    protected function setHeaders($headers){
        //Not necessary. No special headers required.
    }

    protected function getHeaders(){
        return null;
    }

    public function getData(){
        $this->data['token'] = $this->token;

        return $this->data;
    }

    public function setUserCredentials(User $user){
        //Not necessary.
    }

    public function test(){

        $this->resource = self::TEST_METHOD;

        return $this->get($this);
    }

    public function getList($type = self::CHANNELS_METHOD){
        $this->resource = $type;

        $this->data = [
            'exclude_archived' => true,
            'exclude_members' => true
        ];

        return last($this->get($this));
    }

    /**
     * @return Illuminate\Database\Eloquent\Collection|App\Models\User
     */
    protected function getUsers(){
        if(isset($this->users)) return $this->users;

        //TODO will have to figure out how to make this a per organization thing
        return $this->users = User::has('slackAccounts')->with('slackAccounts')->get();
    }


    /**
     * @param string $identifier
     * @return App\Models\User
     */
    protected function getUser($identifier){
        if (filter_var($identifier, FILTER_VALIDATE_EMAIL)){
            return $this->getUsers()->filter(function($user, $index) use ($identifier){
                return strcasecmp($user->email, $identifier) == 0;
            })->first();
        }else{
            if(substr($identifier, 0, 1) == '@') $identifier = substr($identifier, 1);

            return $this->getUsers()->filter(function($user, $index) use ($identifier){
                return !$user->slackAccounts->where('user_name', $identifier)->isEmpty();
            })->first();
        }
    }

    public function openDirectChannel(){

        $this->resource = self::OPEN_CHANNEL_METHOD;
        $this->data = [
            'user' => $this->to
        ];

        $response = $this->get($this);

        if($response['ok']) $this->channel = $response['channel']['id'];

        return $this;
    }


    /**
     * @param string|User $user
     * @return Messenger
     */
    public function to($user){

        if(is_string($user) && substr($user, 0, 1) != '@'){

            if(substr($user, 0, 1) == '#'){//Need to get ID for Channel

                $channels = new Collection($this->getList());

                $targetChannel = substr($user, 1);

                $foundChannel = $channels->first(function($key, $channel) use ($targetChannel){
                    return $channel['name'] == $targetChannel;
                });

                if(!$foundChannel){

                    $foundChannel = (new Collection($this->getList(self::GROUPS_METHOD)))->first(function($key, $channel) use ($targetChannel){
                        return $channel['name'] == $targetChannel;
                    });
                }

                $this->channel = $this->to = head($foundChannel);

                return $this;
            }

            if(strlen($user) == 9){//Slack Specific ID received

                $this->channel = $this->to = $user;

                return $this;
            }
        }

        if($user instanceof User){

            if(is_null($user->slackAccounts)) $user->slackAccounts = $user->slackAccounts()->get();

            $this->to = $user->slackAccounts->where('service_type', 'S-I')->first()->user_name;

            return $this;
        }

        $slackUser = $this->getUser($user)->slackAccounts->where('service_type', 'S-I')->first();

        $this->to = $slackUser->user_name;

        return $this;
    }

    public function attach(array $message){

        $keys = array_keys($message);

        if(is_numeric($keys[0])){
            foreach ($message as $singleMessage){
                $this->attach($singleMessage);
            }
        }else{
            $diff = array_diff_key($this->attachmentStencil, $message);
            $attachment = array_intersect_key($message, $this->attachmentStencil);
            $attachment = array_merge($attachment, $diff);

            //TODO need to encode the special characters here, before pushing to attachments

            $this->attachments->push($attachment);
        }

        return $this;
    }

    public function getAttachments(){
        return $this->attachments;
    }

    public function replaceOriginal($flag = true){
        $this->replaceOriginal = $flag;

        return $this;
    }

    /**
     * @param string $message
     */
    public function send($message = null, $originalMsgTimestamp = null){
        $this->message = $message;

        if(!is_null($this->to) && substr($this->to, 0, 1) == 'U') $this->openDirectChannel();

        $this->resource = is_null($originalMsgTimestamp) ? self::CHAT_MSG_METHOD : self::CHAT_MSG_UPDATE_METHOD;

        $this->data = [
            'channel' => $this->channel,
            'username' => 'Auto Magically'
        ];

        if(!is_null($this->message)) $this->data['text'] = $this->message;
        if(!$this->attachments->isEmpty()) $this->data['attachments'] = $this->attachments->toJson();
        if(!is_null($originalMsgTimestamp)) $this->data['ts'] = $originalMsgTimestamp;
        if(is_null($this->replaceOriginal)){
            $isFromCallbackController = !empty(array_filter(debug_backtrace(), function($value){
                return array_key_exists('file', $value) ? str_contains($value['file'], 'MessageCallbackController') : false;
            }));

            if($isFromCallbackController) $this->data['replace_original'] = false;
        }else{
            $this->data['replace_original'] = $this->replaceOriginal;
        }

        $response = $this->get($this);
        $this->reset();

        return $response;
    }

    private function reset(){
        $this->message = null;
        $this->attachments = new Collection();
        $this->channel = null;
        $this->to = null;
    }
}