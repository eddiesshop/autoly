<?php namespace App\Models\Jira;

use Illuminate\Support\Collection;
use Carbon\Carbon;

use App\Contracts\CommunicationInterface;
use App\Models\Jira\Enum\Status;
use App\Models\Jira\Jira;
use App\Models\Jira\Issue;
use App\Models\User;
use App\Traits\CommunicationTrait;


class Search implements CommunicationInterface{

	use CommunicationTrait;

    const RESOURCE = 'search?jql=';

    const STATUS = 'status';
    const UPDATED = 'updated';
    const ASSIGNED_TO = 'assignee';

    protected $resource;

    protected $data;

    protected $excludedProjects;

    public function __construct(){

    	$excludedProjects = explode(',', getenv('JIRA_EXCLUDE_PROJECTS_FROM_SEARCH'));

    	$this->excludedProjects = empty($excludedProjects) ?: array_filter(array_map(function($excludedProject){

    		if(strlen($excludedProject)) return "'" . trim($excludedProject) . "'";
	    }, $excludedProjects), function($value){

    		return strlen($value);
	    });
    }


    /**
     * @param Carbon $updatedAfter
     * @return Collection|Issue[]
     */
    public function getIssues($targetStatus, User $user = null, Carbon $updatedAfter = null){

        $jira = new Jira();

        $targetStatusString = '';

        if(is_array($targetStatus)){

            foreach ($targetStatus as &$status){

                $status = '"' . Status::getString($status) . '"';
            }

            $targetStatusString = implode(', ', $targetStatus);
        }else{

            $targetStatusString = '"' . Status::getString($targetStatus) . '"';
        }

        $this->resource = self::RESOURCE.self::STATUS." in ($targetStatusString)";

        if(!is_null($user)){

            $this->resource .= ' AND '.self::ASSIGNED_TO.' IN ('.$user->jiraAccount()->first()->user_name.')';
        }

        if(!is_null($updatedAfter)){

            $updatedAfter->setToStringFormat('Y-m-d H:i');
            $this->resource .= " AND updated >= '{$updatedAfter->__toString()}'";
        }

        if(!empty($this->excludedProjects)){

		    $this->resource .= 'AND project NOT IN (' . implode(', ', $this->excludedProjects) . ')';
	    }

        $this->resource .= '&fields=*all&expand=transitions';

        $result = $jira->get($this);

        $issues = new Collection();

        foreach($result['issues'] as $issue){

            $issues->push(new Issue($issue));
        }

        return $issues;
    }

    /**
     * @param Carbon $updatedAfter
     * @return Collection|Issue
     */
    public function getChangedIssues(array $oldStatuses = [], array $targetStatuses = [], User $user = null, Carbon $updatedAfter = null){
        $jira = new Jira();

        $fromStatuses = [];
        foreach ($oldStatuses as $oldStatus){

            $fromStatuses[] = "'".Status::getString($oldStatus)."'";
        }
        $fromStatuses = implode(', ', $fromStatuses);

        $toStatuses = [];
        foreach ($targetStatuses as $targetStatus){

            $toStatuses[] = "'".Status::getString($targetStatus)."'";
        }
        $toStatuses = implode(', ', $toStatuses);

        $from = !empty($fromStatuses) ? "FROM ($fromStatuses)" : '';
        $to = !empty($toStatuses) ? "TO ($toStatuses)" : '';

        $this->resource = self::RESOURCE.self::STATUS." CHANGED $from $to";

        if(!is_null($updatedAfter)){
            $updatedAfter->setToStringFormat('Y-m-d H:i');
            $this->resource .= " AFTER '{$updatedAfter->__toString()}'";
        }

        if(!is_null($user)) $this->resource .= " AND assignee IN ({$user->jiraAccount()->first()->user_name})";

        if(!empty($this->excludedProjects)){

        	$this->resource .= 'AND project NOT IN (' . implode(', ', $this->excludedProjects) . ')';
        }

        $this->resource .= '&fields=*all&expand=transitions';

        $result = $jira->get($this);

        $issues = new Collection();

        if(array_key_exists('issues', $result)) {

            foreach ($result['issues'] as $issue) {
                $issue = new Issue($issue);

                $issues->push($issue);
            }
        }

        return $issues;
    }
}