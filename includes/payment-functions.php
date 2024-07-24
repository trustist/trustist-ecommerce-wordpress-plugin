<?php
if ( ! defined( 'ABSPATH' ) ) exit; 

use GuzzleHttp\Client;
use Shawm11\Hawk\Client\Client as HawkClient;
use Shawm11\Hawk\Client\ClientException as HawkClientException;

$trustist_payments_http_client = new Client();

function trustist_payment_create_hawk_client()
{
    return new HawkClient();
}

function trustist_payment_get_credentials($test = false)
{
    if ($test === true)
        return [
            'id' => get_option('trustist_payments_sandbox_public_key'),
            'key' => get_option('trustist_payments_sandbox_private_key'),
            'algorithm' => 'sha256',
        ];

    return [
        'id' => get_option('trustist_payments_public_key'),
        'key' => get_option('trustist_payments_private_key'),
        'algorithm' => 'sha256',
    ];
}

function trustist_payment_send_request($method, $url, $payload = null, $test = false)
{
    global $trustist_payments_http_client;

    $hawkClient = trustist_payment_create_hawk_client();
    $options = [
        'credentials' => trustist_payment_get_credentials($test),
        'ext' => null,
    ];

    if ($payload !== null) {
        $options['contentType'] = 'application/json';
        $options['payload'] = wp_json_encode($payload);
    }

    $base_uri = $test === true ? 'https://api-sandbox.trustistecommerce.com' : 'https://api.trustistecommerce.com';
    trustist_payment_write_log($base_uri . $url);

    $result = $hawkClient->header($base_uri . $url, $method, $options);

    $header = $result['header'];

    $response = $trustist_payments_http_client->request($method, $base_uri . $url, [
        'headers' => [
            'Authorization' => $header,
            'Content-Type' => 'application/json'
        ],
        'body' => $payload !== null ? wp_json_encode($payload) : null
    ]);

    return json_decode($response->getBody(), true);
}

function trustist_payment_payer_url($paymentId, $test = false)
{
    $url = $test === true ? 'https://payer-sandbox.trustisttransfer.com' : 'https://payer.trustistecommerce.com';
    return $url . "/pay/{$paymentId}";
}

function trustist_payment_receipt_url($paymentId, $test = false)
{
    $url = $test === true ? 'https://payer-sandbox.trustisttransfer.com' : 'https://payer.trustistecommerce.com';
    return $url . "/receipt/{$paymentId}";
}

function trustist_payment_create_payment(TrustistPaymentRequest $request, $test = false)
{
    $data = $request->toArray();

    $url = '/v1/payments';
    return trustist_payment_send_request('POST', $url, $data, $test);
}

function trustist_payment_create_subscription(TrustistStandingOrderRequest $request, $test = false)
{
    $data = $request->toArray();

    $url = '/v1/standingorders';
    return trustist_payment_send_request('POST', $url, $data, $test);
}

function trustist_payment_get_payment($paymentId, $test = false)
{
    $url = "/v1/payments/{$paymentId}";
    return trustist_payment_send_request('GET', $url, null, $test);
}

function trustist_payment_get_subscription($subscriptionId, $test = false)
{
    $url = "/v1/standingorders/{$subscriptionId}";
    return trustist_payment_send_request('GET', $url, null, $test);
}

function trustist_payment_get_merchant($test = false)
{
    $url = '/v1/merchants';
    return trustist_payment_send_request('GET', $url, null, $test);
}

function trustist_payment_write_log( $data ) {
    if ( true === WP_DEBUG ) {
        if ( is_array( $data ) || is_object( $data ) ) {
            error_log( print_r( $data, true ) );
        } else {
            error_log( $data );
        }
    }
}

class TrustistPaymentRequest
{
    private $amount;
    private $reference;
    private $returnUrl;
    private $cancelUrl;
    private $description;
    private $customerDetails;
    private $payerEmail;

    public function __construct(
        $amount,
        $reference, 
        $description, 
        $customerDetails,
        $payerEmail,
        $returnUrl, 
        $cancelUrl = null)
    {
        $this->amount = $amount;
        $this->reference = $reference;
        $this->returnUrl = $returnUrl;
        $this->cancelUrl = $cancelUrl;
        $this->description = $description;
        $this->customerDetails = $customerDetails;
        $this->payerEmail = $payerEmail;
    }

    public function toArray()
    {
        return [
            'amount' => $this->amount,
            'reference' => $this->reference,
            'returnUrl' => $this->returnUrl,
            'cancelUrl' => $this->cancelUrl,
            'description' => $this->description,
            'customerDetails' => $this->customerDetails,
            'payerEmail' => $this->payerEmail,
        ];
    }
}

class TrustistStandingOrderRequest
{
    private $amount;
    private $reference;
    private $returnUrl;
    private $cancelUrl;
    private $description;
    private $payerName;
    private $payerBusinessName;
    private $frequency;
    private $startDate;
    private $numberOfPayments;

    public function __construct(
        $amount,
        $reference,
        $description,
        $frequency,
        $startDate,
        $numberOfPayments,
        $payerName,
        $payerBusinessName,
        $returnUrl,
        $cancelUrl = null
    ) {
        $this->amount = $amount;
        $this->reference = $reference;
        $this->returnUrl = $returnUrl;
        $this->cancelUrl = $cancelUrl;
        $this->description = $description;
        $this->payerName = $payerName;
        $this->payerBusinessName = $payerBusinessName;
        $this->frequency = $frequency;
        $this->startDate = $startDate;
        $this->numberOfPayments = $numberOfPayments;
    }

    public function toArray()
    {
        return [
            'amount' => $this->amount,
            'reference' => $this->reference,
            'returnUrl' => $this->returnUrl,
            'cancelUrl' => $this->cancelUrl,
            'description' => $this->description,
            'payerName' => $this->payerName,
            'payerBusinessName' => $this->payerBusinessName,
            'frequency' => $this->frequency,
            'startDate' => $this->startDate,
            'numberOfPayments' => $this->numberOfPayments,
        ];
    }
}
?>