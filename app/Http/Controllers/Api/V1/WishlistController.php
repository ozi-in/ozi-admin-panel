<?php

namespace App\Http\Controllers\Api\V1;

use App\CentralLogics\Helpers;
use App\Http\Controllers\Controller;
use App\Models\Wishlist;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class WishlistController extends Controller
{
    public function add_to_wishlist(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'item_id' => 'required_without:store_id',
            'store_id' => 'required_without:item_id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }
        if ($request->item_id && $request->store_id) {
            $errors = [];
            array_push($errors, ['code' => 'data', 'message' => translate('messages.can_not_add_both_food_and_restaurant_at_same_time')]);
            return response()->json([
                'errors' => $errors
            ], 403);
        }
        $wishlist = Wishlist::where('user_id', $request->user()->id)->where('item_id', $request->item_id)->where('store_id', $request->store_id)->first();
        if (empty($wishlist)) {
            $wishlist = new Wishlist;
            $wishlist->user_id = $request->user()->id;
            $wishlist->item_id = $request->item_id;
            $wishlist->store_id = $request->store_id;
            $wishlist->save();
            return response()->json(['message' => translate('messages.added_successfully')], 200);
        }

        return response()->json(['message' => translate('messages.already_in_wishlist')], 409);
    }

    public function remove_from_wishlist(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'item_id' => 'required_without:store_id',
            'store_id' => 'required_without:item_id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }

        $wishlist = Wishlist::when($request->item_id, function($query)use($request){
            return $query->where('item_id', $request->item_id);
        })
        ->when($request->store_id, function($query)use($request){
            return $query->where('store_id', $request->store_id);
        })
        ->where('user_id', $request->user()->id)->first();

        if ($wishlist) {
            $wishlist->delete();
            return response()->json(['message' => translate('messages.successfully_removed')], 200);

        }
        return response()->json(['message' => translate('messages.not_found')], 404);
    }

   public function wish_list(Request $request)
{
    // Check for required zoneId header
    if (!$request->hasHeader('zoneId')) {
        return response()->json([
            'errors' => [
                ['code' => 'zoneId', 'message' => 'Zone id is required!']
            ]
        ], 403);
    }

    $zone_id = $request->header('zoneId');
    $longitude = $request->header('longitude');
    $latitude = $request->header('latitude');
    $zone_ids = json_decode($zone_id, true);

    $module_id = config('module.current_module_data')['id'] ?? null;

    $wishlists = Wishlist::where('user_id', $request->user()->id)

        // Only include wishlists where item exists and satisfies conditions
        ->whereHas('item', function ($q) use ($zone_ids, $module_id) {
            $q->whereHas('store', function ($query) use ($zone_ids, $module_id) {
                $query->when($module_id, function ($query) use ($module_id) {
                    $query->where('module_id', $module_id)
                          ->whereHas('zone.modules', function ($query) use ($module_id) {
                              $query->where('modules.id', $module_id);
                          });
                })
                ->whereHas('module', function ($query) {
                    $query->where('status', 1);
                })
                ->whereIn('zone_id', $zone_ids);
            });
        })

        ->with([
            'item' => function ($q) use ($zone_ids, $module_id) {
                $q->whereHas('store', function ($query) use ($zone_ids, $module_id) {
                    $query->when($module_id, function ($query) use ($module_id) {
                        $query->where('module_id', $module_id)
                              ->whereHas('zone.modules', function ($query) use ($module_id) {
                                  $query->where('modules.id', $module_id);
                              });
                    })
                    ->whereHas('module', function ($query) {
                        $query->where('status', 1);
                    })
                    ->whereIn('zone_id', $zone_ids);
                });
            },
            'store' => function ($q) use ($zone_ids, $longitude, $latitude, $module_id) {
                $q->when($module_id, function ($query) use ($module_id) {
                    $query->whereHas('zone.modules', function ($query) use ($module_id) {
                        $query->where('modules.id', $module_id);
                    })->module($module_id);
                })
                ->withOpen($longitude ?? 0, $latitude ?? 0)
                ->whereHas('module', function ($query) {
                    $query->where('status', 1);
                })
                ->whereIn('zone_id', $zone_ids);
            }
        ])
        ->get();

    $wishlists = Helpers::wishlist_data_formatting($wishlists, true);

    return response()->json($wishlists, 200);
}

}
