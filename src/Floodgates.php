<?php

class Floodgates {
	private $bucket_capacity;
	// time in seconds for one drop to leak
	private $bucket_leak_rate;
	private $bucket_last_leak_time;
	private $storage;
	const PREFIX_LAST_LEAK_TIME = 'FoodgatesLLT';

	// leak rate = time for the bucket to empty in second// e.g. bucket of size 5 takes 1 minute to leak = 5 requests per 60 seconds
	public function __construct($UID, $bucket_capacity, $bucket_leak_rate) {
		$this->bucket_capacity = $bucket_capacity;
		$this->bucket_leak_rate = $bucket_leak_rate;

		$this->storage = new Redis();
		$this->storage->pconnect('127.0.0.1', 6379);
		$this->storage->setOption(Redis::OPT_PREFIX, 'Floodgates:');	// use custom prefix on all keys

		$this->fetchLastLeakTimeToObject($UID, time());
	}

	private function fetchLastLeakTimeToObject($UID, $fallback_time) {
		$last_leak_time = $this->storage->get(self::PREFIX_LAST_LEAK_TIME.$UID);

		if(!$last_leak_time) {
			return $this->bucket_last_leak_time = $fallback_time;
		}
		
		return $this->bucket_last_leak_time = $last_leak_time;
	}

	// gets the last leak time from the storage or will set it as current time and store it in the DB
	private function setLastLeakTime($UID, $time) {
		$this->bucket_last_leak_time = $time;
		$this->storage->set(self::PREFIX_LAST_LEAK_TIME.$UID, $this->bucket_last_leak_time);
	}

	// push out the drops that have excceded the TTL of the bucket
	public function leak($UID) {
		$elapsed_time = time() - $this->bucket_last_leak_time;
		$drops_to_leak = $elapsed_time * ($this->bucket_capacity / $this->bucket_leak_rate);

		
		if($drops_to_leak < 1) {
			return;
		}

		$drops_to_leak = round($drops_to_leak);
var_dump($drops_to_leak);
		// ensure the bucket drop count doesnt go negaitve
		if ($drops_to_leak > $this->bucket_capacity) {
			$drops_to_leak = $this->bucket_capacity;
		}

		$r=$this->storage->decrBy($UID, $drops_to_leak);

		$this->setLastLeakTime($UID, time());
	}

	// sets the current time as the last time the bucket recived a drop
	private function updateLastDropTime() {
		$this->bucket_last_leak_time = time();
	}

	// attempts to add drop to bucket, if bucket is not overflowing it returns true
	// if overflowing, call the leak method to clear spce and try again 
	// still no space for the new droplet, return false = rate limited
	public function addDrop($UID, $drops=1) {
		$size = $this->storage->incrBy($UID, $drops);
		if ($size <= $this->bucket_capacity) {
			return true;
		}
		// the bucket is overflowing,

		// leak what can be leaked according to TTL
		$this->leak($UID);
		$new_size = $this->getDropCount($UID);
		
		if ($new_size <= $this->bucket_capacity) {
			return true;
		}

		// the bucket is STILL overflowing, decremnt the one we have added
		$r=$this->storage->decrBy($UID, $drops);
		//var_dump($r);
		return false;
	}

	public function getDropCount($UID) {
		return (int) $this->storage->get($UID);
	}

	public function capcityLeft() {

	}

	public function capcityused() {

	}

	public function isFull() {
		//return ();
	}
}

$UID = md5('127.0.0.7');

// 10 requests in the bucket
// requests leaking at 1 per second 
// = 10 requests in every ten second window
// 
// 3 requests per 5 second window
$Floodgates = new Floodgates($UID, 3, 5);
/*var_dump(
	$Floodgates->addDrop($UID));*/

var_dump(
	$Floodgates->addDrop($UID),
	$Floodgates->addDrop($UID),
	$Floodgates->addDrop($UID),
	$Floodgates->addDrop($UID),
	$Floodgates->addDrop($UID),
	$Floodgates->addDrop($UID) // this one should fail
	);
