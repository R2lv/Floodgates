<?php

namespace DavidFricker\Floodgates;

class Floodgates {
	private $bucket_capacity;

	// time in seconds for one drop to leak
	private $bucket_leak_rate;

	private $bucket_last_leak_time;

	private $storage;

	const PREFIX_LAST_LEAK_TIME = 'FoodgatesLLT';

	private $UID;

	// leak rate = time for the bucket to empty in second// e.g. bucket of size 5 takes 1 minute to leak = 5 requests per 60 seconds
	public function __construct($UID, $bucket_capacity, $bucket_leak_rate) {
		$this->bucket_capacity = $bucket_capacity;
		$this->bucket_leak_rate = $bucket_leak_rate;
		$this->UID = $UID;

		$this->initStorage();
		$this->fetchLastLeakTimeToObject();
	}

	private function initStorage() {
		$this->storage = new Redis();
		$this->storage->pconnect('127.0.0.1', 6379);
		$this->storage->setOption(Redis::OPT_PREFIX, 'Floodgates:');
	}

	// get the last drop leak time from the storage or default to curr time 
	private function fetchLastLeakTimeToObject() {
		$last_leak_time = $this->storage->get(self::PREFIX_LAST_LEAK_TIME.$this->UID);

		if (!$last_leak_time) {
			$this->setLastLeakTime(time());
			//return $this->bucket_last_leak_time = time();
		}
		
		return $this->bucket_last_leak_time = $last_leak_time;
	}

	// sets the last leak time in the object and persits it to storage
	private function setLastLeakTime($time) {
		$this->bucket_last_leak_time = $time;

		// could move this to the destrcutor
		$this->storage->set(self::PREFIX_LAST_LEAK_TIME.$this->UID, $this->bucket_last_leak_time);

	}

	// sets the current time as the last time the bucket recived a drop
	private function updateLastDropTime() {
		$this->bucket_last_leak_time = time();
	}

	// push out the drops that have excceded thier TTL
	public function leak() {
		$elapsed_time = time() - $this->bucket_last_leak_time;
		$drops_to_leak = $elapsed_time * ($this->bucket_capacity / $this->bucket_leak_rate);

		if ($drops_to_leak < 1) {
			return;
		}

		$drops_to_leak = round($drops_to_leak);

		// ensure the bucket drop count doesnt go negaitve
		if ($drops_to_leak > $this->bucket_capacity) {
			$drops_to_leak = $this->bucket_capacity;
		}

		$this->storage->decrBy($this->UID, $drops_to_leak);

		$this->setLastLeakTime(time());
	}

	// attempts to add drop to bucket, if bucket is not overflowing it returns true
	// if overflowing, call the leak method to clear spce and try again 
	// still no space for the new droplet, return false = rate limited
	public function addDrop($drops = 1) {
		$size = $this->storage->incrBy($this->UID, $drops);
		if ($size <= $this->bucket_capacity) {
			return true;
		}

		// the bucket is overflowing
		// leak what can be leaked according to TTL
		$this->leak();
		$new_size = $this->getDropCount();
		
		// if we have been able to make space for the new drop
		if ($new_size <= $this->bucket_capacity) {
			return true;
		}

		// the bucket is STILL overflowing, decremnt the one we have added
		$this->storage->decrBy($this->UID, $drops);
		
		return false;
	}

	private function getDropCount() {
		return (int) $this->storage->get($this->UID);
	}

	public function capcityLeft() {
		return abs($this->bucket_capacity - $this->getDropCount());
	}

	// alias for get drop count
	public function capcityUsed() {
		return $this->getDropCount();
	}

	public function isFull() {
		return ($this->getDropCount() >= $this->bucket_capacity);
	}

	// empty all drops from bucket
	public function flush() {
		$this->storage->set($this->UID, 0);
	}
}
