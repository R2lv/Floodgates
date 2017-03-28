# Floodgates
An intelligent rate limiting class
Written in PHP

## Usage example 
### Create a rate limiter
The following example creates a rate limiting bucket persisted in volitile memory identified by the $UID, which allows 5 requests in any 60 second window.

```PHP

$UID = md5('127.0.0.1');
$bucket_capcity = 5;
$leak_rate = 60;
$Floodgates = new Floodgates($UID, $bucket_capacity, $leak_rate);
```
### Basic rate limiter usage

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
