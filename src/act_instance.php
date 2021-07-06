<?php

require_once 'src/instance.php';

$client = new HttpClient();
$response = $client->request('GET', 'https://httpbin.org', [
  'xray' => ['annotations' => ['method' => 'instance']]
]);

sleep(1);

// this will not show in laravel
closeCollector();
