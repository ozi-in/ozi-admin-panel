<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Item;
use App\Models\Order;
use App\Models\Store;
use App\Models\Review;
use App\Models\Allergy;
use App\Models\Category;
use App\Models\Nutrition;
use App\Models\GenericName;
use App\Models\PriorityList;
use Illuminate\Http\Request;
use App\CentralLogics\Helpers;
use App\Models\BusinessSetting;
use App\CentralLogics\StoreLogic;
use Illuminate\Support\Facades\DB;
use App\CentralLogics\ProductLogic;
use App\CentralLogics\CategoryLogic;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class ItemController extends Controller
{

    public function get_latest_products(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'store_id' => 'required',
            'category_id' => 'required',
            'limit' => 'required',
            'offset' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }

        if (!$request->hasHeader('zoneId')) {
            $errors = [];
            array_push($errors, ['code' => 'zoneId', 'message' => translate('messages.zone_id_required')]);
            return response()->json([
                'errors' => $errors
            ], 403);
        }
        $zone_id = $request->header('zoneId');
        $type = $request->query('type', 'all');
        $product_id = $request->query('product_id')??null;
        $min = $request->query('min_price');
        $max = $request->query('max_price');

        $items = ProductLogic::get_latest_products($zone_id, $request['limit'], $request['offset'], $request['store_id'], $request['category_id'], $type,$min,$max,$product_id);
        $items['categories'] = $items['categories'];
        $items['products'] = Helpers::product_data_formatting($items['products'], true, false, app()->getLocale());
        return response()->json($items, 200);
    }

    public function get_new_products(Request $request)
    {
        if (!$request->hasHeader('zoneId')) {
            $errors = [];
            array_push($errors, ['code' => 'zoneId', 'message' => translate('messages.zone_id_required')]);
            return response()->json([
                'errors' => $errors
            ], 403);
        }
        $zone_id = $request->header('zoneId');
        $type = $request->query('type', 'all');
        $product_id = $request->query('product_id')??null;
        $min = $request->query('min_price');
        $max = $request->query('max_price');
        $limit = isset($request['limit'])?$request['limit']:30;
        $offset = isset($request['offset'])?$request['offset']:1;

        $items = ProductLogic::get_new_products($zone_id, $type,$min,$max,$product_id,$limit,$offset);
        $items['categories'] = $items['categories'];
        $items['products'] = Helpers::product_data_formatting($items['products'], true, false, app()->getLocale());
        return response()->json($items, 200);
    }

