<?php

namespace App\Traits;


trait CommunicationTrait{

	/**
	 * @return String
	 */
	public function getResource(){

		return $this->resource;
	}

	/**
	 * @return array
	 */
	public function getData(){

		return $this->data;
	}

	/**
	 * @return null
	 */
	public function resetData(){

		$this->data = [];
	}

}