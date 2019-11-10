<?php
// Load dependencies using composer generated autoload script
require 'vendor/autoload.php';

use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Subscriber\Oauth\Oauth1;

$baseUrl = '';

$consumerKey       = '';
$consumerSecret    = '';
$accessToken       = '';
$accessTokenSecret = '';

$orderId = '';

$handlerStack     = HandlerStack::create();
$middlewareOauth1 = new Oauth1(
    [
        'consumer_key'    => $consumerKey,
        'consumer_secret' => $consumerSecret,
        'token'           => $accessToken,
        'token_secret'    => $accessTokenSecret,
    ]
);
$handlerStack->push($middlewareOauth1);

$client = new Client(
    [
        'base_uri'                  => $baseUrl,
        'handler'                   => $handlerStack,
        RequestOptions::HTTP_ERRORS => false,
        RequestOptions::AUTH        => 'oauth',
    ]
);


try {
    $response = $client->get("rest/V1/orders/{$orderId}");
    print_r($response);
    print_r((string) $response->getBody());

    $orderInfo = processResponse($response);
    printResult('Order Info', $orderInfo);

} catch (\Exception $e) {
    echo $e->getMessage();
}
