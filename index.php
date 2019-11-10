<?php
// Load dependencies using composer generated autoload script
require 'vendor/autoload.php';

use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Subscriber\Oauth\Oauth1;
use GuzzleHttp\Middleware;
use GuzzleHttp\MessageFormatter;
use Monolog\Logger;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Formatter\LineFormatter;


// ----------------------------------------------------------------------------
// Script Configuration Below \/
// ----------------------------------------------------------------------------

// If you need extra logging of request/response make this true
$saveDebuggingLogs = false;

// Base URL for the Magento 2 API
$baseUrl = 'https://www.yourmagentostore.com/';

// Magento 2 API Credentials
$consumerKey       = '';
$consumerSecret    = '';
$accessToken       = '';
$accessTokenSecret = '';

// Details for the customer, products, etc to order
$customerId = '';
$products   = [
    [
        'sku'            => '',
        'qty'            => '',
        'product_option' => [
            'extension_attributes' => [
                'subscription_option' => [ /* Allowed options */
                    'item_fulfils_subscription'   => true,
                    'item_added_by_subscribe_pro' => false,
                    'interval'                    => '',
                    'subscription_id'             => '',
                    'reorder_ordinal'             => '',
                    // 'fixed_price' => '',             // optional, but if included it cannot be empty
                    'next_order_date'             => '',
                ],
                // 'custom_options' => [],              // optional, but if included it cannot be empty
                // 'configurable_item_options' => [],   // optional, but if included it cannot be empty
                // 'bundle_options' => []               // optional, but if included it cannot be empty
            ],
        ],
    ],
];

// Billing and shipping information
$billingAddressSameAsShipping                = true;
$addressInformation                          = [
    'shipping_address' => [
        'firstname'   => '',
        'lastname'    => '',
        'street'      => [''],
        'city'        => '',
        'region_code' => '',
        'postcode'    => '',
        'country_id'  => '',
        'telephone'   => '',
    ],
];
$addressInformation['billing_address']       = ($billingAddressSameAsShipping === true)
    ? $addressInformation['shipping_address']
    : [ // Only set these if $billingAddressSameAsShipping is false
        'firstname'   => '',
        'lastname'    => '',
        'street'      => [''],
        'city'        => '',
        'region_code' => '',
        'postcode'    => '',
        'country_id'  => '',
        'telephone'   => '',
    ];
$addressInformation['shipping_carrier_code'] = '';
$addressInformation['shipping_method_code']  = '';

// Payment Information
$payment = [
    'method'          => 'subscribe_pro_vault',
    'additional_data' => [
        'profile_id' => '',
    ],
];
$coupon  = '';


// ----------------------------------------------------------------------------
// Script Starts Here
// ----------------------------------------------------------------------------

$handlerStack = HandlerStack::create();
$clientConfig = [
    'base_uri'                  => $baseUrl,
    'handler'                   => $handlerStack,
    RequestOptions::HTTP_ERRORS => false,
    RequestOptions::AUTH        => 'oauth',
];
if ($saveDebuggingLogs) {
    $fileName      = 'log/magento-api.log';
    $lineFormat    = "[%datetime%] %channel%.%level_name%: %message%\n";
    $messageFormat = "{method} - {uri}\nRequest body: {req_body}\n{code} {phrase}\nResponse body: {res_body}\n{error}\n";

    $logHandler = new RotatingFileHandler($fileName);
    $logHandler->setFormatter(new LineFormatter($lineFormat, null, true));
    $handlerStack->push(Middleware::log(new Logger('Logger', [$logHandler]), new MessageFormatter($messageFormat)), 'logger');
}

$middlewareOauth1 = new Oauth1(
    [
        'consumer_key'    => $consumerKey,
        'consumer_secret' => $consumerSecret,
        'token'           => $accessToken,
        'token_secret'    => $accessTokenSecret,
    ]
);
$handlerStack->push($middlewareOauth1);

$client = new Client($clientConfig);

try {
    /* \Magento\Quote\Api\CartManagementInterface::createEmptyCartForCustomer*/
    $response = $client->post("rest/V1/customers/{$customerId}/carts");
    $cartId   = processResponse($response);
    printResult('Cart ID', $cartId);

    cleanQuoteItems($client, $cartId);

    /* \Magento\Quote\Api\CartItemRepositoryInterface::save */
    foreach ($products as $product) {
        $response  = $client->post(
            "rest/V1/carts/{$cartId}/items",
            [RequestOptions::QUERY => ['cartItem' => array_merge($product, ['quote_id' => $cartId])]]
        );
        $cartItems = processResponse($response);
        printResult('Cart items', $cartItems);
    }

    // Remove any existing coupon, and if necessary, add one
    $response = $client->delete("rest/V1/carts/{$cartId}/coupons");
    $response = processResponse($response);
    printResult('Deleting coupon', $response);
    $couponToBeApplied = !empty($coupon);
    if ($couponToBeApplied) {
        $response = $client->put("rest/V1/carts/{$cartId}/coupons/{$coupon}");
        $response = processResponse($response, true);
        // If the coupon couldn't be applied before addresses were set, we'll try again afterwards
        $couponToBeApplied = $response === "The coupon code isn't valid. Verify the code and try again.";
        printResult("Applying coupon '{$coupon}'", $response);
    }

    /* \Magento\Checkout\Api\ShippingInformationManagementInterface::saveAddressInformation */
    $response       = $client->post(
        "rest/V1/carts/{$cartId}/shipping-information",
        [RequestOptions::QUERY => ['addressInformation' => $addressInformation]]
    );
    $addressDetails = processResponse($response);
    printResult('Address details', $addressDetails);

    if ($couponToBeApplied) {
        $response = $client->put("rest/V1/carts/{$cartId}/coupons/{$coupon}");
        $response = processResponse($response, true);
        printResult("Applying coupon '{$coupon}'", $response);
    }

    /* \Magento\Quote\Api\CartManagementInterface::placeOrder */
    $response  = $client->put("rest/V1/carts/{$cartId}/order", [RequestOptions::QUERY => ['paymentMethod' => $payment]]);
    $orderData = processResponse($response);
    printResult('Real order ID', $orderData);

} catch (\Exception $e) {
    echo $e->getMessage()."\n";
    echo $e->getTraceAsString()."\n";
}
