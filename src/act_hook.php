<?php

require_once 'src/hook.php';

use GuzzleHttp\Client;

$client = new Client([
  'base_uri' => 'https://httpbin.org',
  'handler' => createHandler(['annotations' => ['method' => 'hook']]),
]);
$response = $client->get('get');

sleep(1);

// this will not show in laravel
closeCollector();
