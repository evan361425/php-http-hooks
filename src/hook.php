<?php

require_once 'vendor/autoload.php';

use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Handler\CurlHandler;
use GuzzleHttp\Middleware;
use Napp\Xray\Collectors\SegmentCollector;
use Pkerrigan\Xray\Submission\DaemonSegmentSubmitter;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

$collector = new SegmentCollector();
$tracer = $collector->tracer()
  ->setName('Evan testing')
  ->setUrl('localhost/some-example')
  ->setMethod('GET')
  ->begin(100);

// sleep(1);

$handler = new CurlHandler();
$stack = HandlerStack::create($handler); // Wrap w/ middleware
$stack->push(Middleware::mapRequest(function (RequestInterface $request) use ($collector) {
  $collector->addHttpSegment('example', $request->getUri(), $request->getMethod())
    ->addAnnotation('example-header', 'some-value');
  return $request->withHeader('AA', 'BB');
}), 'request');
$stack->push(Middleware::mapResponse(function (ResponseInterface $response) use ($collector) {
  $collector->endHttpSegment('example', $response->getStatusCode());
  return $response->withHeader('CC', 'DD');
}), 'response');
$client = new Client(['base_uri' => 'https://httpbin.org', 'handler' => $stack,]);
$response = $client->get('get');

sleep(1);

$tracer->end()
  ->setResponseCode(200)
  ->submit(new DaemonSegmentSubmitter('localhost', '2000'));

// var_dump($response->getHeaders());
// Headers
// array(8) {
//   ["Date"]=>
//   array(1) {
//     [0]=>
//     string(29) "Tue, 06 Jul 2021 05:41:56 GMT"
//   }
//   ["Content-Type"]=>
//   array(1) {
//     [0]=>
//     string(16) "application/json"
//   }
//   ["Content-Length"]=>
//   array(1) {
//     [0]=>
//     string(3) "252"
//   }
//   ["Connection"]=>
//   array(1) {
//     [0]=>
//     string(10) "keep-alive"
//   }
//   ["Server"]=>
//   array(1) {
//     [0]=>
//     string(15) "gunicorn/19.9.0"
//   }
//   ["Access-Control-Allow-Origin"]=>
//   array(1) {
//     [0]=>
//     string(1) "*"
//   }
//   ["Access-Control-Allow-Credentials"]=>
//   array(1) {
//     [0]=>
//     string(4) "true"
//   }
//   ["CC"]=>
//   array(1) {
//     [0]=>
//     string(2) "DD"
//   }
// }

// echo $response->getBody();
// Body 
// {
//   "args": {}, 
//   "headers": {
//     "Aa": "BB", 
//     "Host": "httpbin.org", 
//     "User-Agent": "GuzzleHttp/7", 
//     "X-Amzn-Trace-Id": "Root=1-60e3ee1b-52ea61105012cf8f0323fda9"
//   }, 
//   "origin": "123.194.64.193", 
//   "url": "https://httpbin.org/get"
// }
