<?php

require_once 'vendor/autoload.php';

use GuzzleHttp\Handler\CurlHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use Napp\Xray\Collectors\SegmentCollector;
use Pkerrigan\Xray\Submission\DaemonSegmentSubmitter;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

$collector = new SegmentCollector();
$collector->tracer()
  ->setName('Evan testing')
  ->setUrl('localhost/some-example')
  ->setMethod('GET')
  ->begin(100);

function closeCollector()
{
  global $collector;
  $collector->tracer()->end()
    ->setResponseCode(200)
    ->submit(new DaemonSegmentSubmitter('localhost', '2000'));
}

function createHandler(?array $config = []): HandlerStack
{
  global $collector;
  $handler = new CurlHandler();
  $stack = HandlerStack::create($handler); // Wrap w/ middleware
  $name = $config['name'];

  $stack->push(Middleware::mapRequest(function (RequestInterface $request) use ($collector, &$name, $config) {
    $name = $name ?? $request->getUri()->__toString();
    $segment = $collector->addHttpSegment(
      $request->getUri()->__toString(),
      ['method' => $request->getMethod(), 'name' => $name],
    );
    foreach ($config['annotations'] as $key => $value) {
      $segment->addAnnotation($key, $value);
    }

    return $request;
  }), 'request');

  $stack->push(Middleware::mapResponse(function (ResponseInterface $response) use ($collector, &$name) {
    $collector->endHttpSegment($name, $response->getStatusCode());
    return $response;
  }), 'response');

  return $stack;
}
