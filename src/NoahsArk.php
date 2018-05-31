<?php

namespace DavidFricker\Floodgates;

use Redis;

/**
 * A concurrent request limiter
 * makes use of in-memory NoSQL storage for fast operation
 */
class NoahsArk {
	/**
	 * Size of ark - amount of requests permitted concurrently
	 * 
	 * @var int
	 */
	private $ark_capacity;

	/**
	 * How many requests are there currently active
	 * 
	 * @var int
	 */
	private $occupants_on_board;

	/**
	 * Handel to Redis in-memory storage
	 * 
	 * @var Object
	 */
	private $storage;

	/**
	 * Prefix for the storage of the $occupants_on_board in Redis
	 */
	const PREFIX_ARK_OOB = 'NoahsArkOOB';

	/**
	 * The unique ID for the ark to be manipulated
	 * Common values include IP address or user ID
	 * 
	 * @var string
	 */
	private $UID;

	/**
	 * Creates a new Noahs Ark object
	 * Automatically populates the current occupancy of the ark
	 * from storage if such records exist for the given $UID value
	 * 
	 * @param string $UID Unique ID of the bucket, commonly a hash of the user's IP address
	 * @param integer $ark_capacity Total number of concurrent connections allowed for given UID
	 */
	public function __construct($UID, $ark_capacity) {
		$this->ark_capacity = $ark_capacity;
		$this->UID = $UID;

		$this->initStorage();
		$this->fetchCurrOccupancyToObject();
	}

	/**
	 * Connection to Redis and save the handle in the object for later use
	 * 
	 * @return void
	 */
	private function initStorage() {
		$this->storage = new Redis();
		$this->storage->pconnect('127.0.0.1', 6379);
		$this->storage->setOption(Redis::OPT_PREFIX, 'NoahsArk:');
	}

	/**
	 * Get the last drop leak time from the storage or default to current time for a new bucket
	 * 
	 * @return void
	 */
	private function fetchCurrOccupancyToObject() {
		$occupancy = $this->storage->get(self::PREFIX_ARK_OOB . $this->UID);

		if (!$occupancy) {
			$this->setOccupancy(0);
		}
		
		$this->occupants_on_board = $occupancy;
	}

	/**
	 * Sets the occupancy of the ark i.e. how many requests are there currently
	 * 
	 * @param integer $time seconds since Unix epoch that the leaked droplets were calculated
	 */
	private function setOccupancy($occupancy) {
		// could move this to the destructor
		$this->storage->set(
			self::PREFIX_ARK_OOB . $this->UID, 
			$this->occupants_on_board = $occupancy
		);
	}

	/**
	 * Fetches the amount of requests in the Ark
	 * 
	 * @return integer request count in the bucket
	 */
	private function getOccupancy() {
		return (int) $this->storage->get(self::PREFIX_ARK_OOB . $this->UID);
	}

	/**
	 * Adds a new request 'occupant' to the ark
	 * 
	 * @return void
	 */
	public function add($requests = 1) {
		$size = $this->storage->incrBy($this->UID, $requests);
		if ($size <= $this->ark_capacity) {
			$this->occupants_on_board = $size;
			return true;
		}

		$this->storage->decrBy($this->UID, $drops);
		
		return false;
	}
	
	/**
	 * Attempts to add a new request 'occupant' to the ark
	 * if there is capacity left in the Ark, true is returned
	 * If the request must be turned away/rejected false is returned
	 * 
	 * @param integer $requests optional, enables the registering of more than 1 request at once, useful for more expensive requests
	 *
	 * @return boolean see function desc for details
	 */
	public function remove($requests = 1) {
		$size = $this->storage->decrBy($this->UID, $requests);
		if ($size < 0) {
			$size = 0;
		}

		$this->occupants_on_board = $size;
		
		return true;
	}

	/**
	 * Fetches the capacity for more drops of the bucket
	 * i.e. the amount of requests remaining before rate limiting
	 * 
	 * @return integer amount of requests left before rate limiting
	 */
	/*public function capacityLeft() {
		$this->leak();
		return abs($this->bucket_capacity - $this->getDropCount());
	}

	/**
	 * Fetches the capacity of the bucket that has been used
	 * i.e. the amount of requests that have been recorded in the current rate limit window
	 * 
	 * @return integer [description]
	 * /
	public function capacityUsed() {
		$this->leak();
		return $this->getDropCount();
	}

	/**
	 * Fetches the state of the bucket
	 * 
	 * @return boolean true if the bucket is full and any further requests will be rate limited
	 * /
	public function isFull() {
		$this->leak();
		return ($this->getDropCount() >= $this->bucket_capacity);
	}

	/**
	 * Empties all drops from bucket, i.e. resets the rate limit
	 * 
	 * @return void
	 * /
	public function flush() {
		$this->storage->set($this->UID, 0);
	}*/
}
