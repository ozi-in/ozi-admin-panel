<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Cart;
use App\Models\Item;
use Illuminate\Http\Request;
use App\CentralLogics\Helpers;
use App\Http\Controllers\Controller;
use App\Models\ItemCampaign;
use Illuminate\Support\Facades\Validator;

class CartController extends Controller
{
    public function get_carts(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'guest_id' => $request->user ? 'nullable' : 'required',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }
        $user_id = $request->user ? $request->user->id : $request['guest_id'];
        $is_guest = $request->user ? 0 : 1;
        $carts = Cart::where('user_id', $user_id)->where('is_guest',$is_guest)->where('module_id',$request->header('moduleId'))->get()
        ->map(function ($data) {
            $data->add_on_ids = json_decode($data->add_on_ids,true);
            $data->add_on_qtys = json_decode($data->add_on_qtys,true);
            $data->variation = $data->variation;
			$data->item = Helpers::cart_product_data_formatting($data->item, $data->variation,$data->add_on_ids,
            $data->add_on_qtys, false, app()->getLocale());
			return $data;
		});
        return response()->json($carts, 200);
    }

    public function add_to_cart(Request $request)
{
    $validator = Validator::make($request->all(), [
        'guest_id' => $request->user ? 'nullable' : 'required',
        'item_id' => 'required|integer',
        'model' => 'required|string|in:Item,ItemCampaign',
        'price' => 'required|numeric',
        'quantity' => 'required|integer|min:1',
    ]);

    if ($validator->fails()) {
        return response()->json(['errors' => Helpers::error_processor($validator)], 403);
    }

    $user_id = $request->user ? $request->user->id : $request['guest_id'];
    $is_guest = $request->user ? 0 : 1;
    $model = $request->model === 'Item' ? 'App\Models\Item' : 'App\Models\ItemCampaign';
    $item = $request->model === 'Item' ? Item::find($request->item_id) : ItemCampaign::find($request->item_id);

    $variationArray = $request->variation ?? [];
    $typeKey = is_array($variationArray) && isset($variationArray[0]['type']) ? $variationArray[0]['type'] : null;

    $cart = Cart::where('item_id', $request->item_id)
        ->where('item_type', $model)
        ->where('user_id', $user_id)
        ->where('is_guest', $is_guest)
        ->where('module_id', $request->header('moduleId'))
        ->when($typeKey, function ($query) use ($typeKey) {
            $query->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(variation, '$[0].type')) = ?", [$typeKey]);
        }, function ($query) {
            $query->where('variation', json_encode([]));
        })
        ->first();

    // ✅ Update quantity if same item + type variation exists
    if ($cart) {
        $newQty = $cart->quantity + $request->quantity;

        if ($item->maximum_cart_quantity && $newQty > $item->maximum_cart_quantity) {
            return response()->json([
                'errors' => [
                    ['code' => 'cart_item_limit', 'message' => translate('messages.maximum_cart_quantity_exceeded')]
                ]
            ], 403);
        }

        $cart->quantity = $newQty;
        $cart->price = $request->price; // Optional: keep updated price
        $cart->add_on_ids = isset($request->add_on_ids) ? json_encode($request->add_on_ids) : json_encode([]);
        $cart->add_on_qtys = isset($request->add_on_qtys) ? json_encode($request->add_on_qtys) : json_encode([]);
        $cart->save();
    } else {
        // ✅ Create new cart item
        $cart = new Cart();
        $cart->user_id = $user_id;
        $cart->module_id = $request->header('moduleId');
        $cart->item_id = $request->item_id;
        $cart->store_id = $item->store_id;
        $cart->is_guest = $is_guest;
        $cart->add_on_ids = isset($request->add_on_ids) ? json_encode($request->add_on_ids) : json_encode([]);
        $cart->add_on_qtys = isset($request->add_on_qtys) ? json_encode($request->add_on_qtys) : json_encode([]);
        $cart->item_type = $request->model;
        $cart->price = $request->price;
        $cart->quantity = $request->quantity;
        $cart->variation = isset($request->variation) ? $request->variation: [];
        $cart->save();

        $item->carts()->save($cart);
    }

    // ✅ Return updated cart list
    $carts = Cart::where('user_id', $user_id)
        ->where('is_guest', $is_guest)
        ->where('module_id', $request->header('moduleId'))
        ->with('item')
        ->get()
        ->map(function ($data) {
            $data->add_on_ids = json_decode($data->add_on_ids, true);
            $data->add_on_qtys = json_decode($data->add_on_qtys, true);
            $data->variation = $data->variation;
            $data->item = Helpers::cart_product_data_formatting(
                $data->item,
                $data->variation,
                $data->add_on_ids,
                $data->add_on_qtys,
                false,
                app()->getLocale()
            );
            return $data;
        });

    return response()->json($carts, 200);
}


    public function update_cart(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'cart_id' => 'required',
            'guest_id' => $request->user ? 'nullable' : 'required',
            'price' => 'required|numeric',
            'quantity' => 'required|integer|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }

        $user_id = $request->user ? $request->user->id : $request['guest_id'];
        $is_guest = $request->user ? 0 : 1;
        $cart = Cart::find($request->cart_id);
        $item = $cart->item_type === 'App\Models\Item' ? Item::find($cart->item_id) : ItemCampaign::find($cart->item_id);
        if($item->maximum_cart_quantity && ($request->quantity>$item->maximum_cart_quantity)){
            return response()->json([
                'errors' => [
                    ['code' => 'cart_item_limit', 'message' => translate('messages.maximum_cart_quantity_exceeded')]
                ]
            ], 403);
        }

        $cart->user_id = $user_id;
        $cart->module_id = $request->header('moduleId');
        $cart->is_guest = $is_guest;
        $cart->add_on_ids = isset($request->add_on_ids)?json_encode($request->add_on_ids):$cart->add_on_ids;
        $cart->add_on_qtys = isset($request->add_on_qtys)?json_encode($request->add_on_qtys):$cart->add_on_qtys;
        $cart->price = $request->price;
        $cart->quantity = $request->quantity;
        $cart->variation = isset($request->variation)? $request->variation:$cart->variation;
        $cart->save();

        $carts = Cart::where('user_id', $user_id)->where('is_guest',$is_guest)->where('module_id',$request->header('moduleId'))->get()
        ->map(function ($data) {
            $data->add_on_ids = json_decode($data->add_on_ids,true);
            $data->add_on_qtys = json_decode($data->add_on_qtys,true);
            $data->variation =$data->variation;
			$data->item = Helpers::cart_product_data_formatting($data->item, $data->variation,$data->add_on_ids,
            $data->add_on_qtys, false, app()->getLocale());
            return $data;
		});
        return response()->json($carts, 200);
    }

    public function remove_cart_item(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'cart_id' => 'required',
            'guest_id' => $request->user ? 'nullable' : 'required',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }

        $user_id = $request->user ? $request->user->id : $request['guest_id'];
        $is_guest = $request->user ? 0 : 1;

        $cart = Cart::find($request->cart_id);
        $cart->delete();

        $carts = Cart::where('user_id', $user_id)->where('is_guest',$is_guest)->where('module_id',$request->header('moduleId'))->get()
        ->map(function ($data) {
            $data->add_on_ids = json_decode($data->add_on_ids,true);
            $data->add_on_qtys = json_decode($data->add_on_qtys,true);
            $data->variation = json_decode($data->variation,true);
			$data->item = Helpers::cart_product_data_formatting($data->item, $data->variation,$data->add_on_ids,
            $data->add_on_qtys, false, app()->getLocale());
            return $data;
		});
        return response()->json($carts, 200);
    }

    public function remove_cart(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'guest_id' => $request->user ? 'nullable' : 'required',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }

        $user_id = $request->user ? $request->user->id : $request['guest_id'];
        $is_guest = $request->user ? 0 : 1;

        $carts = Cart::where('user_id', $user_id)->where('is_guest',$is_guest)->where('module_id',$request->header('moduleId'))->get();

        foreach($carts as $cart){
            $cart->delete();
        }


        $carts = Cart::where('user_id', $user_id)->where('is_guest',$is_guest)->where('module_id',$request->header('moduleId'))->get()
        ->map(function ($data) {
            $data->add_on_ids = json_decode($data->add_on_ids,true);
            $data->add_on_qtys = json_decode($data->add_on_qtys,true);
            $data->variation = json_decode($data->variation,true);
			$data->item = Helpers::cart_product_data_formatting($data->item, $data->variation,$data->add_on_ids,
            $data->add_on_qtys, false, app()->getLocale());
            return $data;
		});
        return response()->json($carts, 200);
    }

    // Make cart Item as Gift
public function updateGiftStatus(Request $request, $id)
{
     $validator = Validator::make($request->all(), [
        'is_gift' => 'required|boolean'

    ]);
    if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }
  $user_id = $request->user ? $request->user->id : $request['guest_id'];
    $cartItem = Cart::where('id', $id)
        ->where('user_id', $user_id) // Ensure it's the current user's cart
        ->first();

    if (!$cartItem) {
        return response()->json(['message' => 'Cart item not found.'], 404);
    }

    $cartItem->is_gift = $request->is_gift;
    $cartItem->save();

    return response()->json([
        'message' => 'Gift status updated.',
        'data' => $cartItem
    ]);
}
}
