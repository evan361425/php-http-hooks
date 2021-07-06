<?php

require_once 'vendor/autoload.php';

use GuzzleHttp\Client;
use GuzzleHttp\Middleware;
use GuzzleHttp\Handler\CurlHandler;
use GuzzleHttp\HandlerStack;
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

class HttpClient
{
  /** @var Client */
  protected $client;

  public function __construct(?array $config = [])
  {
    $this->client = new Client($config);
  }

  protected function createStack(?HandlerStack $stack, ?array $config = [])
  {
    if ($stack == null) {
      $handler = new CurlHandler();
      $stack = HandlerStack::create($handler);
    }

    $name = $config['name'];

    $stack->push(Middleware::mapRequest(function (RequestInterface $request) use (&$name, $config) {
      global $collector;
      $name = $name ?? $request->getUri()->__toString();
      $segment = $collector->addHttpSegment(
        $request->getUri()->__toString(),
        ['method' => $request->getMethod(), 'name' => $name],
      );
      foreach ($config['annotations'] as $key => $value) {
        $segment->addAnnotation($key, $value);
      }

      return $request;
    }), 'setup-xray-segment');

    $stack->push(Middleware::mapResponse(function (ResponseInterface $response) use (&$name) {
      global $collector;
      $collector->endHttpSegment($name, $response->getStatusCode());
      return $response;
    }), 'close-xray-segment');

    return $stack;
  }

  public function request($method, $uri, ?array $options = []): ResponseInterface
  {
    $options['handler'] = $this->createStack($options['handler'], $options['xray']);
    return $this->client->request($method, $uri, $options);
  }
}
