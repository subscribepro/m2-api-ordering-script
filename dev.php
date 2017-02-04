<?php

require 'vendor/autoload.php';

use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Subscriber\Oauth\Oauth1;
use GuzzleHttp\MessageFormatter;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Cookie\SetCookie;
use Monolog\Logger;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Formatter\LineFormatter;

$domain = '';
$baseUrl = "http://{$domain}/";

$consumerKey = '';
$consumerSecret = '';
$accessToken = '';
$accessTokenSecret = '';

$customerId = '';
$product = [
    'sku' => '',
    'qty' => '',
    'product_option' => [
        'extension_attributes' => [
            'subscription_option' => [
                'is_fulfilling' => 1
            ]
        ]
    ]
];
$addressInformation = [
    'billing_address' => [
        'firstname' => '',
        'lastname' => '',
        'company' => '',
        'street' => ['', ''],
        'city' => '',
        'region_code' => '',
        'postcode' => '',
        'country_id' => '',
        'telephone' => ''
    ],
    'shipping_address' => [
        'firstname' => '',
        'lastname' => '',
        'company' => '',
        'street' => ['', ''],
        'city' => '',
        'region_code' => '',
        'postcode' => '',
        'country_id' => '',
        'telephone' => ''
    ],
    'shipping_method_code' => '',
    'shipping_carrier_code' => ''
];
$payment = [
    'method' => 'subscribe_pro_vault',
    'additional_data' => [
        'profile_id' => ''
    ]
];
$coupon = '';


$handlerStack = HandlerStack::create();
$clientConfig = [
    'base_uri' => $baseUrl,
    'handler'  => $handlerStack,
    RequestOptions::HTTP_ERRORS => false,
    RequestOptions::AUTH => 'oauth'
];

/*** -------------------------------- ***/
$fileName = 'log/magento-api.log';
$lineFormat = "[%datetime%] %channel%.%level_name%: %message%\n";
$messageFormat = "{method} - {uri}\nRequest body: {req_body}\n{code} {phrase}\nResponse body: {res_body}\n{error}\n";

$logHandler = new RotatingFileHandler($fileName);
$logHandler->setFormatter(new LineFormatter($lineFormat, null, true));
$handlerStack->push(Middleware::log(new Logger('Logger', [$logHandler]), new MessageFormatter($messageFormat)), 'logger');
/*** -------------------------------- ***/
$xDebug = SetCookie::fromString('');
$xDebug->setName('XDEBUG_SESSION');
$xDebug->setValue('PHPSTORM');
$xDebug->setDomain($domain);

$cookieJar = new CookieJar();
$cookieJar->setCookie($xDebug);
$clientConfig[RequestOptions::COOKIES] = $cookieJar; // comment out this line if XDEBUG is not needed
/*** -------------------------------- ***/

$middlewareOauth1 = new Oauth1([
    'consumer_key'    => $consumerKey,
    'consumer_secret' => $consumerSecret,
    'token'           => $accessToken,
    'token_secret'    => $accessTokenSecret
]);
$handlerStack->push($middlewareOauth1);

$client = new Client($clientConfig);

try {
    /* \Magento\Quote\Api\CartManagementInterface::createEmptyCartForCustomer*/
    $response = $client->post("rest/V1/customers/{$customerId}/carts");
    $cartId = processResponse($response);
    printResult('Cart ID', $cartId);

    cleanQuoteItems($client, $cartId);

    /* \Magento\Quote\Api\CartItemRepositoryInterface::save */
    $response = $client->post("rest/V1/carts/{$cartId}/items", [RequestOptions::QUERY => ['cartItem' => array_merge($product, ['quote_id' => $cartId])]]);
    $cartItems = processResponse($response);
    printResult('Cart items', $cartItems);

    /* \Magento\Checkout\Api\ShippingInformationManagementInterface::saveAddressInformation */
    $response = $client->post("rest/V1/carts/{$cartId}/shipping-information", [RequestOptions::QUERY => ['addressInformation' => $addressInformation]]);
    $paymentDetails = processResponse($response);
    printResult('Payment details', $paymentDetails);

    $response = $client->delete("rest/V1/carts/{$cartId}/coupons");
    $response = processResponse($response);
    printResult('Deleting coupon', $response);
    if ($coupon) {
        $response = $client->put("rest/V1/carts/{$cartId}/coupons/{$coupon}");
        $response = processResponse($response, true);
        printResult("Applying coupon '{$coupon}'", $response);
    }

    /* \Magento\Quote\Api\CartManagementInterface::placeOrder */
    $response = $client->put("rest/V1/carts/{$cartId}/order", [RequestOptions::QUERY => ['paymentMethod' => $payment]]);
    $orderData = processResponse($response);
    printResult('Real order ID', $orderData);
} catch (\Exception $e) {
    echo 'Exception: ' . $e->getMessage();
}
