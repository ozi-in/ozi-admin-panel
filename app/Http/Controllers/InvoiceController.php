<?php

use App\Models\Order;
use Illuminate\Support\Facades\Crypt;
class InvoiceController extends Controller  
{
public function show($id){

    try {
        $orderId = decrypt($id);
        $order = Order::with('items')->findOrFail($orderId);

        return view('invoice.guest', compact('order'));
    } catch (\Exception $e) {
        abort(404); // Invalid or tampered URL
    }
}
}