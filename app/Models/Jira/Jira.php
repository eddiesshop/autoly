<?php
namespace App\Models\Jira;

use App\Models\Communicator;
use App\Contracts\CommunicationInterface;
use App\Models\User;

class Jira extends Communicator {
    //
    const URI_STRUCTURE = "{host}/rest/api/{version}/{resource}";

    protected $host; //Set JIRA_HOST in .env file
    protected $version = 2;
    protected $userCredentials;

    protected $resource;
    protected $data;

    public function __construct(User $user = null){
        parent::__construct();

        $this->host = getenv('JIRA_HOST');

        !is_null($user) ? $this->setUserCredentials($user) : $this->setUserCredentials(new User());

        $this->setHeaders();
    }

    public function setUserCredentials(User $user){

        if(!$user->exists) $this->userCredentials = base64_encode(getenv('JIRA_USER').':'.getenv('JIRA_PASS'));
    }

    public function setHeaders($headers = []){

        $masterHeaders = [
        	'Authorization' => "Basic {$this->getUserCredentials()}",
	        'Content-Type' => 'application/json'
        ];

        $diff = array_diff_assoc($headers, $masterHeaders);

        foreach ($diff as $key => $value) {

            $masterHeaders[$key] = $value;
        }

        $this->headers = $masterHeaders;
    }

    public function getHeaders(){

        return $this->headers;
    }

    public function setEndpoint(CommunicationInterface $item){

        $this->endpoint = strtr(self::URI_STRUCTURE, [
            '{host}'      => $this->getHost(),
            '{version}'   => $this->getVersion(),
            '{resource}'  => $item->getResource()
        ]);
    }

	public function get(CommunicationInterface $item){
		unset($this->headers['Content-Type']);

		return parent::get($item);
	}
}
