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
        
        // === Update Order Status ===
        $newStatus = null;
        
        if ($externalStatus === 'c') {
            $newStatus = 'confirmed';
        }
        $update_Current_order=0;
        if ($fulfillmentStatus === 'CREATED') {
            $update_Current_order=1;
            $newStatus = 'confirmed';
        } 
        elseif (in_array($fulfillmentStatus, ['PICKED_UP'])) {
            $newStatus = 'picked_up';
        } elseif ($fulfillmentStatus === 'DELIVERED') {
            $newStatus = 'delivered';
        } elseif ($fulfillmentStatus === 'UNDELIVERED') {
            $newStatus = 'undelivered';
        }
        else{
            $newStatus = $fulfillmentStatus;
        }
        
        if ($newStatus) {
            $order->order_status = $newStatus;
            if( $newStatus=="delivered"){
                $order->payment_status = 'paid';
            }
            $order->save();
            Log::info("Order ID {$order->id} updated to {$newStatus}");
        } else {
            Log::info("Order ID {$order->id} - no status change needed");
        }
        
        // === Extract Rider Location ===
        $logs = $data['fulfillment']['logs'] ?? [];
        $latestLocation = null;
        
        foreach (array_reverse($logs) as $log) {
            if (!empty($log['location']['latitude']) && !empty($log['location']['longitude'])) {
                $latestLocation = $log;
                break;
            }
        }
        
        if ($latestLocation) {
            $riderData = $data['fulfillment']['rider'] ?? null;
            
            if ($riderData && !empty($riderData['mobile'])) {
                // Search for existing delivery man
                $deliveryMan = \App\Models\DeliveryMan::where('phone', $riderData['mobile'])->first();
                
                // Create manually if not exists
                if (!$deliveryMan) {
                    $deliveryMan = new \App\Models\DeliveryMan();
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
                if($update_Current_order>0){
                    $deliveryMan->increment('current_orders');
                }
                // Save location
                $location = $latestLocation['location'];
                \App\Models\DeliveryHistory::create([
                    'delivery_man_id' => $deliveryMan->id,
                    'latitude' => $location['latitude'],
                    'longitude' => $location['longitude'],
                    'location' => ucfirst(strtolower(str_replace("_", " ", $latestLocation['status']))),
                    'time' => now(),
                    'order_id' => $data['reference_id'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                
                
                //$order = Order::where('id', $data['reference_id'])->first();
                $order->delivery_man_id=$deliveryMan->id;
                $order->save();
                Log::info("Delivery location updated for rider ID {$deliveryMan->id}");
            }
        }
        
        return response()->json(['message' => 'Webhook processed'], 200);
    }
    
    
}

?>