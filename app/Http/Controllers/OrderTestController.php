<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\OrderConnector;

class OrderTestController extends Controller
{
    public function testCreateOrder(OrderConnector $connector)
    {
       $payload=['invoice_id'=>'429645257'];
        //   return     $response = $connector->call('cancelOrder', $payload);
 //return $response = $connector->cancelOrderByInvoiceId('429929185');

      return  $response = $connector->getRiderLocation('17544575425807VT1W9SX');
  
        $payload = [
            "orderType" => "retailorder",
            "marketplaceId" => 10,
            "orderNumber" => "12349",
            "orderDate" => "2024-03-19 14:32:59",
            "expDeliveryDate" => "", 
            "paymentMode" => 2,
            "shippingMethod" => 1, 
            "items" => [[
                "Sku" => "test_1",
                "Quantity" => "2",
                "Price" => 20,
                "itemDiscount" => 1
                ]],
                "customer" => [[
                    "gst_number" => "abcdxyz132435",
                    "billing" => [
                        "name" => "test",
                        "addressLine1" => "testaddress1",
                        "addressLine2" => "testaddress2",
                        "postalCode" => "400067",
                        "city" => "Mumbai",
                        "state" => "Maharashtra",
                        "country" => "India",
                        "contact" => "9876543210",
                        "email" => "test@gmail.com"
                    ],
                    "shipping" => [
                        "name" => "test1",
                        "addressLine1" => "testaddress3",
                        "addressLine2" => "testaddress4",
                        "postalCode" => "400067",
                        "city" => "Mumbai",
                        "state" => "Maharashtra",
                        "country" => "India",
                        "contact" => "9876543210",
                        "email" => "test@gmail.com",
                        "latitude" => "12.900222",
                        "longitude" => "77.650914"
                        ]
                        ]]
                    ];
                    
                    try {
                        $response = $connector->call('createOrder', $payload);
                        return response()->json(['message' => 'Order created successfully', 'response' => $response]);
                    } catch (\Exception $e) {
                        return response()->json(['error' => $e->getMessage()], 500);
                    }
                }
            }
       