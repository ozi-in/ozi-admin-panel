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

    $apiKey = \App\Models\BusinessSetting::where('key', 'map_api_key')->first()->value;
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

    $distance = $element['distance']['text'];
    $duration = $element['duration']['text'];
    $distanceKm = $element['distance']['value'] / 1000; // meters to km

    // ✅ Serviceability Check
    $maxDistance = 250; // Max allowed km
    if ($distanceKm > $maxDistance) {
        return response()->json([
            'message' => 'Your area is unserviceable',
            'distance' => $distance,
            'duration' => $duration,
        ], 400);
    }

    // ✅ Get delivery TAT based on config
    $tat = 'Unknown';
    foreach (config('tat.levels') as $level) {
        if ($distanceKm < $level['max_km']) {
            $tat = $level['label'];
            break;
        }
    }

    return response()->json([
        'distance' => $distance,
        'duration' => $duration,
        'tat' => $tat,
    ]);
}

}
