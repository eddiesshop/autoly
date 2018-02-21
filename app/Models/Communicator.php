<?php
/**
 * Created by PhpStorm.
 * User: eddiecarrasco
 * Date: 10/18/2016
 * Time: 5:39 PM
 */

namespace App\Models;

use GuzzleHttp\Client;

use App\Contracts\CommunicationInterface;

use App\Models\User;

use Exception;

abstract class Communicator{

    protected $host;
    protected $version;
    protected $userCredentials;

    protected $endpoint;
    protected $headers = [];
    protected $response;

    protected $client;

    protected $user;

    public function __construct(){
        $this->client = new Client();
    }

    public function getHost(){
        return $this->host;
    }

    public function getVersion(){
        return $this->version;
    }

    abstract protected function setUserCredentials(User $user);

    protected function getUserCredentials(){
        if(is_null($this->userCredentials)) throw new Exception("User credentials have not been set yet.");

        return $this->userCredentials;
    }

    /**
     * @param CommunicationInterface $item
     */
    abstract protected function setEndpoint(CommunicationInterface $item);

    protected function getEndpoint(){
        return $this->endpoint;
    }

    /**
     * @param array $headers
     */
    abstract protected function setHeaders($headers);

    abstract protected function getHeaders();

    /**
     * @return \GuzzleHttp\Psr7\Response
     */
    public function getResponse(){
        return $this->response;
    }

    public function get(CommunicationInterface $item){
        $this->setEndpoint($item);

        $headers = $this->getHeaders();

        $endpoint = $this->getEndpoint();

        if(!empty($item->getData())) $endpoint .= '?' . http_build_query($item->getData());

        $this->response = $this->client->get($endpoint, ['headers' => $headers]);

        return json_decode($this->response->getBody()->getContents(), true);
    }

    public function post(CommunicationInterface $item){
        $this->setEndpoint($item);

        $headers = $this->getHeaders();

        $endpoint = $this->getEndpoint();

        $this->response = $this->client->post($endpoint, ['headers' => $headers, 'body' => json_encode($item->getData())]);

        $item->resetData();

        return json_decode($this->response->getBody()->getContents(), true);
    }

	public function put(CommunicationInterface $item){
		$this->setEndpoint($item);

		$headers = $this->getHeaders();

		$endpoint = $this->getEndpoint();

		$this->response = $this->client->put($endpoint, ['headers' => $headers, 'body' => json_encode($item->getData())]);

		$item->resetData();

		return json_decode($this->response->getBody()->getContents(), true);
	}
}