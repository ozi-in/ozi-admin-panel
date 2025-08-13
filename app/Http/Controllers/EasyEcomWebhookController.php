<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use DB;
use App\Models\PaymentRequest;
use App\Models\Order;
class EasyEcomWebhookController extends Controller
{
    public function shippingAssigned(Request $request)
    {
        // $expectedToken = env('EASYECOM_WEBHOOK_TOKEN');
        // $receivedToken = $request->header('Access-Token');
        
        // if ($receivedToken git!== $expectedToken) {
        //     Log::warning('Unauthorized webhook attempt', [
        //         'received_token' => $receivedToken,
        //     ]);
        //     return response()->json(['error' => 'Unauthorized'], 401);
        // }
        
        // Log or process the webhook data
        $data = $request->all();
        $orderData = is_array($data) ? $data[0] : $data;
        
        $invoiceId = $orderData['reference_code'] ?? null;
        $awbNumber = $orderData['awb_number'] ?? null;
        DB::table('orders')
        ->where('id', $invoiceId)
        ->update(['awb_number' => $awbNumber]);
        
        // Optional: log the incoming payload
       // Log::info('EasyEcom Webhook: Shipping Assigned', $orderData);
        if (!$invoiceId || !$awbNumber) {
            return response()->json(['success' => false, 'message' => 'Missing invoice_id or awb_number'], 400);
        }
        
      //  Log::info('EasyEcom Shipping Assigned Webhook', $data);
        
        // TODO: Add logic to trigger Pidge API here and save tracking number
        
        return response()->json(['success' => true]);
    }


    public function Getlogs(Request $request)
{
    $payload = json_decode($request->getContent(), true);
    Log::info('Razorpay Webhook Received', ['payload' => $payload]);

    try {
        if (
            isset($payload['event']) && 
            $payload['event'] === 'payment.captured' && 
            isset($payload['payload']['payment']['entity'])
        ) {
            $paymentEntity = $payload['payload']['payment']['entity'];

            $paymentId = $paymentEntity['id'] ?? null; // Razorpay payment ID
            $paymentMethod = $paymentEntity['method'] ?? null;

            if ($paymentId) {
                // 1️⃣ Find payment request
                $paymentRequest = PaymentRequest::where('transaction_id', $paymentId)->first();

                if ($paymentRequest) {
                    $paymentRequest->is_paid = 1;
                    $paymentRequest->payment_method = 'razor_pay';
                    $paymentRequest->save();
                    // 2️⃣ Find related order
                    $order = Order::find($paymentRequest->attribute_id);

                    if ($order) {
                        // 3️⃣ Update order
                        $order->payment_status = 'paid';
                        $order->payment_method = 'razor_pay';
                        $order->save();

                        Log::info("Order {$order->id} marked paid via webhook");
                    } else {
                        Log::warning("No order found for payment request: {$paymentRequest->id}");
                    }
                } else {
                    Log::warning("No payment request found for payment ID: {$paymentId}");
                }
            }
        }
    } catch (\Exception $e) {
        Log::error('Error processing Razorpay webhook', ['error' => $e->getMessage()]);
    }

    return response()->json(['success' => true]);
}


}
