<?php

namespace App\Services;

use App\Traits\FileManagerTrait;
use Illuminate\Support\Facades\Config;

class BannerService
{
    use FileManagerTrait;

  public function getAddData(Object $request): array
{
    return [
        'title' => $request->title[array_search('default', $request->lang)],
        'type' => $request->banner_type,
   'section_id' =>$request->section_id,
      'banner_keywords' =>$request->banner_keywords,
        'zone_id' => $request->zone_id,
        'image' => $this->upload('banner/', 'png', $request->file('image')),
      'data' => $request->banner_type == 'store_wise' ? $request->store_id
        : ($request->banner_type == 'item_wise' ? $request->item_id
        : ($request->banner_type == 'category_wise' 
            ? ($request->subcategory_id ?? $request->category_id) // 🟢 subcategory preferred if exists
            : '')
        ),
        'module_id' => Config::get('module.current_module_id'),
        'default_link' => $request->default_link
    ];
}
    public function getUpdateData(Object $request, object $banner): array
{
    return [
        'title' => $request->title[array_search('default', $request->lang)],
        'type' => $request->banner_type,
        'zone_id' => $request->zone_id,
     'section_id' => $request->section_id,
        'image' => $request->has('image') ? $this->updateAndUpload('banner/', $banner->image, 'png', $request->file('image')) : $banner->image,
     'data' => $request->banner_type == 'store_wise' ? $request->store_id
        : ($request->banner_type == 'item_wise' ? $request->item_id
        : ($request->banner_type == 'category_wise' 
            ? ($request->subcategory_id ?? $request->category_id) // 🟢 subcategory preferred if exists
            : '')
        ),
        'module_id' => Config::get('module.current_module_id'),
        'default_link' => $request->default_link,
             'banner_keywords' =>$request->banner_keywords,
    ];
}

}
