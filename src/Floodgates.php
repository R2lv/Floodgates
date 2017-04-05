<?php

namespace DavidFricker\Floodgates;

use Redis;

/**
 * A rate limiting class that implements the leaky bucket algorithm and 
 * makes use of in-memory NoSQL storage for fast operation
 */
class Floodgates {
	/**
	 * Size of bucket - amount of requests permitted
	 * 
	 * @var int
	 */
	private $bucket_capacity;

	/**
	 * Bucket leak rate - time taken for all the drops as defined
	 * in $bucket_capacity to leak from the bucket
	 * 
	 * @var int
	 */
	private $bucket_leak_rate;

	/**
	 * Defines in seconds since Unix epoch the last time the bucket 
	 * was 'leaked' - the last time the bucket was cleared of drops 
	 * that have surpassed their TTL
	 * 
	 * @var int
	 */
	private $bucket_last_leak_time;

	/**
	 * Handel to Redis in-memory storage
	 * 
	 * @var Object
	 */
	private $storage;

	/**
	 * Prefix for the storage of the $bucket_last_leak_time in Redis
	 */
	const PREFIX_LAST_LEAK_TIME = 'FoodgatesLLT';

	/**
	 * The unique ID for the bucket to be manipulated
	 * Common values include IP address or browser fingerprint
	 * 
	 * @var string
	 */
	private $UID;

	/**
	 * Creates a new leaky bucket style rate limiting object
	 * Automatically populates the last leak time and the bucket 
	 * state from storage if such records exist for the given $UID value
	 *
	 * Leak rate = time for the bucket to empty in seconds
	 * e.g. bucket of size 5 takes 1 minute to leak = 5 requests per 60 seconds
	 * $bucket_capacity = 5; $bucket_leak_rate = 60;
	 * 
	 * @param string $UID Unique ID of the bucket, commonly a hash of the user's IP address
	 * @param integer $bucket_capacity  Amount of requests allowed in the time frame as defined by $bucket_leak_rate
	 * @param integer $bucket_leak_rate Time it takes for all drops to leak from bucket
	 */
	public function __construct($UID, $bucket_capacity, $bucket_leak_rate) {
		$this->bucket_capacity = $bucket_capacity;
		$this->bucket_leak_rate = $bucket_leak_rate;
		$this->UID = $UID;

		$this->initStorage();
		$this->fetchLastLeakTimeToObject();
	}

	/**
	 * Connection to Redis and save the handle in the object for later use
	 * 
	 * @return void
	 */
	private function initStorage() {
		$this->storage = new Redis();
		$this->storage->pconnect('127.0.0.1', 6379);
		$this->storage->setOption(Redis::OPT_PREFIX, 'Floodgates:');
	}

	/**
	 * Get the last drop leak time from the storage or default to current time for a new bucket
	 * 
	 * @return void
	 */
	private function fetchLastLeakTimeToObject() {
		$last_leak_time = $this->storage->get(self::PREFIX_LAST_LEAK_TIME.$this->UID);

		if (!$last_leak_time) {
			$this->setLastLeakTime(time());
			//return $this->bucket_last_leak_time = time();
		}
		
		$this->bucket_last_leak_time = $last_leak_time;
	}

	/**
	 * Sets the last leak time in the object and persist it to storage
	 * 
	 * @param integer $time seconds since Unix epoch that the leaked droplets were calculated
	 */
	private function setLastLeakTime($time) {
		$this->bucket_last_leak_time = $time;

		// could move this to the destructor
		$this->storage->set(self::PREFIX_LAST_LEAK_TIME.$this->UID, $this->bucket_last_leak_time);

	}

	/**
	 * Fetches the amount of drops in the bucket
	 * 
	 * @return integer Drop count in the bucket
	 */
	private function getDropCount() {
		return (int) $this->storage->get($this->UID);
	}

	/**
	 * Calculates the drops that are in the bucket that have exceeded their TTL
	 * These drops are then 'leaked' from the bucket to make space for new drops
	 * 
	 * @return void
	 */
	public function leak() {
		$elapsed_time = time() - $this->bucket_last_leak_time;
		$drops_to_leak = $elapsed_time * ($this->bucket_capacity / $this->bucket_leak_rate);

		if ($drops_to_leak < 1) {
			return;
		}

		$drops_to_leak = round($drops_to_leak);

		// ensure the bucket drop count doesn't go negative
		if ($drops_to_leak > $this->bucket_capacity) {
			$drops_to_leak = $this->bucket_capacity;
		}

		$this->storage->decrBy($this->UID, $drops_to_leak);

		$this->setLastLeakTime(time());
	}

	
	/**
	 * Attempts to add a drop to the bucket, if the bucket is not overflowing it returns true 
	 * Automatically calls the leak method 
	 * 
	 * @param integer $drops optional, allows you to increment the number of drops added to the bucket by more then 1, useful for more expensive requests
	 *
	 * @return boolean true if the UID has not exceeded their rate limit, false if the UID has exceeded the rate limit
	 */
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

		// the bucket is STILL overflowing, decrement the one we have added
		$this->storage->decrBy($this->UID, $drops);
		
		return false;
	}

	/**
	 * Fetches the capacity for more drops of the bucket
	 * i.e. the amount of requests remaining before rate limiting
	 * 
	 * @return integer amount of requests left before rate limiting
	 */
	public function capacityLeft() {
		$this->leak();
		return abs($this->bucket_capacity - $this->getDropCount());
	}

	/**
	 * Fetches the capacity of the bucket that has been used
	 * i.e. the amount of requests that have been recorded in the current rate limit window
	 * 
	 * @return integer [description]
	 */
	public function capacityUsed() {
		$this->leak();
		return $this->getDropCount();
	}

	/**
	 * Fetches the state of the bucket
	 * 
	 * @return boolean true if the bucket is full and any further requests will be rate limited
	 */
	public function isFull() {
		$this->leak();
		return ($this->getDropCount() >= $this->bucket_capacity);
	}

	/**
	 * Empties all drops from bucket, i.e. resets the rate limit
	 * 
	 * @return void
	 */
	public function flush() {
		$this->storage->set($this->UID, 0);
	}
}
