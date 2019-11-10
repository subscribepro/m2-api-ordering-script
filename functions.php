<?php

/**
 * @param \Psr\Http\Message\ResponseInterface $response
 * @param bool                                $graceful
 *
 * @return array|int|null
 * @throws Exception
 */
function processResponse($response, $graceful = false)
{
    if ($response->getStatusCode() < 300) {
        $body = (string) $response->getBody();

        return !empty($body) ? json_decode($body, true) : $response->getStatusCode();
    }
    $errorMessage = getErrorMessage($response);
    if ($graceful) {
        return $errorMessage;
    }
    throw new \Exception($errorMessage);
}

/**
 * @param \Psr\Http\Message\ResponseInterface $response
 *
 * @return string
 */
function getErrorMessage($response)
{
    $errorBody = json_decode((string) $response->getBody(), true);

    $message    = !empty($errorBody['message']) ? $errorBody['message'] : $response->getReasonPhrase();
    $parameters = !empty($errorBody['parameters']) ? $errorBody['parameters'] : [];

    foreach ($parameters as $name => $value) {
        $message = str_replace("%{$name}", $value, $message);
    }

    return $message;
}

/**
 * @param string $title
 * @param mixed  $result
 */
function printResult($title, $result)
{
    echo $title.": ";
    print_r($result);
    echo "\n";
}

/**
 * @param \GuzzleHttp\Client $client
 * @param int                $cartId
 */
function cleanQuoteItems($client, $cartId)
{
    $response  = $client->get("rest/V1/carts/{$cartId}/items");
    $cartItems = processResponse($response);
    if (!empty($cartItems)) {
        foreach ($cartItems as $cartItem) {
            $response = $client->delete("rest/V1/carts/{$cartId}/items/{$cartItem['item_id']}");
            $response = processResponse($response);
            printResult("Deleted {$cartItem['item_id']} cart item", $response);
        }
    }
}
