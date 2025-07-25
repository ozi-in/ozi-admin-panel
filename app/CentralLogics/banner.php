<?php

namespace App\CentralLogics;

use App\Models\Banner;
use App\Models\Item;
use App\Models\Store;
use App\CentralLogics\Helpers;
use Illuminate\Support\Facades\Cache;

class BannerLogic
{
    public static function get_banners($zone_id, $featured = false,$section_id=1)
    {
        $section_id = (string) $section_id; // force to string for JSON match
        $moduleData = config('module.current_module_data');
        $moduleId = isset($moduleData['id']) ? $moduleData['id'] : 'default';
        $cacheKey = 'banners_' . md5($zone_id . '_' . ($featured ? 'featured' : 'non_featured') . '_' . $moduleId);
        // $banners = Cache::remember($cacheKey, now()->addMinutes(0), function() use ($zone_id, $featured,$section_id) {
        $banners = (function() use ($zone_id, $featured,$section_id ){
            $banners = Banner::active()
            ->when($featured, function($query){
                $query->featured();
                
            })
            
            ->when($section_id, function ($query) use ($section_id) {
                $query->whereJsonContains('section_id', $section_id);
            });
            
            if(config('module.current_module_data')) {
                $banners = $banners->whereHas('zone.modules', function($query){
                    $query->where('modules.id', config('module.current_module_data')['id']);
                })
                ->module(config('module.current_module_data')['id'])
                ->when(!config('module.current_module_data')['all_zone_service'], function($query) use ($zone_id){
                    $query->where(function($query) use($zone_id){
                        $query->where(function($query) use($zone_id){
                            $query->where('type','store_wise')
                            ->whereIn('zone_id', json_decode($zone_id, true));
                        })->orWhereIn('type', ['default', 'keyword','category_wise']);
                    });
                    
                });
            }
            
            return $banners->where(function($query) use($zone_id){
                $query->where(function($query) use($zone_id){
                    $query->where('type','store_wise')
                    ->whereIn('zone_id', json_decode($zone_id, true));
                })->orWhereIn('type', ['default', 'keyword','category_wise']);
            })
            ->whereHas('module', function($query){
                $query->active();
            })
            ->where('created_by', 'admin')
            ->get();
        })();
        
        $data = [];
        
        foreach($banners as $banner)
        {
            if($banner->type=='store_wise')
            {
                $store = Store::active()
                ->when(config('module.current_module_data'), function($query){
                    $query->whereHas('zone.modules', function($query){
                        $query->where('modules.id', config('module.current_module_data')['id']);
                    });
                })
                ->find($banner->data);
                if($store){
                    $data[]=[
                        'id'=>$banner->id,
                        'title'=>$banner->title,
                        'type'=>$banner->type,
                        'image'=>$banner->image,
                        'link'=> null,
                        'store'=> $store?Helpers::store_data_formatting($store, false):null,
                        'item'=>null,
                        'image_full_url' => $banner->image_full_url
                    ];
                }
            }
            elseif ($banner->type === 'category_wise') {
                $category = \App\Models\Category::active()->find($banner->data);
                if ($category) {
                    // Check if it's a parent category or subcategory
                    $isSubCategory = $category->parent_id != 0;                    
                    $data[] = [
                        'id' => $banner->id,
                        'title' => $banner->title,
                        'type' => $banner->type,
                        'image' => $banner->image,
                        'category' =>[
                            'id' => $category->id,
                            'name' => $category->name,
                            'parent_id'=>$category->parent_id
                        ],
                        'image_full_url' => $banner->image_full_url,                        
                    ];
                }
            }
            if($banner->type=='item_wise')
            {
                $item = Item::active()
                ->when(config('module.current_module_data'), function($query)use($zone_id) {
                    $query->whereHas('module.zones',function($query)use($zone_id){
                        $query->whereIn('zones.id', json_decode($zone_id, true));
                    });
                })
                ->find($banner->data);
                $data[]=[
                    'id'=>$banner->id,
                    'title'=>$banner->title,
                    'type'=>$banner->type,
                    'image'=>$banner->image,
                    'link'=> null,
                    'store'=> null,
                    'item'=> $item?Helpers::product_data_formatting($item, false, false, app()->getLocale()):null,
                    'image_full_url' => $banner->image_full_url
                ];
            }
            if($banner->type=='default')
            {
                $data[]=[
                    'id'=>$banner->id,
                    'title'=>$banner->title,
                    'type'=>$banner->type,
                    'image'=>$banner->image,
                    'link'=>$banner->default_link,
                    'store'=> null,
                    'item'=> null,
                    'image_full_url' => $banner->image_full_url
                ];
            }
            
            
            if($banner->type=='keyword')
            {
                $data[]=[
                    'id'=>$banner->id,
                    'title'=>$banner->title,
                    'type'=>$banner->type,
                    'image'=>$banner->image,
                    'banner_keywords'=>$banner->banner_keywords ? str_replace(","," ",$banner->banner_keywords):'',
                    'store'=> null,
                    'item'=> null,
                    'image_full_url' => $banner->image_full_url
                ];
            }
            if($banner->type == null)
            {
                $data[]=[
                    'id'=>$banner->id,
                    'title'=>$banner->title,
                    'type'=>$banner->type,
                    'image'=>$banner->image,
                    'link'=> null,
                    'store'=> null,
                    'item'=> null,
                    'image_full_url' => $banner->image_full_url
                ];
            }
        }
        return $data;
    }
}
