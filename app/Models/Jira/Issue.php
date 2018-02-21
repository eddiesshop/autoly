<?php

namespace App\Models\Jira;

use App\Contracts\CommunicationInterface;
use App\Exceptions\JiraIssueNotFoundException;
use App\Models\Jira\Enum\Status as JiraStatus;
use App\Models\Jira\Jira;
use App\Models\User;
use App\Traits\CommunicationTrait;

use Symfony\Component\Debug\Exception\FatalErrorException;
use Carbon\Carbon;
use GuzzleHttp\Exception\ClientException;
use Illuminate\Support\Collection;




class Issue implements CommunicationInterface{

	use CommunicationTrait;

    CONST RESOURCE = 'issue/{key}';

    protected $issueData;

    protected $resource;

    protected $data;

    protected $id;

    protected $assignee;

    protected $reporter;

    /**
     * @var string
     */
    protected $status;

    /**
     * @var string
     */
    protected $summary;

    protected $description;


    /**
     * The time this issue was last updated.
     *
     * @var Carbon
     */
    protected $lastUpdated;


    /**
     * @var Collection
     */
    protected $comments;

    /**
     * @var Collection
     */
    protected $history;

    protected $priority;

    /**
     * @var Collection
     */
    protected $labels;


    /**
     * Issue constructor.
     * @param mixed $attributes
     */
    public function __construct($attributes = null){

        if(!is_null($attributes)){
            if(is_array($attributes)){

                $this->issueData = $attributes;
            }else if(is_string($attributes)){

                $jira = new Jira();

                $this->resource = strtr(self::RESOURCE . '?expand=transitions', ['{key}' => $attributes]);

                try{

                    $this->issueData = $jira->get($this);
                }catch(ClientException $e){

                    if($e->getResponse()->getStatusCode() == 404){
                        throw new JiraIssueNotFoundException(null, $attributes);
                    }

                    throw $e;
                }
            }
        }
        //TODO Will return a blank object if attributes is null, should I allow them to set ID?
    }


    /**
     * @param string $id The id (Key) of the issue
     * @return Issue
     */
    public static function get($id){
        return new self($id);
    }

	//////////////////////////////////////////////////
	//  Various Jira Issue Related Helper Methods   //
	//////////////////////////////////////////////////

	/**
	 * @return User
	 */
	public function getAssignee(){

    	if(!is_null($this->assignee)) return $this->assignee;

    	return $this->assignee = User::whereEmail($this->getFields()['assignee']['emailAddress'])->first();
	}

	/**
	 * @return User
	 */
	public function getReporter(){

		if(!is_null($this->reporter)) return $this->reporter;

		return $this->reporter = User::whereEmail($this->getFields()['creator']['emailAddress'])->first();
	}

	/**
	 * @return string
	 */
	public function getId(){
		return $this->getKey();
	}

	/**
	 * @return integer
	 */
	public function getStatusId(){

		if(!is_null($this->status)) return $this->status;

		return $this->status = $this->getFields()['status']['id'];
	}

	/**
	 * @return string
	 */
	public function getStatus(){

		return JiraStatus::getString($this->getStatusId());
	}

	/**
	 * @return string
	 */
	public function getSummary(){

		if(!is_null($this->summary)) return $this->summary;

		return $this->summary = $this->getFields()['summary'];
	}

	/**
	 * @return string
	 */
	public function getDescription(){

		if(!is_null($this->description)) return $this->description;

		return $this->description = $this->getFields()['description'];
	}

	public function getPriority(){

		if(!is_null($this->priority)) return $this->priority;

		return $this->priority = $this->getFields()['priority']['name'];
	}

	public function getLink(){
		$jira = new Jira();

		return $jira->getHost()."/browse/{$this->getKey()}";
	}

	/**
	 * @return Carbon
	 */
	public function getLastUpdated(){

		if(!is_null($this->lastUpdated)) return $this->lastUpdated;

		return $this->lastUpdated = Carbon::parse($this->getFields()['updated']);
	}

    public function getHistory(){

        if(!is_null($this->history)) return $this->history;

        $jira = new Jira();
        $this->resource = strtr(self::RESOURCE. '?expand=changelog', ['{key}' => $this->getKey()]);

        return $this->history = new Collection($jira->get($this)['changelog']['histories']);
    }

    /**
     * @param JiraStatus|int $from
     * @param JiraStatus|int $to
     * @return string timestamp
     */
    public function getLatestStatusChangeTime($from = null, $to = null){
        if(!is_null($from) && !is_null($to)){
            return $this->getHistory()->whereLoose('items.0.from', $from)->whereLoose('items.0.to', $to)->last()['created'];
        }

        return $this->getHistory()->whereLoose('items.0.field', 'status')->last()['created'];
    }

    /**
     * @return Collection
     */
    public function getComments(){

        if(is_null($this->comments)){

        	$this->resource = strtr(self::RESOURCE . '/comment', ['{key}' => $this->getId()]);

        	$jira = new Jira();
        	$comments = $jira->get($this)['comments'];

        	$this->issueData['comment'] = ['comments' => $comments];
        	return $this->comments = new Collection($comments);
        }else{

	        return $this->comments;
        }
    }

    /**
     * @param string $comment The comment to make on this issue
     * @return Issue
     */
    public function makeComment($comment){
        $jira = new Jira();
        $this->resource = strtr(self::RESOURCE . '/comment', ['{key}' => $this->getKey()]);
        $this->data = ['body' => $comment];

	    if(is_null($this->comments)) $this->comments = new Collection();

	    $jiraComment = $jira->post($this);

	    $this->comments->push($jiraComment);

        return $this;
    }

	/**
	 * @return Collection
	 */
    public function getLabels(){

    	if(!is_null($this->labels)) return $this->labels;

        return $this->labels = new Collection($this->getFields()['labels']);
    }

    public function addLabel($label){
        $jira = new Jira();
        $this->resource = strtr(self::RESOURCE, ['{key}' => $this->getKey()]);
        $this->data = ['update' => ['labels' => [['add' => $label]]]];

        if(is_null($this->labels)) $this->labels = new Collection();

        $jiraLabel = $jira->put($this);

        $this->labels->push($jiraLabel);

        return $this;
    }

    public function changeStatus($newStatus){

    	$transitionId = (new Collection($this->getTransitions()))->whereLoose('to.id', $newStatus)->first()['id'];

	    $jira = new Jira();
	    $this->resource = strtr(self::RESOURCE . '/transitions', ['{key}' => $this->getKey()]);
	    $this->data = ['transition' => ['id' => $transitionId]];

	    $jira->post($this);

	    $this->status = $newStatus;

	    return $this;
    }

    public function __call($name, $arguments){
        $fullClassName = get_class($this).'::'.$name;

        if(!empty($name) && str_contains($name, 'get') && empty($arguments)){

            $key = snake_case(str_replace_first('get', '', $name));

            if(!array_key_exists($key, $this->issueData)) throw new FatalErrorException("Call to undefined method $fullClassName()", 0, 1, null, 62);

            return $this->issueData[$key];
        }

        return null;
    }
}