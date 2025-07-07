<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class TATController extends Controller
{
    public function getDeliveryTAT(Request $request)
    {
        $request->validate([
            'origin_lat' => 'required|numeric',
            'origin_lng' => 'required|numeric',
            'dest_lat' => 'required|numeric',
            'dest_lng' => 'required|numeric',
        ]);

        $originLat = $request->origin_lat;
        $originLng = $request->origin_lng;
        $destLat = $request->dest_lat;
        $destLng = $request->dest_lng;

        $apiKey = env('GOOGLE_MAPS_API_KEY');
        $url = "https://maps.googleapis.com/maps/api/distancematrix/json?origins=$originLat,$originLng&destinations=$destLat,$destLng&key=$apiKey";

        $response = file_get_contents($url);
        $data = json_decode($response, true);

        if (!$data || $data['status'] !== 'OK') {
            return response()->json(['error' => 'Google API failed'], 500);
        }

        $element = $data['rows'][0]['elements'][0];

        if ($element['status'] !== 'OK') {
            return response()->json(['error' => 'Route not found'], 404);
        }

        $duration = $element['duration']['text'];
        $distance = $element['distance']['text'];
      

        $distanceKm = preg_replace('/[^\d]/', '', $distance);

        if ($distanceKm < 7) {
            $tat = 'Instant Delivery';
        } elseif ($distanceKm < 20 ) {
            $tat = 'Same Day Delivery';
        } elseif ($distanceKm < 50 ) {
            $tat = 'Next Day Delivery';
        } else {
            $tat = "Estimated delivery time: $duration";
        }

        return response()->json([
            'distance' => $distance,
            'duration' => $duration,
            'tat' => $tat,
        ]);
    }
}

