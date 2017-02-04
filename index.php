<?php
/* Run command: composer install */
require 'vendor/autoload.php';

use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Subscriber\Oauth\Oauth1;

$baseUrl = '';

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
            'subscription_option' => [ /* Allowed options */
                'is_fulfilling' => 1,        // required
                'option' => 'subscription',  // optional
                'interval' => '',            // optional
                'subscription_id' => ''      // optional
            ],
            'custom_options' => [],              // optional, cannot be empty
            'configurable_item_options' => [],   // optional, cannot be empty
            'bundle_options' => []               // optional, cannot be empty
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
$middlewareOauth1 = new Oauth1([
    'consumer_key'    => $consumerKey,
    'consumer_secret' => $consumerSecret,
    'token'           => $accessToken,
    'token_secret'    => $accessTokenSecret
]);
$handlerStack->push($middlewareOauth1);

$client = new Client([
    'base_uri' => $baseUrl,
    'handler'  => $handlerStack,
    RequestOptions::HTTP_ERRORS => false,
    RequestOptions::AUTH => 'oauth'
]);


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
    echo $e->getMessage();
}
