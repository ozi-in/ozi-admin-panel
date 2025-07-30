<?php

namespace App\Services;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use App\Models\Order;
class OrderConnector
{
    protected $baseUrl;
    protected $apiKey;
     protected $pidgeapiKey;
    protected $endpoints;

    public function __construct()
    {
        $this->baseUrl = config('services.order_api.base_url', 'http://localhost:3000');
        $this->apiKey = config('services.order_api.api_key');
        $this->pidgeapiKey = config('services.order_api.pgi_api_key');
        // Define supported endpoints
        $this->endpoints = [
            'createOrder' => '/orders/createOrder',
            'cancelOrder' => '/orders/cancelOrder',
               'getOrder'    => '/orders/getOrder/',
             //     'cancelOrder' => '/orders/cancelOrder/', // add this line
            // add more endpoints here
        ];
    }

    public function getOrderByInvoiceId(string $invoiceId)
{
    $url = $this->baseUrl . $this->endpoints['getOrder'] . $invoiceId;

    $response = Http::withHeaders([
        'api-key' => $this->apiKey,
    ])->get($url);

    if (!$response->successful()) {
        throw new \Exception("API Error: " . $response->body());
    }

    return $response->json();
}
public function cancelOrderByInvoiceId(string $invoiceId)
{
  $url = $this->baseUrl . $this->endpoints['cancelOrder'] . "/".$invoiceId;

    $response = Http::withHeaders([
        'api-key' => $this->apiKey,
    ])->get($url); // assuming it's a GET request as per your Node.js code

    if (!$response->successful()) {
        throw new \Exception("API Error: " . $response->body());
    }

    return $response->json();
}

    public function call(string $endpointKey, array $data)
{
    if (!isset($this->endpoints[$endpointKey])) {
        throw new \InvalidArgumentException("Endpoint '{$endpointKey}' is not defined.");
    }

    $url = $this->baseUrl . $this->endpoints[$endpointKey];

    $response = Http::withHeaders([
        'api-key' => $this->apiKey,
    ])->post($url, $data);

    if (!$response->successful()) {
        throw new \Exception("API Error: " . $response->body());
    }

    // Decode the response only after ensuring it's successful
    $responseData = $response->json();
   Log::info("API Response for endpoint [{$endpointKey}]:", [
        'url' => $url,
        'request' => $data,
        'response' => $response->json(),
        'status' => $response->status(),
    ]);

    
    // Update Order table if endpoint is createOrder
    if (
        $endpointKey === 'createOrder' &&
        isset($responseData['data']['data']['OrderID'], $responseData['data']['data']['InvoiceID'], $data['orderNumber'])
    ) {
        Order::where('id', $data['orderNumber'])->update([
            'EcommOrderID'   => $responseData['data']['data']['OrderID'] ?? null,
            'EcommInvoiceID' => $responseData['data']['data']['InvoiceID'] ?? null,
        ]);
    }

    return $responseData;
}

public function getPidgeOrderStatus(string $orderId)
{
    $url = $this->baseUrl . "/pidge/order/{$orderId}/status";

    try {
        $response = Http::withHeaders([
        'api-key' => $this->apiKey,
    ])->withToken($this->pidgeapiKey)
            ->timeout(10)
            ->get($url);

        if (!$response->successful()) {
            Log::error('Pidge API Error', [
                'url' => $url,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            return null;
        }

        return $response->json();
    } catch (\Exception $e) {
        Log::error('Pidge API Exception', [
            'url' => $url,
            'message' => $e->getMessage(),
        ]);
        return null;
    }
}
}
