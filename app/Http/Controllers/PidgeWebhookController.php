<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Models\Order;
use App\Models\User;
use App\Models\DeliveryMan;
use App\Models\DeliveryHistory;

class PidgeWebhookController extends Controller
{
    public function handle(Request $request)
    {
        Log::info('Pidge Webhook Received', [$request->all()]);

        $data = $request->all();
        $referenceId = $data['reference_id'] ?? null;

        if (!$referenceId) {
            return response()->json(['message' => 'Invalid payload'], 400);
        }

        $order = Order::find($referenceId);
        if (!$order) {
            return response()->json(['message' => 'Order not found'], 404);
        }

        $externalStatus = $data['status'] ?? null;
        $fulfillment     = $data['fulfillment'] ?? [];
        $fulfillmentStatus = $fulfillment['status'] ?? null;

        $newStatus = $this->mapStatus($externalStatus, $fulfillmentStatus, $order);

        // === If status updated ===
        if ($newStatus) {
            $order->order_status = strtolower($newStatus);

            if ($newStatus === 'delivered') {
                $this->handleDeliveredOrder($order);
            }

            $order->save();
            Log::info("Order ID {$order->id} updated to {$newStatus}");
        } else {
            Log::info("Order ID {$order->id} - no status change");
        }

        // === Save rider + location ===
        if (!empty($fulfillment['logs'])) {
            $this->handleRiderLocation($order, $fulfillment, $newStatus);
        }

        return response()->json(['message' => 'Webhook processed'], 200);
    }

    /**
     * Map external/fulfillment status to internal status.
     */
    private function mapStatus($externalStatus, $fulfillmentStatus, &$order)
    {
        $status = null;

        if ($externalStatus === 'CANCELLED') {
            $status = 'canceled';
        } elseif (in_array($externalStatus, ['COMPLETED', 'PENDING'])) {
            $status = strtolower($externalStatus);
        }

        if ($fulfillmentStatus) {
            switch ($fulfillmentStatus) {
                case 'CREATED':
                    $status = 'confirmed';
                    $order->confirmed = now();
                    break;
                case 'PICKED_UP':
                    $status = 'picked_up';
                    $order->picked_up = now();
                    break;
                case 'CANCELLED':
                    $status = 'canceled';
                    $order->canceled = now();
                    break;
                default:
                    $status = strtolower($fulfillmentStatus);
                    break;
            }
        }

        return $status;
    }

    /**
     * Handle delivered order updates.
     */
    private function handleDeliveredOrder(&$order)
    {
        $customer = User::find($order->user_id);
        if ($customer) {
            $customer->order_count = ($customer->order_count ?? 0) + 1;
            $customer->save();
        }
        $order->payment_status = 'paid';
        $order->delivered = now();
    }

    /**
     * Handle rider creation/location logging.
     */
    private function handleRiderLocation(&$order, $fulfillment, $newStatus)
    {
        $logs = array_reverse($fulfillment['logs']);
        $latestLocation = null;

        foreach ($logs as $log) {
            if (!empty($log['location']['latitude']) && !empty($log['location']['longitude'])) {
                $latestLocation = $log;
                break;
            }
        }

        if (!$latestLocation) {
            return;
        }

        $riderData = $fulfillment['rider'] ?? [];
        if (empty($riderData['mobile'])) {
            return;
        }

        // Find or create rider manually
        $deliveryMan = DeliveryMan::where('phone', $riderData['mobile'])->first();
        if (!$deliveryMan) {
            $deliveryMan = new DeliveryMan();
            $deliveryMan->f_name = $riderData['name'] ?? 'Unknown';
            $deliveryMan->phone = $riderData['mobile'];
            $deliveryMan->email = null;
            $deliveryMan->zone_id = 1;
            $deliveryMan->identity_type = 'N/A';
            $deliveryMan->identity_number = 'N/A';
            $deliveryMan->identity_image = json_encode([]);
            $deliveryMan->password = bcrypt('password');
            $deliveryMan->active = 1;
            $deliveryMan->save();
        }

        // Increment orders if needed
        if ($newStatus === 'confirmed') {
            $deliveryMan->increment('current_orders');
        }

        // Save delivery history manually
        $loc = $latestLocation['location'];
        $history = new DeliveryHistory();
        $history->delivery_man_id = $deliveryMan->id;
        $history->latitude = $loc['latitude'];
        $history->longitude = $loc['longitude'];
        $history->location = ucfirst(strtolower(str_replace("_", " ", $latestLocation['status'] ?? ''))); 
        $history->time = now();
        $history->order_id = $order->id;
        $history->created_at = now();
        $history->updated_at = now();
        $history->save();

        // Link rider to order
        $order->delivery_man_id = $deliveryMan->id;
        $order->save();

        Log::info("Delivery location updated for rider ID {$deliveryMan->id}");
    }
}
