# Floodgates
This rate limiting class implements the 'leaky bucket' algorithm. Due to the nature of rate limiting standard file based storage is unsuited to the task. For this reason, Floodgates makes use of Redis NoSQL storage that lives in volatile main memory. This means that you are required to have Redis installed to use Floodgates.

## Install
Using composer

`composer require DavidFricker/Floodgates`

## Usage example 
### Create a rate limiter
The following example creates a rate limiting bucket persisted in volatile memory identified by the $UID, which allows 5 requests in any 60 second window.

```PHP
$UID = md5('127.0.0.1');
$bucket_capcity = 5;
$leak_rate = 60;
$Floodgates = new Floodgates($UID, $bucket_capacity, $leak_rate);
```
### Basic rate limiter usage
Once you have created a rate limiter object as directed in the previous section simply call the `addDrop` method and check its return value. If the function returns true then the UID has not exceeded his or her allowed limit and so you may continue.

```PHP
if (!$Floodgates->addDrop()) {
  die('Rate limit exceeded');
}

// perform the the task that would otherwise be rate limited here

```

### Advanced rate limiter usage
Some applications will require that a single HTTP call will result in an increment greater than one to the rate limiter, this is often the case when more expensive operations are being performed, such as rendering an image using PHP GD. 

To increase the drop count by more than one simply pass the integer you wish to increment by to the `addDrop` call as shown below.

```PHP
$drops = 3;
if (!$Floodgates->addDrop($drops)) {
  die('Rate limit exceeded');
}

// perform the the task that would otherwise be rate limited here

```
## Recommendations
Consider enabling a swap file to ensure your processes are not killed by the system if you were to receive many requests from differing UIDs.

## Methods
### addDrop
`addDrop($drops = 1)`
Increases the drop count in the bucket. In real terms this mean it decrements the remaining requests possible in the current window of time.
#### returns
`boolean` - `true` when the UID has enough remaining requests to fulfil this request, `false` when the request should be rejected due to exceeding the rate limit


### capacityLeft
`capacityLeft()`
Fetches the number of drops that can be added to the bucket before it overflows.
#### returns
`int` - An integer representing the remaining requests that can be performed before incurring a rate limit


### capacityUsed
`capacityUsed()`
Fetches the amount of drops currently in the bucket.
#### returns
`int` - An integer representing the number of requests that have been performed in the current time window


### isFull
`isFull()`
Fetches the state of the bucket's remaining capacity
#### returns
`boolean` - `true` when a subsequent request to `addDrop` would return false, i.e. the request limit has been achieved


### flush
`flush()`
Reset the bucket contents, i.e. empty all drops from bucket.
#### returns
void

## License
Released under the MIT license.

