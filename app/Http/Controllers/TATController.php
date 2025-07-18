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
        $delivery_Tat = \App\Models\BusinessSetting::where('key', 'delivery_tat')->first()->value;
        
        $distance = $element['distance']['text'];
              $distanceKm = $element['distance']['value'] / 1000; // meters to km
   
      $delivery_Tat = \App\Models\BusinessSetting::where('key', 'delivery_tat')->first()->value; // in minutes

$distance = $element['distance']['text'];
$durationText = $element['duration']['text'];

// Parse hours and minutes from Google duration
$hours = 0;
$minutes = 0;

if (preg_match('/(\d+)\s*hour/', $durationText, $matches)) {
    $hours = (int) $matches[1];
}
if (preg_match('/(\d+)\s*min/', $durationText, $matches)) {
    $minutes = (int) $matches[1];
}

$totalMinutes = $hours * 60 + $minutes + ($delivery_Tat ?? 0);

// Rebuild duration string
$newHours = floor($totalMinutes / 60);
$newMinutes = $totalMinutes % 60;

$newDuration = '';
if ($newHours > 0) {
    $newDuration .= $newHours . ' hour' . ($newHours > 1 ? 's' : '');
}
if ($newMinutes > 0) {
    if ($newDuration !== '') {
        $newDuration .= ' ';
    }
    $newDuration .= $newMinutes . ' min' . ($newMinutes > 1 ? 's' : '');
}
if ($newDuration === '') {
    $newDuration = '0 mins';
}

$duration = $newDuration;
  
        
        // ✅ Serviceability Check
        $maxDistance = 35; // Max allowed km
        if ($distanceKm > $maxDistance) {
            return response()->json([
                // 'message' => 'Your area is unserviceable',
                'distance' => $distance,
                'duration' => "Your area is unserviceable",
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