public function get_searched_products(Request $request)
{
    if (!$request->hasHeader('zoneId')) {
        $errors = [['code' => 'zoneId', 'message' => translate('messages.zone_id_required')]];
        return response()->json(['errors' => $errors], 403);
    }

    $validator = Validator::make($request->all(), [
        'name' => 'required'
    ]);
    if ($validator->fails()) {
        return response()->json(['errors' => Helpers::error_processor($validator)], 403);
    }

    $zone_id = $request->header('zoneId');
    $search  = trim((string) $request['name']);
    $key     = preg_split('/\s+/', $search, -1, PREG_SPLIT_NO_EMPTY) ?: [];
    $limit   = (int)($request['limit'] ?? 30);
    $offset  = (int)($request['offset'] ?? 1);
    /**
     * Step 1: Check if search contains any banner keyword
     */





$productIds = \App\Models\BannerKeywordProduct::whereRaw('LOWER(keyword) = ?', [strtolower($search)])->orWhere('keyword', 'like', '%' . $search . '%')
    ->pluck('item_id')
    ->toArray();

    if ($productIds) {
        // Fetch products by those IDs
        $items = Item::query()
            ->whereIn('id', $productIds)
            ->active()
            ->with(['store:id,zone_id,module_id'])
            ->whereHas('store', function($q) use ($zone_id) {
                $q->when(config('module.current_module_data'), function($q) {
                    $q->where('module_id', config('module.current_module_data')['id']);
                })->whereIn('zone_id', json_decode($zone_id, true));
            })
            ->paginate($limit, ['*'], 'page', $offset);

        // Build category list from matched products
        $pageCategoryIds = collect($items->items())->pluck('category_id')->filter()->unique()->values()->all();
        $categories = empty($pageCategoryIds)
            ? collect([])
            : Category::select('id','name','parent_id','priority','status','module_id','position')
                ->where(['position'=>0,'status'=>1])
                ->when(config('module.current_module_data'), function($q){
                    $q->module(config('module.current_module_data')['id']);
                })
                ->whereIn('id', $pageCategoryIds)
                ->orderBy('priority','desc')
                ->get();

        return response()->json([
            'total_size' => $items->total(),
            'limit'      => $limit,
            'offset'     => $offset,
            'products'   => Helpers::product_data_formatting($items->items(), true, false, app()->getLocale()),
            'categories' => $categories,
        ], 200);
    }
  
    /**
     * Step2
     * Relevance scoring
     * Keep NAME highest priority; then DESCRIPTION.
     * Also add token-based partials so we surface "related" items even if not exact.
     */
    $W_NAME_EXACT   = 1000; // exact name match
    $W_NAME_PREFIX  = 500;  // name starts with full search
    $W_NAME_SUBSTR  = 300;  // name contains full search
    $W_DESC_SUBSTR  = 120;  // description contains full search

    $W_NAME_TOKEN_PREFIX = 80;  // name starts with token
    $W_NAME_TOKEN_SUBSTR = 60;  // name contains token
    $W_DESC_TOKEN_SUBSTR = 25;  // description contains token

    $relevanceParts = [];
    $bindings       = [];

    // full-string signals
    $relevanceParts[] = "IF(items.name = ?, {$W_NAME_EXACT}, 0)";
    $bindings[]       = $search;

    $relevanceParts[] = "IF(items.name LIKE ?, {$W_NAME_PREFIX}, 0)";
    $bindings[]       = $search.'%';

    $relevanceParts[] = "IF(items.name LIKE ?, {$W_NAME_SUBSTR}, 0)";
    $bindings[]       = '%'.$search.'%';

    $relevanceParts[] = "IF(items.description LIKE ?, {$W_DESC_SUBSTR}, 0)";
    $bindings[]       = '%'.$search.'%';

    // token-based signals for "more related products"
    foreach ($key as $t) {
        $relevanceParts[] = "IF(items.name LIKE ?, {$W_NAME_TOKEN_PREFIX}, 0)";
        $bindings[]       = $t.'%';

        $relevanceParts[] = "IF(items.name LIKE ?, {$W_NAME_TOKEN_SUBSTR}, 0)";
        $bindings[]       = '%'.$t.'%';

        $relevanceParts[] = "IF(items.description LIKE ?, {$W_DESC_TOKEN_SUBSTR}, 0)";
        $bindings[]       = '%'.$t.'%';
    }

    $relevanceSql = implode(' + ', $relevanceParts) . ' AS relevance';

    $itemsQuery = Item::query()
        ->select('items.*')
        ->selectRaw($relevanceSql, $bindings)
        ->active()
        // keep relationships lean
        ->with(['store:id,zone_id,module_id'])
        // zone + module constraints (lightweight)
        ->whereHas('module.zones', function($q) use ($zone_id) {
            $q->whereIn('zones.id', json_decode($zone_id, true));
        })
        ->whereHas('store', function($q) use ($zone_id) {
            $q->when(config('module.current_module_data'), function($q) {
                $q->where('module_id', config('module.current_module_data')['id'])
                  ->whereHas('zone.modules', function($q){
                      $q->where('modules.id', config('module.current_module_data')['id']);
                  });
            })->whereIn('zone_id', json_decode($zone_id, true));
        })
        // search ONLY name/description
        ->where(function ($q) use ($key) {
            foreach ($key as $value) {
                $like = '%'.$value.'%';
                $q->orWhere('items.name', 'like', $like)
                  ->orWhere('items.description', 'like', $like);
            }
        })
        // final ordering: relevance first, then a tiny bias by shorter name (nicer UX)
        ->orderByDesc('relevance')
        ->orderByRaw('LENGTH(items.name) ASC');

    // Fetch page
    $items = $itemsQuery->paginate($limit, ['*'], 'page', $offset);

    // Build categories from the current page only (cheaper, still useful)
    $pageCategoryIds = collect($items->items())->pluck('category_id')->filter()->unique()->values()->all();

    $categories = empty($pageCategoryIds)
        ? collect([])
        : Category::select('id','name','parent_id','priority','status','module_id','position')
            ->where(['position'=>0,'status'=>1])
            ->when(config('module.current_module_data'), function($q){
                $q->module(config('module.current_module_data')['id']);
            })
            ->whereIn('id', $pageCategoryIds)
            ->orderBy('priority','desc')
            ->get();

    $data = [
        'total_size' => $items->total(),
        'limit'      => $limit,
        'offset'     => $offset,
        'products'   => Helpers::product_data_formatting($items->items(), true, false, app()->getLocale()),
        'categories' => $categories,
    ];

    return response()->json($data, 200);
}


    public function get_searched_products_suggestion(Request $request)
    {
        if (!$request->hasHeader('zoneId')) {
            $errors = [];
            array_push($errors, ['code' => 'zoneId', 'message' => translate('messages.zone_id_required')]);
            return response()->json([
                'errors' => $errors
            ], 403);
        }
        $validator = Validator::make($request->all(), [
            'name' => 'required'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }
        $zone_id = $request->header('zoneId');

        $key = explode(' ', $request['name']);

        $limit = $request['limit']??10;
        $offset = $request['offset']??1;

        $type = $request->query('type', 'all');

        $items = Item::active()->type($type)

        ->when($request->category_id, function($query)use($request){
            $query->whereHas('category',function($q)use($request){
                return $q->whereId($request->category_id)->orWhere('parent_id', $request->category_id);
            });
        })
        ->when($request->store_id, function($query) use($request){
            return $query->where('store_id', $request->store_id);
        })
        ->whereHas('module.zones', function($query)use($zone_id){
            $query->whereIn('zones.id', json_decode($zone_id, true));
        })
        ->whereHas('store', function($query)use($zone_id){
            $query->when(config('module.current_module_data'), function($query){
                $query->where('module_id', config('module.current_module_data')['id'])->whereHas('zone.modules',function($query){
                    $query->where('modules.id', config('module.current_module_data')['id']);
                });
            })->whereIn('zone_id', json_decode($zone_id, true));
        })
        ->where(function ($q) use ($key) {
            foreach ($key as $value) {
                $q->orWhere('name', 'like', "%{$value}%");
            }
            $q->orWhereHas('translations',function($query)use($key){
                $query->where(function($q)use($key){
                    foreach ($key as $value) {
                        $q->where('value', 'like', "%{$value}%");
                    };
                });
            });
            $q->orWhereHas('tags',function($query)use($key){
                $query->where(function($q)use($key){
                    foreach ($key as $value) {
                        $q->where('tag', 'like', "%{$value}%");
                    };
                });
            });
            $q->orWhereHas('nutritions',function($query)use($key){
                $query->where(function($q)use($key){
                    foreach ($key as $value) {
                        $q->where('nutrition', 'like', "%{$value}%");
                    };
                });
            });
            $q->orWhereHas('allergies',function($query)use($key){
                $query->where(function($q)use($key){
                    foreach ($key as $value) {
                        $q->where('allergy', 'like', "%{$value}%");
                    };
                });
            });
            $q->orWhereHas('generic',function($query)use($key){
                $query->where(function($q)use($key){
                    foreach ($key as $value) {
                        $q->where('generic_name', 'like', "%{$value}%");
                    };
                });
            });
        })->select(['name','image'])

        ->paginate($limit, ['*'], 'page', $offset);

        $data =  [
            'total_size' => $items->total(),
            'limit' => $limit,
            'offset' => $offset,
            'products' => $items->items()
        ];

        return response()->json($data, 200);
    }

    public function get_popular_products(Request $request)
    {
        if (!$request->hasHeader('zoneId')) {
            $errors = [];
            array_push($errors, ['code' => 'zoneId', 'message' => translate('messages.zone_id_required')]);
            return response()->json([
                'errors' => $errors
            ], 403);
        }

        $type = $request->query('type', 'all');

        $zone_id= $request->header('zoneId');
        $items = ProductLogic::popular_products($zone_id, $request['limit']?? 10, $request['offset'], $type);
        $items['products'] = Helpers::product_data_formatting($items['products'], true, false, app()->getLocale());
        return response()->json($items, 200);
    }

    public function get_most_reviewed_products(Request $request)
    {
        if (!$request->hasHeader('zoneId')) {
            $errors = [];
            array_push($errors, ['code' => 'zoneId', 'message' => translate('messages.zone_id_required')]);
            return response()->json([
                'errors' => $errors
            ], 403);
        }

        $type = $request->query('type', 'all');

        $zone_id= $request->header('zoneId');
        $items = ProductLogic::most_reviewed_products($zone_id, $request['limit'], $request['offset'], $type);
        $items['categories'] = $items['categories'];
        $items['products'] = Helpers::product_data_formatting($items['products'], true, false, app()->getLocale());
        return response()->json($items, 200);
    }

    public function get_discounted_products(Request $request)
    {
        if (!$request->hasHeader('zoneId')) {
            $errors = [];
            array_push($errors, ['code' => 'zoneId', 'message' => translate('messages.zone_id_required')]);
            return response()->json([
                'errors' => $errors
            ], 403);
        }

        $type = $request->query('type', 'all');
        $category_ids = $request->query('category_ids', '');

        $zone_id= $request->header('zoneId');
        $items = ProductLogic::discounted_products($zone_id, $request['limit'], $request['offset'], $type, $category_ids);
        $items['products'] = Helpers::product_data_formatting($items['products'], true, false, app()->getLocale());
        return response()->json($items, 200);
    }

    public function get_cart_suggest_products(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'store_id' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }

        if (!$request->hasHeader('zoneId')) {
            $errors = [];
            array_push($errors, ['code' => 'zoneId', 'message' => translate('messages.zone_id_required')]);
            return response()->json([
                'errors' => $errors
            ], 403);
        }
        $zone_id = $request->header('zoneId');

        $type = $request->query('type', 'all');
        $recommended = $request->query('recommended');

        $items = ProductLogic::cart_suggest_products($zone_id, $request['store_id'], $request['limit'], $request['offset'], $type,$recommended);
        $items['items'] = Helpers::product_data_formatting($items['items'], true, false, app()->getLocale());
        return response()->json($items, 200);
    }

    public function get_product($id)
    {
        try {

            $item = Item::withCount('whislists')->with(['tags','nutritions','allergies','reviews','reviews.customer'])->active()
            ->when(config('module.current_module_data'), function($query){
                $query->module(config('module.current_module_data')['id']);
            })
            ->when(is_numeric($id),function ($qurey) use($id){
                $qurey-> where('id', $id);
            })
            ->when(!is_numeric($id),function ($qurey) use($id){
                $qurey-> where('slug', $id);
            })
            ->first();
            $store = StoreLogic::get_store_details($item->store_id);
            if($store)
            {
                $category_ids = DB::table('items')
                ->join('categories', 'items.category_id', '=', 'categories.id')
                ->selectRaw('categories.position as positions, IF((categories.position = "0"), categories.id, categories.parent_id) as categories')
                ->where('items.store_id', $item->store_id)
                ->where('categories.status',1)
                ->groupBy('categories','positions')
                ->get();

                $store = Helpers::store_data_formatting($store);
                $store['category_ids'] = array_map('intval', $category_ids->pluck('categories')->toArray());
                $store['category_details'] = Category::whereIn('id',$store['category_ids'])->get();
                $store['price_range']  = Item::withoutGlobalScopes()->where('store_id', $item->store_id)
                ->select(DB::raw('MIN(price) AS min_price, MAX(price) AS max_price'))
                ->get(['min_price','max_price'])->toArray();
            }
            $item = Helpers::product_data_formatting($item, false, false, app()->getLocale());
            $item['store_details'] = $store;
            return response()->json($item, 200);
        } catch (\Exception $e) {
            return response()->json([
                'errors' => ['code' => 'product-001', 'message' => translate('messages.not_found')]
            ], 404);
        }
    }

    public function get_related_products(Request $request,$id)
    {
        if (!$request->hasHeader('zoneId')) {
            $errors = [];
            array_push($errors, ['code' => 'zoneId', 'message' => translate('messages.zone_id_required')]);
            return response()->json([
                'errors' => $errors
            ], 403);
        }
        $zone_id= $request->header('zoneId');
        if (Item::find($id)) {
            $items = ProductLogic::get_related_products($zone_id,$id);
            $items = Helpers::product_data_formatting($items, true, false, app()->getLocale());
            return response()->json($items, 200);
        }
        return response()->json([
            'errors' => ['code' => 'product-001', 'message' => translate('messages.not_found')]
        ], 404);
    }
    public function get_related_store_products(Request $request,$id)
    {
        if (!$request->hasHeader('zoneId')) {
            $errors = [];
            array_push($errors, ['code' => 'zoneId', 'message' => translate('messages.zone_id_required')]);
            return response()->json([
                'errors' => $errors
            ], 403);
        }
        $zone_id= $request->header('zoneId');
        if (Item::find($id)) {
            $items = ProductLogic::get_related_store_products($zone_id,$id);
            $items = Helpers::product_data_formatting($items, true, false, app()->getLocale());
            return response()->json($items, 200);
        }
        return response()->json([
            'errors' => ['code' => 'product-001', 'message' => translate('messages.not_found')]
        ], 404);
    }

    public function get_recommended(Request $request)
    {
        if (!$request->hasHeader('zoneId')) {
            $errors = [];
            array_push($errors, ['code' => 'zoneId', 'message' => translate('messages.zone_id_required')]);
            return response()->json([
                'errors' => $errors
            ], 403);
        }

        $type = $request->query('type', 'all');
        $filter = $request->query('filter', 'all');

        $zone_id= $request->header('zoneId');
        $items = ProductLogic::recommended_items($zone_id, $request->store_id,$request['limit'], $request['offset'], $type, $filter);
        $items['items'] = Helpers::product_data_formatting($items['items'], true, false, app()->getLocale());
        return response()->json($items, 200);
    }

    public function get_set_menus()
    {
        try {
            $items = Helpers::product_data_formatting(Item::active()->with(['rating'])->where(['set_menu' => 1, 'status' => 1])->get(), true, false, app()->getLocale());
            return response()->json($items, 200);
        } catch (\Exception $e) {
            return response()->json([
                'errors' => ['code' => 'product-001', 'message' => 'Set menu not found!']
            ], 404);
        }
    }

    public function get_product_reviews(Request $request, $item_id)
    {
        if(isset($request['limit']) && ($request['limit'] != null) && isset($request['offset']) && ($request['offset'] != null)){

            $reviews = Review::with(['customer', 'item'])->where(['item_id' => $item_id])->active()->paginate($request['limit'], ['*'], 'page', $request['offset']);
            $total = $reviews->total();
        }else{

            $reviews = Review::with(['customer', 'item'])->where(['item_id' => $item_id])->active()->get();
            $total = $reviews->count();
        }

        $storage = [];
        foreach ($reviews as $temp) {
            $temp['attachment'] = json_decode($temp['attachment']);
            $temp['item_name'] = null;
            if($temp->item)
            {
                $temp['item_name'] = $temp->item->name;
                if(count($temp->item->translations)>0)
                {
                    $translate = array_column($temp->item->translations->toArray(), 'value', 'key');
                    $temp['item_name'] = $translate['name'];
                }
            }

            unset($temp['item']);
            array_push($storage, $temp);
        }

        $data =  [
            'total_size' => $total,
            'limit' => $request['limit'],
            'offset' => $request['offset'],
            'reviews' => $storage
        ];

        return response()->json($data, 200);
    }

    public function get_product_rating($id)
    {
        try {
            $item = Item::find($id);
            $overallRating = ProductLogic::get_overall_rating($item->reviews);
            return response()->json(floatval($overallRating[0]), 200);
        } catch (\Exception $e) {
            return response()->json(['errors' => $e], 403);
        }
    }

    public function submit_product_review(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'item_id' => 'required',
            'order_id' => 'required',
            'rating' => 'required|numeric|max:5',
        ]);

        $order = Order::find($request->order_id);
        if (isset($order) == false) {
            $validator->errors()->add('order_id', translate('messages.order_data_not_found'));
        }

        $item = Item::find($request->item_id);
        if (isset($order) == false) {
            $validator->errors()->add('item_id', translate('messages.item_not_found'));
        }

        $multi_review = Review::where(['item_id' => $request->item_id, 'user_id' => $request->user()->id, 'order_id'=>$request->order_id])->first();
        if (isset($multi_review)) {
            return response()->json([
                'errors' => [
                    ['code'=>'review','message'=> translate('messages.already_submitted')]
                ]
            ], 403);
        } else {
            $review = new Review;
        }

        if ($validator->errors()->count() > 0) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }

        $image_array = [];
        if (!empty($request->file('attachment'))) {
            foreach ($request->file('attachment') as $image) {
                if ($image != null) {
                    if (!Storage::disk('public')->exists('review')) {
                        Storage::disk('public')->makeDirectory('review');
                    }
                    array_push($image_array, Storage::disk('public')->put('review', $image));
                }
            }
        }

        $order?->OrderReference?->update([
            'is_reviewed' => 1
        ]);

        $review->user_id = $request->user()->id;
        $review->item_id = $request->item_id;
        $review->order_id = $request->order_id;
        $review->module_id = $order->module_id;
        $review->comment = $request?->comment;
        $review->rating = $request->rating;
        $review->attachment = json_encode($image_array);
        $review->save();

        if($item->store)
        {
            $store_rating = StoreLogic::update_store_rating($item->store->rating, (int)$request->rating);
            $item->store->rating = $store_rating;
            $item->store->save();
        }

        $item->rating = ProductLogic::update_rating($item->rating, (int)$request->rating);
        $item->avg_rating = ProductLogic::get_avg_rating(json_decode($item->rating, true));
        $item->save();
        $item->increment('rating_count');

        return response()->json(['message' => translate('messages.review_submited_successfully')], 200);
    }

    public function item_or_store_search(Request $request){

        if (!$request->hasHeader('zoneId')) {
            $errors = [];
            array_push($errors, ['code' => 'zoneId', 'message' => translate('messages.zone_id_required')]);
            return response()->json([
                'errors' => $errors
            ], 403);
        }
        if (!$request->hasHeader('longitude') || !$request->hasHeader('latitude')) {
            $errors = [];
            array_push($errors, ['code' => 'longitude-latitude', 'message' => translate('messages.longitude-latitude_required')]);
            return response()->json([
                'errors' => $errors
            ], 403);
        }
        $validator = Validator::make($request->all(), [
            'name' => 'required',
        ]);
        $zone_id= $request->header('zoneId');
        $longitude= $request->header('longitude');
        $latitude= $request->header('latitude');

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }
        $key = explode(' ', $request->name);

       $items = Item::active()
    ->whereHas('store', function($query) use ($zone_id) {
        $query->when(config('module.current_module_data'), function($query) {
            $query->where('module_id', config('module.current_module_data')['id'])
                  ->whereHas('zone.modules', function($query) {
                      $query->where('modules.id', config('module.current_module_data')['id']);
                  });
        })->whereIn('zone_id', json_decode($zone_id, true));
    })
    ->select('id','name','image')
    ->selectRaw("
        CASE 
            WHEN name LIKE ? THEN 3
            WHEN description LIKE ? THEN 2
            ELSE 1
        END AS relevance
    ", [
        '%' . $request->name . '%',
        '%' . $request->name . '%'
    ])
    ->where(function ($q) use ($key) {
        foreach ($key as $value) {
            $q->orWhere('name', 'like', "%{$value}%")
              ->orWhere('description', 'like', "%{$value}%");
        }

        $relationships = [
            'translations' => 'value',
            'tags' => 'tag',
            'nutritions' => 'nutrition',
            'allergies' => 'allergy',
            'category.parent' => 'name',
            'category' => 'name',
            'generic' => 'generic_name',
            'ecommerce_item_details.brand' => 'name',
            'pharmacy_item_details.common_condition' => 'name',
        ];
        $q->applyRelationShipSearch(relationships: $relationships, searchParameter: $key);
    })
    ->orderByDesc('relevance')
    ->limit(30)
    ->get();


        $stores = Store::
        whereHas('zone.modules', function($query){
            $query->where('modules.id', config('module.current_module_data')['id']);
        })
        ->withOpen($longitude??0,$latitude??0)
        ->with(['discount'=>function($q){
            return $q->validate();
        }])->weekday()

        ->where(function ($q) use ($key) {
            foreach ($key as $value) {
                $q->orWhere('name', 'like', "%{$value}%");
            }

            $relationships = [
                'translations' => 'value',
                'items.nutritions' => 'nutrition',
                'items.allergies' => 'allergy',
                'items.generic' => 'generic_name',
                'items.ecommerce_item_details.brand' => 'name',
                'items.pharmacy_item_details.common_condition' => 'name'
            ];
            $q->applyRelationShipSearch(relationships:$relationships ,searchParameter:$key);
        })
        ->when(config('module.current_module_data'), function($query)use($zone_id){
            $query->module(config('module.current_module_data')['id']);
            if(!config('module.current_module_data')['all_zone_service']) {
                $query->whereIn('zone_id', json_decode($zone_id, true));
            }
        })
        ->active()
        ->limit(30)
        ->select(['id','name','logo'])
        ->get();

        return [
            'items' => $items,
            'stores' => $stores
        ];

    }

    public function get_store_condition_products(Request $request)
    {
        if (!$request->hasHeader('zoneId')) {
            $errors = [];
            array_push($errors, ['code' => 'zoneId', 'message' => translate('messages.zone_id_required')]);
            return response()->json([
                'errors' => $errors
            ], 403);
        }
        $validator = Validator::make($request->all(), [
            'store_id' => 'required',
            'limit' => 'required',
            'offset' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }

        $zone_id= $request->header('zoneId');

        $type = $request->query('type', 'all');
        $limit = $request['limit'];
        $offset = $request['offset'];

        $paginator = Item::
        whereHas('module.zones', function($query)use($zone_id){
            $query->whereIn('zones.id', json_decode($zone_id, true));
        })
        ->whereHas('store', function($query)use($zone_id){
            $query->whereIn('zone_id', json_decode($zone_id, true))->whereHas('zone.modules',function($query){
                $query->when(config('module.current_module_data'), function($query){
                    $query->where('modules.id', config('module.current_module_data')['id']);
                });
            });
        })
        ->whereHas('pharmacy_item_details',function($q){
            return $q->whereNotNull('common_condition_id');
        })
        ->whereHas('ecommerce_item_details',function($q){
            return $q->whereNotNull('brand_id');
        })
        ->when(is_numeric($request->store_id),function ($qurey) use($request){
            $qurey->where('store_id', $request->store_id);
        })
        ->when(!is_numeric($request->store_id), function ($query) use ($request) {
            $query->whereHas('store', function ($q) use ($request) {
                $q->where('slug', $request->store_id);
            });
        })
        ->active()->type($type)->latest()->paginate($limit, ['*'], 'page', $offset);
        $data=[
            'total_size' => $paginator->total(),
            'limit' => $limit,
            'offset' => $offset,
            'products' => $paginator->items()
        ];
        $data['products'] = Helpers::product_data_formatting($data['products'] , true, false, app()->getLocale());
        return response()->json($data, 200);
    }

    public function get_popular_basic_products(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'limit' => 'required',
            'offset' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }

        if (!$request->hasHeader('zoneId')) {
            $errors = [];
            array_push($errors, ['code' => 'zoneId', 'message' => translate('messages.zone_id_required')]);
            return response()->json([
                'errors' => $errors
            ], 403);
        }
        $zone_id = $request->header('zoneId');
        $type = $request->query('type', 'all');
        $product_id = $request->query('product_id')??null;
        $min = $request->query('min_price');
        $max = $request->query('max_price');
        $limit = $request['limit']??25;
        $offset = $request['offset']??1;

        $items = ProductLogic::get_popular_basic_products($zone_id, $limit, $offset, $type, $request['store_id'], $request['category_id'], $min,$max,$product_id);
        $items['categories'] = $items['categories'];
        $items['products'] = Helpers::product_data_formatting($items['products'], true, false, app()->getLocale());
        return response()->json($items, 200);
    }

    public function get_products(Request $request)
    {
        if (!$request->hasHeader('zoneId')) {
            $errors = [['code' => 'zoneId', 'message' => translate('messages.zone_id_required')]];
            return response()->json(['errors' => $errors], 403);
        }

        $data_type = $request->query('data_type', 'all');

        $zone_id = $request->header('zoneId');
        $type = $request->query('type', 'all');
        $filter = $request->query('filter', '');
        $filter = $filter?(is_array($filter)?$filter:str_getcsv(trim($filter, "[]"), ',')):'';
        $category_ids = $request->query('category_ids', '');

        // Common parameters for all product types
        $limit = $request->query('limit', 10);
        $offset = $request->query('offset', 1);
        $min_price = $request->query('min_price');
        $max_price = $request->query('max_price');
        $rating_count = $request->query('rating_count');
        $product_id = $request->query('product_id');

        switch ($data_type) {
            case 'searched':
                return $this->get_searched_products($request);
                break;
            case 'discounted':
                $items = ProductLogic::discounted_products($zone_id, $limit, $offset, $type, $category_ids, $filter, $min_price, $max_price, $rating_count);
                break;
            case 'new':
                $items = ProductLogic::get_new_products($zone_id, $type, $min_price, $max_price, $product_id, $limit, $offset, $filter, $rating_count);
                break;
            case 'category':
                $validator = Validator::make($request->all(), [
                    'category_ids' => 'required',
                ]);

                if ($validator->fails()) {
                    return response()->json(['errors' => Helpers::error_processor($validator)], 403);
                }

                $items = CategoryLogic::category_products($category_ids, $zone_id, $limit, $offset, $type, $filter, $min_price, $max_price, $rating_count);
                break;
            default:
            $items =  [
                'total_size' => 0,
                'limit' => $limit,
                'offset' => $offset,
                'products' => [],
                'categories' => [],
            ];
        }

        $items['products'] = Helpers::product_data_formatting($items['products'], true, false, app()->getLocale());
        return response()->json($items, 200);
    }



    public function getGenericNameList(){
        $names= GenericName::select(['generic_name'])->pluck('generic_name');
        return response()->json($names, 200);
    }
    public function getAllergyNameList(){
        $names= Allergy::select(['allergy'])->pluck('allergy');
        return response()->json($names, 200);
    }
    public function getNutritionNameList(){
        $names= Nutrition::select(['nutrition'])->pluck('nutrition');
        return response()->json($names, 200);
    }
 // Trending Products API
            public function get_trending_products(Request $request)
            {
                if (!$request->hasHeader('zoneId')) {
                    return response()->json([
                        'errors' => [
                            ['code' => 'zoneId', 'message' => translate('messages.zone_id_required')]
                            ]
                        ], 403);
                    }
                    
                    $type = $request->query('type', 'all');
                    $zone_id = $request->header('zoneId');
                    
                    // 🔥 Your new trending logic
                    $items = ProductLogic::trending_products($zone_id, $request['limit'] ?? 10, $request['offset'], $type);
                    
                    $items['products'] = Helpers::product_data_formatting($items['products'], true, false, app()->getLocale());
                    
                    return response()->json($items, 200);
                }

}
