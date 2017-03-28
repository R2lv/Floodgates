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
if(!$Floodgates->addDrop()) {
  die('Rate limit exceeded');
}

// perform the the task that would otherwise be rate limited here

```

### Advhanced rate limiter usage
Some applications will require that a single HTTP call will result in an increment greater than one to the rate limiter, this is often the case when more expensive operations are being performed, such as rendering an image using PHP GD. 

To increase the drop count by more than one simply pass the integer you wish to increment by to the `addDrop` call as shown below.

```PHP
$drops = 3;
if(!$Floodgates->addDrop($drops)) {
  die('Rate limit exceeded');
}

// perform the the task that would otherwise be rate limited here

```
##Recommendations
Consider enabling a swap file to ensure your processes are not killed by the system if you were to receive many requests from differing UIDs.
