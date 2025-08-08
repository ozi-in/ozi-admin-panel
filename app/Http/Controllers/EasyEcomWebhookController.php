<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use DB;
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
       
        
        Log::info('Test', $data);
        
        // TODO: Add logic to trigger Pidge API here and save tracking number
        
        return response()->json(['success' => true]);
    }
}
