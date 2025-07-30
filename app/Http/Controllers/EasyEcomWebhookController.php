<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class EasyEcomWebhookController extends Controller
{
    public function shippingAssigned(Request $request)
    {
        // $expectedToken = env('EASYECOM_WEBHOOK_TOKEN');
        // $receivedToken = $request->header('Access-Token');

        // if ($receivedToken !== $expectedToken) {
        //     Log::warning('Unauthorized webhook attempt', [
        //         'received_token' => $receivedToken,
        //     ]);
        //     return response()->json(['error' => 'Unauthorized'], 401);
        // }

        // Log or process the webhook data
        $payload = $request->all();
        Log::info('EasyEcom Shipping Assigned Webhook', $payload);

        // TODO: Add logic to trigger Pidge API here and save tracking number

        return response()->json(['success' => true]);
    }
}
