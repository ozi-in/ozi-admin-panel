<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Order;
use Illuminate\Support\Facades\Log;

class PidgeWebhookController extends Controller
{
    public function handle(Request $request)
{
    Log::info('Pidge Webhook Received', [$request->all()]);

    $data = $request->all();

    if (!isset($data['reference_id'])) {
        return response()->json(['message' => 'Invalid payload'], 400);
    }

    $order = Order::where('id', $data['reference_id'])->first();

    if (!$order) {
        return response()->json(['message' => 'Order not found'], 404);
    }

    $externalStatus = $data['status'] ?? null;
    $fulfillmentStatus = $data['fulfillment']['status'] ?? null;

    // Map external status to internal status
    $newStatus = null;

    if ($externalStatus === 'fulfilled') {
        $newStatus = 'confirmed';
    }

    if ($fulfillmentStatus === 'CREATED') {
        $newStatus = 'confirmed';
    } elseif ($fulfillmentStatus === 'OUT_FOR_DELIVERY') {
        $newStatus = 'picked_up';
    } elseif ($fulfillmentStatus === 'REACHED_PICKUP') {
        $newStatus = 'picked_up';
    } elseif ($fulfillmentStatus === 'DELIVERED') {
        $newStatus = 'delivered';
    } elseif ($fulfillmentStatus === 'UNDELIVERED') {
        $newStatus = 'undelivered';
    }

    if ($newStatus) {
        $order->order_status = $newStatus;
        $order->save();
        Log::info("Order ID {$order->id} updated to {$newStatus}");
    } else {
        Log::info("Order ID {$order->id} - no status change needed");
    }

    return response()->json(['message' => 'Webhook processed'], 200);
}

}

?>