@extends('layouts.admin.app')

@section('title', request()->product_gellary  == 1 ?  translate('Add item') : translate('Edit item'))

@push('css_or_js')
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link href="{{ asset('public/assets/admin/css/tags-input.min.css') }}" rel="stylesheet">
@endpush

@section('content')
    @php($opening_time = '')
    @php($closing_time = '')

    <div class="content container-fluid">
        <!-- Page Header -->
        <div class="page-header d-flex flex-wrap __gap-15px justify-content-between align-items-center">
            <h1 class="page-header-title">
                <span class="page-header-icon">
                    <img src="{{ asset('public/assets/admin/img/edit.png') }}" class="w--22" alt="">
                </span>
                <span>
                    {{ request()->product_gellary  == 1 ?  translate('Add_item') : translate('item_update') }}
                </span>
            </h1>
            <div class="d-flex align-items-end flex-wrap">
                @if(Config::get('module.current_module_type') == 'food')
                <div class="text--primary-2 py-1 d-flex flex-wrap align-items-center foodModalShow" type="button" >
                    <strong class="mr-2">{{translate('See_how_it_works!')}}</strong>
                    <div>
                        <i class="tio-info-outined"></i>
                    </div>
                </div>
                @else
                <div class="text--primary-2 py-1 d-flex flex-wrap align-items-center attributeModalShow" type="button" >
                    <strong class="mr-2">{{translate('See_how_it_works!')}}</strong>
                    <div>
                        <i class="tio-info-outined"></i>
                    </div>
                </div>
                @endif
            </div>
        </div>
        <!-- End Page Header -->
        <form action="javascript:" method="post" id="product_form" enctype="multipart/form-data">
            @csrf
                @if (request()->product_gellary  == 1)
                    @php($route =route('admin.item.store',['product_gellary' => request()->product_gellary ]))
                    @php($product->price = 0)
                @else
                    @php($route =route('admin.item.update', [ isset($temp_product) && $temp_product == 1 ?   $product['item_id'] : $product['id']]))
                @endif

            <input type="hidden" class="route_url" value="{{ $route ?? route('admin.item.update', [ isset($temp_product) && $temp_product == 1 ?   $product['item_id'] : $product['id']]) }}" >
            <input type="hidden" value="{{$temp_product ?? 0 }}" name="temp_product" >
            <input type="hidden" value="{{$product['id'] ?? null }}" name="item_id" >

            @php($language = \App\Models\BusinessSetting::where('key', 'language')->first())
            @php($language = $language->value ?? null)
            @php($defaultLang = str_replace('_', '-', app()->getLocale()))
            <div class="row g-2">
                <div class="col-md-6">
                    <div class="card h-100">
                        <div class="card-body">
                            @if ($language)
                                <ul class="nav nav-tabs border-0 mb-3">
                                    <li class="nav-item">
                                        <a class="nav-link lang_link active" href="#"
                                            id="default-link">{{ translate('messages.default') }}</a>
                                    </li>
                                    @foreach (json_decode($language) as $lang)
                                         
                                        <li class="nav-item">
                                            <a class="nav-link lang_link" href="#"
                                                id="{{ $lang }}-link">{{ \App\CentralLogics\Helpers::get_language_name($lang) . '(' . strtoupper($lang) . ')' }}</a>
                                        </li>
                                
                                    @endforeach
                                </ul>
                            @endif
                            @if ($language)
                                <div class="lang_form" id="default-form">
                                    <div class="form-group">
                                        <label class="input-label" for="default_name">{{ translate('messages.name') }}
                                            ({{ translate('messages.default') }})  <span class="form-label-secondary text-danger"
                                            data-toggle="tooltip" data-placement="right"
                                            data-original-title="{{ translate('messages.Required.')}}"> *
                                            </span></label>
                                        <input type="text" name="name[]" id="default_name" class="form-control"
                                            placeholder="{{ translate('messages.new_food') }}"
                                            value="{{ $product?->getRawOriginal('name') }}" required
                                             >
                                    </div>
                                    <input type="hidden" name="lang[]" value="default">
                                    <div class="form-group pt-2 mb-0">
                                        <label class="input-label"
                                            for="exampleFormControlInput1">{{ translate('messages.short_description') }}
                                            ({{ translate('messages.default') }})  <span class="form-label-secondary text-danger"
                                            data-toggle="tooltip" data-placement="right"
                                            data-original-title="{{ translate('messages.Required.')}}"> *
                                            </span></label>
                                        <textarea type="text" name="description[]" class="form-control ckeditor min--height-200">{!! $product?->getRawOriginal('description') !!}</textarea>
                                    </div>
                                </div>
                                @foreach (json_decode($language) as $lang)
                                  
                                    <?php
                                    if (count($product['translations'])) {
                                        $translate = [];
                                        foreach ($product['translations'] as $t) {
                                            if ($t->locale == $lang && $t->key == 'name') {
                                                $translate[$lang]['name'] = $t->value;
                                            }
                                            if ($t->locale == $lang && $t->key == 'description') {
                                                $translate[$lang]['description'] = $t->value;
                                            }
                                        }
                                    }
                                    ?>
                                    <div class="d-none lang_form" id="{{ $lang }}-form">
                                        <div class="form-group">
                                            <label class="input-label"
                                                for="{{ $lang }}_name">{{ translate('messages.name') }}
                                                ({{ strtoupper($lang) }})</label>
                                            <input type="text" name="name[]" id="{{ $lang }}_name"
                                                class="form-control" placeholder="{{ translate('messages.new_food') }}"
                                                value="{{ $translate[$lang]['name'] ?? '' }}"
                                                 >
                                        </div>
                                        <input type="hidden" name="lang[]" value="{{ $lang }}">
                                        <div class="form-group pt-2 mb-0">
                                            <label class="input-label"
                                                for="exampleFormControlInput1">{{ translate('messages.short_description') }} ({{ strtoupper($lang) }})</label>
                                            <textarea type="text" name="description[]" class="form-control ckeditor min--height-200">{!! $translate[$lang]['description'] ?? '' !!}</textarea>
                                        </div>
                                    </div>
                      
                                @endforeach
                            @else
                                <div id="default-form">
                                    <div class="form-group">
                                        <label class="input-label"
                                            for="exampleFormControlInput1">{{ translate('messages.name') }}
                                            ({{ translate('messages.default') }})</label>
                                        <input type="text" name="name[]" class="form-control"
                                            placeholder="{{ translate('messages.new_food') }}"
                                            value="{{ $product['name'] }}" required>
                                    </div>
                                    <input type="hidden" name="lang[]" value="default">
                                    <div class="form-group pt-2 mb-0">
                                        <label class="input-label"
                                            for="exampleFormControlInput1">{{ translate('messages.short_description') }}</label>
                                        <textarea type="text" name="description[]" class="form-control ckeditor min--height-200">{!! $product['description'] !!}</textarea>
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="card h-100">
                        <div class="card-body d-flex flex-wrap align-items-center">
                            <div class="w-100 d-flex flex-wrap __gap-15px">
                                <div class="flex-grow-1 mx-auto">
                                    <label class="text-dark d-block">
                                        {{ translate('messages.item_image') }}
                                        <small >( {{ translate('messages.ratio') }} 1:1 )</small>
                                    </label>
                                    <div class="d-flex flex-wrap __gap-12px __new-coba" id="coba">

                                        <input type="hidden" id="removedImageKeysInput" name="removedImageKeys" value="">
                                        @foreach($product->images as $key => $photo)
                                            @php($photo = is_array($photo)?$photo:['img'=>$photo,'storage'=>'public'])
                                            <div id="product_images_{{ $key }}" class="spartan_item_wrapper min-w-176px max-w-176px">
                                                <img class="img--square onerror-image"
                                                src="{{ \App\CentralLogics\Helpers::get_full_url('product',$photo['img'] ?? '',$photo['storage']) }}"
                                                    data-onerror-image="{{ asset('public/assets/admin/img/upload-img.png') }}"
                                                    alt="Product image">
                                                    {{-- <div class="pen spartan_remove_row"><i class="tio-edit"></i></div> --}}
                                                    <a href="#" data-key={{ $key }} data-photo="{{ $photo['img'] }}"
                                                    class="spartan_remove_row function_remove_img"><i class="tio-add-to-trash"></i></a>
                                                    {{-- @if (request()->product_gellary  == 1)
                                                    @else
                                                        <a href="{{ route('admin.item.remove-image', ['id' => $product['id'], 'name' => $photo['img'] ,'temp_product' => $temp_product]) }}"
                                                            class="spartan_remove_row"><i class="tio-add-to-trash"></i></a>
                                                    @endif --}}
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                                <div class="flex-grow-1 mx-auto">
                                    <label class="text-dark d-block">
                                        {{ translate('messages.item_thumbnail') }}
                                        <small class="text-danger">* ( {{ translate('messages.ratio') }} 1:1 )</small>
                                    </label>
                                    <label class="d-inline-block m-0 position-relative">
                                        <img class="img--176 border onerror-image" id="viewer"
                                        src="{{ $product['image_full_url'] ?? asset('public/assets/admin/img/upload-img.png') }}"
                                            data-onerror-image="{{ asset('public/assets/admin/img/upload-img.png') }}"
                                            alt="thumbnail" />
                                        <div class="icon-file-group">
                                            <div class="icon-file">
                                                <input type="file" name="image" id="customFileEg1" class="custom-file-input read-url"
                                                    accept=".webp, .jpg, .png, .jpeg, .webp, .gif, .bmp, .tif, .tiff|image/*" >
                                                    <i class="tio-edit"></i>
                                            </div>
                                        </div>
                                    </label>
                                </div>

                                <div class="flex-grow-1 mx-auto">
    <label class="text-dark d-block mb-4 mb-xl-5">
        {{ translate('messages.item_video') }}
        <small class="text-info">(Supported formats: mp4, webm, ogg | Max: 8MB)</small>
    </label>
    <label class="d-inline-block m-0 position-relative">
        {{-- If video exists, show video, else show placeholder --}}
        @if (!empty($product['video_full_url']))
            <video id="videoPreview" width="300" height="176" controls class="border">
                <source src="{{ $product['video_full_url'] }}" type="video/mp4">
                {{ translate('messages.video_not_supported') }}
            </video>
            <img id="videoPlaceholder" class="img--176 border" src="{{ asset('public/assets/admin/img/video-placeholder.png') }}" alt="Video Placeholder" style="display: none;" />
        @else
            <video id="videoPreview" width="300" height="176" controls class="border" style="display: none;"></video>
            <img id="videoPlaceholder" class="img--176 border" src="{{ asset('public/assets/admin/img/video-placeholder.png') }}" alt="Video Placeholder" />
        @endif

        <div class="icon-file-group">
            <div class="icon-file">
                <input type="file" name="video" id="customVideoFile" class="custom-file-input d-none"
                       accept="video/mp4,video/webm,video/ogg">
                <i class="tio-edit"></i>
            </div>
        </div>
    </label>
</div>

                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-12">
                    <div class="card shadow--card-2 border-0">
                        <div class="card-header">
                            <h5 class="card-title">
                                <span class="card-header-icon mr-2">
                                    <i class="tio-dashboard-outlined"></i>
                                </span>
                                <span> {{ translate('item_details') }} </span>
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="row g-2">
                                <div class="col-sm-6 col-lg-3">
                                    <div class="form-group mb-0">
                                        <label class="input-label" for="store_id">{{ translate('messages.store') }}  <span class="form-label-secondary text-danger"
                                            data-toggle="tooltip" data-placement="right"
                                            data-original-title="{{ translate('messages.Required.')}}"> *
                                            </span><span
                                                class="input-label-secondary"></span></label>
                                        <select name="store_id"
                                            data-placeholder="{{ translate('messages.select_store') }}"
                                            id="store_id" class="js-data-example-ajax form-control"
                                            title="{{ translate('messages.select_store') }}" {{ isset(request()->product_gellary) == false ?'required' : '' }}
                                            oninvalid="this.setCustomValidity('{{ translate('messages.please_select_store') }}')">

                                            @if (isset($product->store) && request()->product_gellary  != 1)
                                                <option value="{{ $product->store_id }}" selected="selected">
                                                    {{ $product->store->name }}</option>
                                            @endif

                                        </select>
                                    </div>
                                </div>
                                <div class="col-sm-6 col-lg-3">
                                    <div class="form-group mb-0">
                                        <label class="input-label"
                                            for="category_id">{{ translate('messages.category') }} <span class="form-label-secondary text-danger"
                                            data-toggle="tooltip" data-placement="right"
                                            data-original-title="{{ translate('messages.Required.')}}"> *
                                            </span></label>
                                        <select name="category_id" class="js-data-example-ajax form-control"
                                            id="category_id">
                                            @if ($category)
                                                <option value="{{ $category['id'] }}">{{ $category['name'] }}</option>
                                            @endif
                                        </select>
                                    </div>
                                </div>
                                <div class="col-sm-6 col-lg-3">
                                    <div class="form-group mb-0">
                                        <label class="input-label"
                                            for="exampleFormControlSelect1">{{ translate('messages.sub_category') }}<span
                                                class="form-label-secondary" data-toggle="tooltip" data-placement="right"
                                                data-original-title="{{ translate('messages.category_required_warning') }}"><img
                                                    src="{{ asset('/public/assets/admin/img/info-circle.svg') }}"
                                                    alt="{{ translate('messages.category_required_warning') }}"></span></label>
                                        <select name="sub_category_id" class="js-data-example-ajax form-control"
                                            id="sub-categories">
                                            @if (isset($sub_category))
                                                <option value="{{ $sub_category['id'] }}">{{ $sub_category['name'] }}
                                                </option>
                                            @endif
                                        </select>
                                    </div>
                                </div>
                                <div class="col-sm-6 col-lg-3" id="condition_input">
                                    <div class="form-group mb-0">
                                        <label class="input-label" for="condition_id">{{ translate('messages.Suitable_For') }}<span
                                                class="input-label-secondary"></span></label>
                                        <select name="condition_id" id="condition_id"
                                            data-placeholder="{{ translate('messages.Select_Condition') }}"
                                            id="condition_id" class="js-data-example-ajax form-control"
                                            oninvalid="this.setCustomValidity('{{ translate('messages.Select_Condition') }}')">
                                            @if (isset($product->pharmacy_item_details?->common_condition_id))
                                                <option value="{{ $product->pharmacy_item_details->common_condition_id }}" selected="selected">
                                                    {{ $product->pharmacy_item_details?->common_condition->name }}</option>
                                            @elseif((isset($temp_product) && $temp_product == 1 && $product->common_condition_id))
                                            <option value="{{ $product->common_condition_id }}" selected="selected">
                                                {{ $product->common_condition->name }}</option>
                                            @endif

                                        </select>
                                    </div>
                                </div>
                                <div class="col-sm-6 col-lg-3" id="brand_input">
                                    <div class="form-group mb-0">
                                        <label class="input-label" for="brand_id">{{ translate('messages.Brand') }}<span
                                                class="input-label-secondary"></span></label>
                                        <select name="brand_id" id="brand_id"
                                            data-placeholder="{{ translate('messages.Select_brand') }}"
                                            id="brand_id" class="js-data-example-ajax form-control"
                                            oninvalid="this.setCustomValidity('{{ translate('messages.Select_brand') }}')">
                                            @if (isset($product->ecommerce_item_details?->brand_id))
                                                <option value="{{ $product->ecommerce_item_details->brand_id }}" selected="selected">
                                                    {{ $product->ecommerce_item_details?->brand->name }}</option>
                                            @elseif((isset($temp_product) && $temp_product == 1 && $product->brand_id))
                                            <option value="{{ $product->brand_id }}" selected="selected">
                                                {{ $product->brand->name }}</option>
                                            @endif

                                        </select>
                                    </div>
                                </div>
                                <div class="col-sm-6 col-lg-3" id="unit_input">
                                    <div class="form-group mb-0">
                                        <label class="input-label text-capitalize"
                                            for="unit">{{ translate('messages.unit') }}</label>
                                        <select name="unit" class="form-control js-select2-custom">
                                            @foreach (\App\Models\Unit::all() as $unit)
                                                <option value="{{ $unit->id }}"
                                                    {{ $unit->id == $product->unit_id ? 'selected' : '' }}>
                                                    {{ $unit->unit }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                                <div class="col-sm-6 col-lg-3" id="veg_input">
                                    <div class="form-group mb-0">
                                        <label class="input-label"
                                            for="exampleFormControlInput1">{{ translate('messages.item_type') }}</label>
                                        <select name="veg" class="form-control js-select2-custom">
                                            <option value="0" {{ $product['veg'] == 0 ? 'selected' : '' }}>
                                                {{ translate('messages.non_veg') }}</option>
                                            <option value="1" {{ $product['veg'] == 1 ? 'selected' : '' }}>
                                                {{ translate('messages.veg') }}</option>
                                        </select>
                                    </div>
                                </div>
                                @if(Config::get('module.current_module_type') == 'grocery' || Config::get('module.current_module_type') == 'food')
                                    @if (isset($temp_product) && $temp_product == 1 )
                                        @php($product_nutritions = \App\Models\Nutrition::whereIn('id', json_decode($product?->nutrition_ids))->pluck('id'))
                                        @php($product_allergies = \App\Models\Allergy::whereIn('id', json_decode($product?->allergy_ids))->pluck('id'))
                                    @else
                                        @php($product_nutritions = $product->nutritions->pluck('id'))
                                        @php($product_allergies = $product->allergies->pluck('id'))
                                    @endif

                                    <div class="col-sm-6" id="nutrition">
                                        <label class="input-label" for="sub-categories">
                                            {{translate('Nutrition')}}
                                            <span class="input-label-secondary" title="{{ translate('Specify the necessary keywords relating to energy values for the item.') }}" data-toggle="tooltip">
                                                <i class="tio-info-outined"></i>
                                            </span>
                                        </label>
                                        <select name="nutritions[]" class="form-control multiple-select2" data-placeholder="{{ translate('messages.Type your content and press enter') }}" multiple>
                                            @foreach (\App\Models\Nutrition::all() as $nutrition)
                                                <option value="{{ $nutrition->nutrition }}" {{ $product_nutritions->contains($nutrition->id) ? 'selected' : '' }}>{{ $nutrition->nutrition }}</option>
                                            @endforeach
                                        </select>
                                    </div>


                                    <div class="col-sm-6" id="allergy">
                                        <label class="input-label" for="sub-categories">
                                            {{translate('Allegren Ingredients')}}
                                            <span class="input-label-secondary" title="{{ translate('Specify the ingredients of the item which can make a reaction as an allergen.') }}" data-toggle="tooltip">
                                                <i class="tio-info-outined"></i>
                                            </span>
                                        </label>
                                        <select name="allergies[]" class="form-control multiple-select2" data-placeholder="{{ translate('messages.Type your content and press enter') }}" multiple>
                                            @foreach (\App\Models\Allergy::all() as $allergy)
                                                <option value="{{ $allergy->allergy }}" {{ $product_allergies->contains($allergy->id) ? 'selected' : '' }}>{{ $allergy->allergy }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                @endif


                                <div class="col-sm-6 col-lg-3" id="maximum_cart_quantity">
                                    <div class="form-group mb-0">
                                        <label class="input-label"
                                            for="maximum_cart_quantity">{{ translate('messages.Maximum_Purchase_Quantity_Limit') }}
                                            <span
                                            class="input-label-secondary text--title" data-toggle="tooltip"
                                            data-placement="right"
                                            data-original-title="{{ translate('If_this_limit_is_exceeded,_customers_can_not_buy_the_item_in_a_single_purchase.') }}">
                                            <i class="tio-info-outined"></i>
                                        </span>
                                        </label>
                                        <input type="number" placeholder="{{ translate('messages.Ex:_10') }}" class="form-control" name="maximum_cart_quantity" min="0" value="{{ $product->maximum_cart_quantity }}" id="cart_quantity">
                                    </div>
                                </div>
                                <div class="col-sm-6 col-lg-3" id="organic">
                                    <div class="form-check mb-0 p-6">
                                        <input class="form-check-input" name="organic" type="checkbox" value="1" id="flexCheckDefault" {{ $product->organic == 1?'checked':'' }}>
                                        <label class="form-check-label" for="flexCheckDefault">
                                          {{ translate('messages.is_organic') }}
                                        </label>
                                      </div>
                                </div>
                                @if(Config::get('module.current_module_type') == 'pharmacy')
                                <div class="col-sm-6 col-lg-3" id="is_prescription_required">
                                    <div class="form-check mb-0 p-6">
                                        <input class="form-check-input" name="is_prescription_required" type="checkbox" value="1" id="flexCheckDefaultprescription" {{ $product->pharmacy_item_details?->is_prescription_required == 1?'checked':((isset($temp_product) && $temp_product == 1 && $product->is_prescription_required ==1)?'checked':'') }}>
                                        <label class="form-check-label" for="flexCheckDefaultprescription">
                                          {{ translate('messages.is_prescription_required') }}
                                        </label>
                                      </div>
                                </div>
                                <div class="col-sm-6 col-lg-3" id="basic">
                                    <div class="form-check mb-0 p-6">
                                        <input class="form-check-input" name="basic" type="checkbox" value="1" id="flexCheckDefaultbasic" {{ $product->pharmacy_item_details?->is_basic == 1?'checked':((isset($temp_product) && $temp_product == 1 && $product->basic ==1)?'checked':'') }}>
                                        <label class="form-check-label" for="flexCheckDefaultbasic">
                                          {{ translate('messages.Is_Basic_Medicine') }}
                                        </label>
                                      </div>
                                </div>

                                    <div class="col-sm-6" id="generic_name">
                                        <label class="input-label" for="sub-categories">
                                            {{translate('generic_name')}}
                                            <span class="input-label-secondary" title="{{ translate('Specify the medicine`s active ingredient that makes it work') }}" data-toggle="tooltip">
                                            <i class="tio-info-outined"></i>
                                        </span>
                                        </label>
                                        <div class="dropdown suggestion_dropdown">
                                            <input type="text" class="form-control" data-toggle="dropdown" name="generic_name" value="{{ isset($temp_product) && $temp_product == 1 ?  \App\Models\GenericName::where('id', json_decode($product?->generic_ids))->first()?->generic_name : $product->generic->pluck('generic_name')->first() }}" autocomplete="off">
                                            @if(count(\App\Models\GenericName::select(['generic_name'])->get())>0)
                                            <div class="dropdown-menu">
                                                @foreach (\App\Models\GenericName::select(['generic_name'])->get() as $generic_name)
                                                <div class="dropdown-item">{{ $generic_name->generic_name }}</div>
                                                @endforeach
                                            </div>
                                            @endif
                                        </div>
                                    </div>

                                @endif

                                @if(Config::get('module.current_module_type') == 'grocery' || Config::get('module.current_module_type') == 'food')
                                    <div class="col-sm-6 col-lg-3" id="halal">
                                        <div class="form-check mb-0 p-6">
                                            <input class="form-check-input" name="is_halal" type="checkbox" value="1" id="flexCheckDefault1" {{ $product->is_halal == 1?'checked':((isset($temp_product) && $temp_product == 1 && $product->is_halal ==1)?'checked':'') }}>
                                            <label class="form-check-label" for="flexCheckDefault1">
                                                {{ translate('messages.Is_It_Halal') }}
                                            </label>
                                        </div>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-6" id="addon_input">
                    <div class="card shadow--card-2 border-0">
                        <div class="card-header">
                            <h5 class="card-title">
                                <span class="card-header-icon"><i class="tio-dashboard-outlined"></i></span>
                                <span>{{ translate('messages.addon') }}</span>
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="form-group mb-0">
                                <label class="input-label"
                                    for="exampleFormControlSelect1">{{ translate('messages.addon') }}<span
                                        class="form-label-secondary" data-toggle="tooltip" data-placement="right"
                                        data-original-title="{{ translate('messages.store_required_warning') }}"><img
                                            src="{{ asset('/public/assets/admin/img/info-circle.svg') }}"
                                            alt="{{ translate('messages.store_required_warning') }}"></span></label>
                                <select name="addon_ids[]" class="form-control js-select2-custom" multiple="multiple"
                                    id="add_on">
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-6" id="time_input">
                    <div class="card shadow--card-2 border-0">
                        <div class="card-header">
                            <h5 class="card-title">
                                <span class="card-header-icon"><i class="tio-date-range"></i></span>
                                <span>{{ translate('time_schedule') }}</span>
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="row g-2">
                                <div class="col-sm-6">
                                    <div class="form-group mb-0">
                                        <label class="input-label"
                                            for="exampleFormControlInput1">{{ translate('messages.available_time_starts') }}</label>
                                        <input type="time" value="{{ $product['available_time_starts'] }}"
                                            name="available_time_starts" class="form-control" id="available_time_starts"
                                            placeholder="{{ translate('messages.Ex:') }} 10:30 am">
                                    </div>
                                </div>
                                <div class="col-sm-6">
                                    <div class="form-group mb-0">
                                        <label class="input-label"
                                            for="exampleFormControlInput1">{{ translate('messages.available_time_ends') }}</label>
                                        <input type="time" value="{{ $product['available_time_ends'] }}"
                                            name="available_time_ends" class="form-control" id="available_time_ends"
                                            placeholder="5:45 pm">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-12">
                    <div class="card shadow--card-2 border-0">
                        <div class="card-header">
                            <h5 class="card-title">
                                <span class="card-header-icon"><i class="tio-label-outlined"></i></span>
                                <span>{{ translate('Price Information') }}</span>
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="row g-2">
                                <div class="col-sm-6 col-lg-3">
                                    <div class="form-group mb-0">
                                        <label class="input-label"
                                            for="exampleFormControlInput1">{{ translate('messages.price') }}  <span class="form-label-secondary text-danger"
                                            data-toggle="tooltip" data-placement="right"
                                            data-original-title="{{ translate('messages.Required.')}}"> *
                                            </span></label>
                                        <input type="number" value="{{ $product->price }}" min="0"
                                            max="999999999999.99" name="price" class="form-control" step="0.01"
                                            placeholder="{{ translate('messages.Ex:') }} 100" required>
                                    </div>
                                </div>
                                <div class="col-sm-6 col-lg-3" id="stock_input">
                                    <div class="form-group mb-0">
                                        <label class="input-label"
                                            for="total_stock">{{ translate('messages.total_stock') }}</label>
                                        <input type="number" class="form-control" name="current_stock" min="0"
                                            value="{{ $product->stock }}" id="quantity">
                                    </div>
                                </div>
                                <div class="col-sm-6 col-lg-3">
                                    <div class="form-group mb-0">
                                        <label class="input-label"
                                            for="exampleFormControlInput1">{{ translate('messages.discount_type') }} <span class="form-label-secondary text-danger"
                                            data-toggle="tooltip" data-placement="right"
                                            data-original-title="{{ translate('messages.Required.')}}"> *
                                            </span><span
                                                class="input-label-secondary text--title" data-toggle="tooltip"
                                                data-placement="right"
                                                data-original-title="{{ translate('Admin_shares_the_same_percentage/amount_on_discount_as_he_takes_commissions_from_stores.') }}">
                                                <i class="tio-info-outined"></i>
                                            </span></label>
                                        <select name="discount_type" id="discount_type" class="form-control js-select2-custom">
                                            <option value="percent"
                                                {{ $product['discount_type'] == 'percent' ? 'selected' : '' }}>
                                                {{ translate('messages.percent') }} (%)
                                            </option>
                                            <option value="amount"
                                                {{ $product['discount_type'] == 'amount' ? 'selected' : '' }}>
                                                {{ translate('messages.amount') }} ({{ \App\CentralLogics\Helpers::currency_symbol() }})
                                            </option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-sm-6 col-lg-3">
                                    <div class="form-group mb-0">
                                        <label class="input-label"
                                            for="exampleFormControlInput1">{{ translate('messages.discount') }}
                                        <span id=symble>  {{  $product['discount_type'] == 'amount' ? ( \App\CentralLogics\Helpers::currency_symbol()) : '(%)' }}</span>
                                            <span class="form-label-secondary text-danger"
                                            data-toggle="tooltip" data-placement="right"
                                            data-original-title="{{ translate('messages.Required.')}}"> *
                                            </span></label>
                                        <input type="number" min="0" value="{{ $product['discount'] }}"
                                            max="999999999" name="discount" class="form-control"
                                            placeholder="{{ translate('messages.Ex:') }} 100">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-12" id="food_variation_section">
                    <div class="card shadow--card-2 border-0">
                        <div class="card-header flex-wrap">
                            <h5 class="card-title">
                                <span class="card-header-icon mr-2">
                                    <i class="tio-canvas-text"></i>
                                </span>
                                <span>{{ translate('messages.food_variations') }}</span>
                            </h5>
                            <a class="btn text--primary-2" id="add_new_option_button">
                                {{ translate('add_new_variation') }}
                                <i class="tio-add"></i>
                            </a>
                        </div>
                        <div class="card-body">
                            <div id="add_new_option">
                                @if (isset($product->food_variations) && count(json_decode($product->food_variations,true))>0)
                                    @foreach (json_decode($product->food_variations, true) as $key_choice_options => $item)
                                        @if (isset($item['price']))
                                        @break

                                    @else
                                        @include('admin-views.product.partials._new_variations', [
                                            'item' => $item,
                                            'key' => $key_choice_options + 1,
                                        ])
                                    @endif
                                    @endforeach
                                @endif
                            </div>

                                <!-- Empty Variation -->
                                @if (!isset($product->food_variations) || count(json_decode($product->food_variations,true))<1)
                                <div id="empty-variation">
                                    <div class="text-center">
                                        <img src="{{ asset('/public/assets/admin/img/variation.png') }}" alt="">
                                        <div>{{ translate('No variation added') }}</div>
                                    </div>
                                </div>
                            @endif
                        </div>
                </div>
            </div>
            <div class="col-md-12" id="attribute_section">
                <div class="card shadow--card-2 border-0">
                    <div class="card-header">
                        <h5 class="card-title">
                            <span class="card-header-icon"><i class="tio-canvas-text"></i></span>
                            <span>{{ translate('attribute') }}</span>
                        </h5>
                    </div>
                    <div class="card-body pb-0">
                        <div class="row g-2">
                            <div class="col-12">
                                <div class="form-group mb-0">
                                    <label class="input-label"
                                        for="exampleFormControlSelect1">{{ translate('messages.attribute') }}<span
                                            class="input-label-secondary"></span></label>
                                    <select name="attribute_id[]" id="choice_attributes"
                                        class="form-control js-select2-custom" multiple="multiple">
                                        @foreach (\App\Models\Attribute::orderBy('name')->get() as $attribute)
                                            <option value="{{ $attribute['id'] }}"
                                                {{ in_array($attribute->id, json_decode($product['attributes'], true)) ? 'selected' : '' }}>
                                                {{ $attribute['name'] }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>

                            <div class="col-md-12">
                                <div class="table-responsive">
                                    <div class="customer_choice_options d-flex __gap-24px"
                                        id="customer_choice_options">
                                        @include('admin-views.product.partials._choices', [
                                            'choice_no' => json_decode($product['attributes']),
                                            'choice_options' => json_decode($product['choice_options'], true),
                                        ])
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-12">
                                <div class="variant_combination" id="variant_combination">
                                    @include('admin-views.product.partials._edit-combinations', [
                                        'combinations' => json_decode($product['variations'], true),
                                        'stock' => config('module.' . $product->module->module_type)['stock'],
                                    ])
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-12">
                <div class="card shadow--card-2 border-0">
                    <div class="card-header">
                        <h5 class="card-title">
                            <span class="card-header-icon"><i class="tio-label"></i></span>
                            <span>{{ translate('tags') }}</span>
                        </h5>
                    </div>
                    <div class="card-body pb-0">
                        <div class="row g-2">
                            <div class="col-12">
                                @if (isset($temp_product) && $temp_product == 1 )
                                <div class="form-group producttag">
                                    @php( $tags =\App\Models\Tag::whereIn('id',json_decode($product?->tag_ids) )->get('tag'))
                                    <input type="text" class="form-control" name="tags" placeholder="Enter tags" value="@foreach($tags as $c) {{$c->tag.','}} @endforeach" data-role="tagsinput">
                                </div>
                                @else
                                <div class="form-group producttag">
                                    <input type="text" class="form-control" name="tags" placeholder="Enter tags" value="@foreach($product->tags as $c) {{$c->tag.','}} @endforeach" data-role="tagsinput">
                                </div>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-12">
                <div class="btn--container justify-content-end">
                    <button type="reset" id="reset_btn"
                        class="btn btn--reset">{{ translate('messages.reset') }}</button>
                    <button type="submit" class="btn btn--primary">{{ isset($temp_product) && $temp_product == 1  ? translate('Edit_&_Approve') : translate('messages.submit')  }}</button>
                </div>
            </div>
        </div>
    </form>
</div>

<div class="modal" id="food-modal">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-body">
                <button type="button" class="close foodModalClose" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                  </button>
                <div class="embed-responsive embed-responsive-16by9">
                    <iframe class="embed-responsive-item" src="https://www.youtube.com/embed/xG8fO7TXPbk" title="YouTube video player" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" allowfullscreen></iframe>
                  </div>
            </div>
        </div>
    </div>
</div>

<div class="modal" id="attribute-modal">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-body">
                <button type="button" class="close attributeModalClose" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                  </button>
                <div class="embed-responsive embed-responsive-16by9">
                    <iframe class="embed-responsive-item" src="https://www.youtube.com/embed/xG8fO7TXPbk" title="YouTube video player" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" allowfullscreen></iframe>
                  </div>
            </div>
        </div>
    </div>
</div>
@endsection


@push('script_2')
<script src="{{ asset('public/assets/admin') }}/js/tags-input.min.js"></script>
<script src="{{ asset('public/assets/admin/js/spartan-multi-image-picker.js') }}"></script>
<script>
    "use strict";
     let removedImageKeys = [];
    let element = "";


    $(document).on('click','.function_remove_img' ,function(){
    let key = $(this).data('key');
        let photo = $(this).data('photo');
        function_remove_img(key,photo);
    });

        function function_remove_img(key,photo) {
        $('#product_images_' + key).addClass('d-none');
        removedImageKeys.push(photo);
        $('#removedImageKeysInput').val(removedImageKeys.join(','));
    }


    function show_min_max(data) {
        console.log(data);
        $('#min_max1_' + data).removeAttr("readonly");
        $('#min_max2_' + data).removeAttr("readonly");
        $('#min_max1_' + data).attr("required", "true");
        $('#min_max2_' + data).attr("required", "true");
    }

    function hide_min_max(data) {
        console.log(data);
        $('#min_max1_' + data).val(null).trigger('change');
        $('#min_max2_' + data).val(null).trigger('change');
        $('#min_max1_' + data).attr("readonly", "true");
        $('#min_max2_' + data).attr("readonly", "true");
        $('#min_max1_' + data).attr("required", "false");
        $('#min_max2_' + data).attr("required", "false");
    }

     $(document).on('change', '.show_min_max', function () {
         let data = $(this).data('count');
         show_min_max(data);
     });

     $(document).on('change', '#discount_type', function () {
         let data =  document.getElementById("discount_type");
         if(data.value === 'amount'){
             $('#symble').text("({{ \App\CentralLogics\Helpers::currency_symbol() }})");
            }
            else{
             $('#symble').text("(%)");
         }
     });

     $(document).on('change', '.hide_min_max', function () {
         let data = $(this).data('count');
         hide_min_max(data);
     });

    let count =   $('.count_div').length;

    $(document).ready(function() {
        $("#add_new_option_button").click(function(e) {
            $('#empty-variation').hide();
            count++;
            let add_option_view = `
                    <div class="__bg-F8F9FC-card count_div view_new_option mb-2">
                        <div>
                            <div class="d-flex align-items-center justify-content-between mb-3">
                                <label class="form-check form--check">
                                    <input id="options[` + count + `][required]" name="options[` + count + `][required]" class="form-check-input" type="checkbox">
                                    <span class="form-check-label">{{ translate('Required') }}</span>
                                </label>
                                <div>
                                    <button type="button" class="btn btn-danger btn-sm delete_input_button"
                                        title="{{ translate('Delete') }}">
                                        <i class="tio-add-to-trash"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="row g-2">
                                <div class="col-xl-4 col-lg-6">
                                    <label for="">{{ translate('name') }}</label>
                                    <input required name=options[` + count +
                `][name] class="form-control new_option_name" type="text" data-count="`+
                count +`">
                                </div>

                                <div class="col-xl-4 col-lg-6">
                                    <div>
                                        <label class="input-label text-capitalize d-flex align-items-center"><span class="line--limit-1">{{ translate('messages.selcetion_type') }} </span>
                                        </label>
                                        <div class="resturant-type-group px-0">
                                            <label class="form-check form--check mr-2 mr-md-4">
                                                <input class="form-check-input show_min_max" data-count="`+count+`" type="radio" value="multi"
                                                name="options[` + count + `][type]" id="type` + count +
                `" checked
                                                >
                                                <span class="form-check-label">
                                                    {{ translate('Multiple Selection') }}
                                                </span>
                                            </label>

                                            <label class="form-check form--check mr-2 mr-md-4">
                                                <input class="form-check-input hide_min_max" data-count="`+count+`" type="radio" value="single"
                                                name="options[` + count + `][type]" id="type` + count +
                `"
                                                >
                                                <span class="form-check-label">
                                                    {{ translate('Single Selection') }}
                                                </span>
                                            </label>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-xl-4 col-lg-6">
                                    <div class="row g-2">
                                        <div class="col-6">
                                            <label for="">{{ translate('Min') }}</label>
                                            <input id="min_max1_` + count + `" required  name="options[` + count + `][min]" class="form-control" type="number" min="1">
                                        </div>
                                        <div class="col-6">
                                            <label for="">{{ translate('Max') }}</label>
                                            <input id="min_max2_` + count + `"   required name="options[` + count + `][max]" class="form-control" type="number" min="1">
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div id="option_price_` + count + `" >
                                <div class="bg-white border rounded p-3 pb-0 mt-3">
                                    <div  id="option_price_view_` + count + `">
                                        <div class="row g-3 add_new_view_row_class mb-3">
                                            <div class="col-md-4 col-sm-6">
                                                <label for="">{{ translate('Option_name') }}</label>
                                                <input class="form-control" required type="text" name="options[` +
                count +
                `][values][0][label]" id="">
                                            </div>
                                            <div class="col-md-4 col-sm-6">
                                                <label for="">{{ translate('Additional_price') }}</label>
                                                <input class="form-control" required type="number" min="0" step="0.01" name="options[` +
                count + `][values][0][optionPrice]" id="">
                                            </div>
                                        </div>
                                    </div>
                                    <div class="row mt-3 p-3 mr-1 d-flex "  id="add_new_button_` + count +
                `">
                                        <button type="button" class="btn btn--primary btn-outline-primary add_new_row_button" data-count="`+
                count +`" >{{ translate('Add_New_Option') }}</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>`;

            $("#add_new_option").append(add_option_view);
        });
    });

    function new_option_name(value, data) {
        $("#new_option_name_" + data).empty();
        $("#new_option_name_" + data).text(value)
        console.log(value);
    }

    function removeOption(e) {
        element = $(e);
        element.parents('.view_new_option').remove();
    }

    $(document).on('click', '.delete_input_button', function () {
        let e = $(this);
        removeOption(e);
    });

    function deleteRow(e) {
        element = $(e);
        element.parents('.add_new_view_row_class').remove();
    }

    $(document).on('click', '.deleteRow', function () {
        let e = $(this);
        deleteRow(e);
    });
    let countRow = 0;

    function add_new_row_button(data) {
        // count = data;
        countRow = 1 + $('#option_price_view_' + data).children('.add_new_view_row_class').length;
        let add_new_row_view = `
            <div class="row add_new_view_row_class mb-3 position-relative pt-3 pt-sm-0">
                <div class="col-md-4 col-sm-5">
                        <label for="">{{ translate('Option_name') }}</label>
                        <input class="form-control" required type="text" name="options[` + data + `][values][` +
            countRow + `][label]" id="">
                    </div>
                    <div class="col-md-4 col-sm-5">
                        <label for="">{{ translate('Additional_price') }}</label>
                        <input class="form-control"  required type="number" min="0" step="0.01" name="options[` +
                        data +
            `][values][` + countRow + `][optionPrice]" id="">
                    </div>
                    <div class="col-sm-2 max-sm-absolute">
                        <label class="d-none d-sm-block">&nbsp;</label>
                        <div class="mt-1">
                            <button type="button" class="btn btn-danger btn-sm deleteRow"
                                title="{{ translate('Delete') }}">
                                <i class="tio-add-to-trash"></i>
                            </button>
                        </div>
                </div>
            </div>`;
        $('#option_price_view_' + data).append(add_new_row_view);

    }

     $(document).on('click', '.add_new_row_button', function () {
         let data = $(this).data('count');
         add_new_row_button(data);
     });

     $(document).on('keyup', '.new_option_name', function () {
         let data = $(this).data('count');
         let value = $(this).val();
         new_option_name(value, data);
     });

     $('#store_id').on('change', function () {
         let route = '{{url('/')}}/admin/store/get-addons?data[]=0&store_id=';
         let store_id = $(this).val();
         let id = 'add_on';
         getStoreData(route, store_id, id);
     });

    function getStoreData(route, store_id, id) {
        $.get({
            url: route + store_id,
            dataType: 'json',
            success: function(data) {
                $('#' + id).empty().append(data.options);
            },
        });
    }

    function getRequest(route, id) {
        $.get({
            url: route,
            dataType: 'json',
            success: function(data) {
                $('#' + id).empty().append(data.options);
            },
        });
    }

    function readURL(input) {
        if (input.files && input.files[0]) {
            let reader = new FileReader();

            reader.onload = function(e) {
                $('#viewer').attr('src', e.target.result);
            }

            reader.readAsDataURL(input.files[0]);
        }
    }

    $("#customFileEg1").change(function() {
        readURL(this);
        $('#image-viewer-section').show(1000)
    });

    $(document).ready(function() {
        @if (count(json_decode($product['add_ons'], true)) > 0)
            getStoreData(
                '{{ url('/') }}/admin/store/get-addons?@foreach (json_decode($product['add_ons'], true) as $addon)data[]={{ $addon }}& @endforeach store_id=',
                '{{ $product['store_id'] }}', 'add_on');
        @else
            getStoreData('{{ url('/') }}/admin/store/get-addons?data[]=0&store_id=',
                '{{ $product['store_id'] }}', 'add_on');
        @endif
    });

    let module_id = {{ $product->module_id }};
    let module_type = "{{ $product->module->module_type }}";
    let parent_category_id = {{ $category ? $category->id : 0 }};
    <?php
    $module_data = config('module.' . $product->module->module_type);
    unset($module_data['description']);
    ?>
    let module_data = {{ str_replace('"', '', json_encode($module_data)) }};
    let stock = {{ $product->module->module_type == 'food' ? 'false' : 'true' }};
    input_field_visibility_update();

    function modulChange(id) {
        $.get({
            url: "{{ url('/') }}/admin/module/" + id,
            dataType: 'json',
            success: function(data) {
                module_data = data.data;
                stock = module_data.stock;
                input_field_visibility_update();
                combination_update();
            },
        });
        module_id = id;
    }

    function input_field_visibility_update() {
        if (module_data.stock) {
            $('#stock_input').show();
        } else {
            $('#stock_input').hide();
        }
        if (module_data.add_on) {
            $('#addon_input').show();
        } else {
            $('#addon_input').hide();
        }

        if (module_data.item_available_time) {
            $('#time_input').show();
        } else {
            $('#time_input').hide();
        }

        if (module_data.veg_non_veg) {
            $('#veg_input').show();
        } else {
            $('#veg_input').hide();
        }

        if (module_data.unit) {
            $('#unit_input').show();
        } else {
            $('#unit_input').hide();
        }
        if (module_data.common_condition) {
            $('#condition_input').show();
        } else {
            $('#condition_input').hide();
        }
        if (module_data.brand) {
            $('#brand_input').show();
        } else {
            $('#brand_input').hide();
        }
        if (module_type == 'food') {
            $('#food_variation_section').show();
            $('#attribute_section').hide();
        } else {
            $('#food_variation_section').hide();
            $('#attribute_section').show();
        }
        if (module_data.organic) {
            $('#organic').show();
        } else {
            $('#organic').hide();
        }
        if (module_data.basic) {
            $('#basic').show();
        } else {
            $('#basic').hide();
        }
        if (module_data.nutrition) {
            $('#nutrition').show();
        } else {
            $('#nutrition').hide();
        }
        if (module_data.allergy) {
            $('#allergy').show();
        } else {
            $('#allergy').hide();
        }
    }

     $('#category_id').on('change', function () {
         parent_category_id = $(this).val();
        let subCategoriesSelect = $('#sub-categories');
            subCategoriesSelect.empty();
            subCategoriesSelect.append('<option value="" selected>{{ translate("messages.select_sub_category") }}</option>');
     });

     $('.foodModalClose').on('click',function (){
         $('#food-modal').hide();
     })

     $('.foodModalShow').on('click',function (){
         $('#food-modal').show();
     })

     $('.attributeModalClose').on('click',function (){
         $('#attribute-modal').hide();
     })

     $('.attributeModalShow').on('click',function (){
         $('#attribute-modal').show();
     })

    $(document).on('ready', function() {
        $('.js-select2-custom').each(function() {
            let select2 = $.HSCore.components.HSSelect2.init($(this));
        });
    });

    $('#condition_id').select2({
            ajax: {
                url: '{{ url('/') }}/admin/common-condition/get-all',
                data: function(params) {
                    return {
                        q: params.term, // search term
                        page: params.page,
                    };
                },
                processResults: function(data) {
                    return {
                        results: data
                    };
                },
                __port: function(params, success, failure) {
                    let $request = $.ajax(params);

                    $request.then(success);
                    $request.fail(failure);

                    return $request;
                }
            }
        });

    $('#brand_id').select2({
            ajax: {
                url: '{{ url('/') }}/admin/brand/get-all',
                data: function(params) {
                    return {
                        q: params.term, // search term
                        page: params.page,
                    };
                },
                processResults: function(data) {
                    return {
                        results: data
                    };
                },
                __port: function(params, success, failure) {
                    let $request = $.ajax(params);

                    $request.then(success);
                    $request.fail(failure);

                    return $request;
                }
            }
        });

    $('#store_id').select2({
        ajax: {
            url: '{{ url('/') }}/admin/store/get-stores',
            data: function(params) {
                return {
                    q: params.term, // search term
                    page: params.page,
                    module_id: module_id
                };
            },
            processResults: function(data) {
                return {
                    results: data
                };
            },
            __port: function(params, success, failure) {
                let $request = $.ajax(params);

                $request.then(success);
                $request.fail(failure);

                return $request;
            }
        }
    });

    $('#category_id').select2({
        ajax: {
            url: '{{ url('/') }}/admin/item/get-categories?parent_id=0',
            data: function(params) {
                return {
                    q: params.term, // search term
                    page: params.page,
                    module_id: module_id
                };
            },
            processResults: function(data) {
                return {
                    results: data
                };
            },
            __port: function(params, success, failure) {
                let $request = $.ajax(params);

                $request.then(success);
                $request.fail(failure);

                return $request;
            }
        }
    });

    $('#sub-categories').select2({
        ajax: {
            url: '{{ url('/') }}/admin/item/get-categories',
            data: function(params) {
                return {
                    q: params.term, // search term
                    page: params.page,
                    module_id: module_id,
                    parent_id: parent_category_id,
                    sub_category: true
                };
            },
            processResults: function(data) {
                return {
                    results: data
                };
            },
            __port: function(params, success, failure) {
                let $request = $.ajax(params);

                $request.then(success);
                $request.fail(failure);

                return $request;
            }
        }
    });

    $('#choice_attributes').on('change', function() {
        $('#customer_choice_options').html(null);
        combination_update();
        $.each($("#choice_attributes option:selected"), function() {
            add_more_customer_choice_option($(this).val(), $(this).text());
        });
    });

    function add_more_customer_choice_option(i, name) {
        let n = name;

        $('#customer_choice_options').append(
            `<div class="__choos-item"><div><input type="hidden" name="choice_no[]" value="${i}"><input type="text" class="form-control d-none" name="choice[]" value="${n}" placeholder="{{ translate('messages.choice_title') }}" readonly> <label class="form-label">${n}</label> </div><div><input type="text" class="form-control combination_update" name="choice_options_${i}[]" placeholder="{{ translate('messages.enter_choice_values') }}" data-role="tagsinput"></div></div>`
        );
        $("input[data-role=tagsinput], select[multiple][data-role=tagsinput]").tagsinput();
    }

    setTimeout(function() {
        $('.call-update-sku').on('change', function() {
            combination_update();
        });
    }, 2000)

    $('#colors-selector').on('change', function() {
        combination_update();
    });

    $('input[name="unit_price"]').on('keyup', function() {
        combination_update();
    });

    function combination_update() {
        $.ajaxSetup({
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            }
        });

        $.ajax({
            type: "POST",
            url: "{{ route('admin.item.variant-combination') }}",
            data: $('#product_form').serialize() + '&stock=' + stock,
            beforeSend: function() {
                $('#loading').show();
            },
            success: function(data) {
                $('#loading').hide();
                $('#variant_combination').html(data.view);
                if (data.length < 1) {
                    $('input[name="current_stock"]').attr("readonly", false);
                }
            }
        });
    }

     $(document).on('change', '.combination_update', function () {
         combination_update();
     });
     // $('#product_form').on('keydown', function(e) {
     //        if (e.key === 'Enter') {
     //        e.preventDefault(); // Prevent submission on Enter
     //        }
     //    });

    $('#product_form').on('submit', function() {
        console.log('working');
        let formData = new FormData(this);
        $.ajaxSetup({
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            }
        });
        $.post({
            url: $('.route_url').val() ,
            data: $('#product_form').serialize(),
            data: formData,
            cache: false,
            contentType: false,
            processData: false,
            beforeSend: function() {
                $('#loading').show();
            },
            success: function(data) {
                console.log(data);
                $('#loading').hide();
                if (data.errors) {
                    for (let i = 0; i < data.errors.length; i++) {
                        toastr.error(data.errors[i].message, {
                            CloseButton: true,
                            ProgressBar: true
                        });
                    }
                }
                if(data.product_approval){
                        toastr.success(data.product_approval, {
                        CloseButton: true,
                        ProgressBar: true
                    });
                }
                if(data.success) {
                    toastr.success(data.success, {
                        CloseButton: true,
                        ProgressBar: true
                    });
                    setTimeout(function() {
                        location.href =
                            '{{ route('admin.item.list') }}';
                    }, 2000);
                }
            }
        });
    });

    $('#reset_btn').click(function() {
        location.reload(true);
    })

    update_qty();

    function update_qty() {
        let total_qty = 0;
        let qty_elements = $('input[name^="stock_"]');
        for (let i = 0; i < qty_elements.length; i++) {
            total_qty += parseInt(qty_elements.eq(i).val());
        }
        if (qty_elements.length > 0) {

            $('input[name="current_stock"]').attr("readonly", true);
            $('input[name="current_stock"]').val(total_qty);
        } else {
            $('input[name="current_stock"]').attr("readonly", false);
        }
    }
    $('input[name^="stock_"]').on('keyup', function() {
        let total_qty = 0;
        let qty_elements = $('input[name^="stock_"]');
        for (let i = 0; i < qty_elements.length; i++) {
            total_qty += parseInt(qty_elements.eq(i).val());
        }
        $('input[name="current_stock"]').val(total_qty);
    });

    $(function() {
        $("#coba").spartanMultiImagePicker({
            fieldName: 'item_images[]',
            maxCount: 6,
            // rowHeight: '100px !important',
            groupClassName: 'spartan_item_wrapper min-w-176px max-w-176px',
            maxFileSize: "{{ config('media.max_image_size_kb') }}",
            placeholderImage: {
                image: "{{ asset('public/assets/admin/img/upload-img.png') }}",
                width: '176px'
            },
            dropFileLabel: "Drop Here",
            onAddRow: function(index, file) {

            },
            onRenderedPreview: function(index) {

            },
            onRemoveRow: function(index) {

            },
            onExtensionErr: function(index, file) {
                toastr.error(
                    "{{ translate('messages.please_only_input_png_or_jpg_type_file') }}", {
                        CloseButton: true,
                        ProgressBar: true
                    });
            },
            onSizeErr: function(index, file) {
                toastr.error("{{ translate('messages.file_size_too_big') }}", {
                    CloseButton: true,
                    ProgressBar: true
                });
            }
        });
    });

    $('#reset_btn').click(function() {
        $('#module_id').val(null).trigger('change');
        $('#store_id').val(null).trigger('change');
        $('#category_id').val(null).trigger('change');
        $('#sub-categories').val(null).trigger('change');
        $('#unit').val(null).trigger('change');
        $('#veg').val(0).trigger('change');
        $('#add_on').val(null).trigger('change');
        $('#discount_type').val(null).trigger('change');
        $('#choice_attributes').val(null).trigger('change');
        $('#customer_choice_options').empty().trigger('change');
        $('#variant_combination').empty().trigger('change');
        $('#viewer').attr('src', "{{ asset('public/assets/admin/img/upload.png') }}");
        $("#coba").empty().spartanMultiImagePicker({
            fieldName: 'item_images[]',
            maxCount: 6,
            rowHeight: '176px !important',
            groupClassName: 'spartan_item_wrapper min-w-176px max-w-176px',
            maxFileSize: "{{ config('media.max_image_size_kb') }}",
            placeholderImage: {
                image: "{{ asset('public/assets/admin/img/upload-img.png') }}",
                width: '100%'
            },
            dropFileLabel: "Drop Here",
            onAddRow: function(index, file) {

            },
            onRenderedPreview: function(index) {

            },
            onRemoveRow: function(index) {

            },
            onExtensionErr: function(index, file) {
                toastr.error(
                    "{{ translate('messages.please_only_input_png_or_jpg_type_file') }}", {
                        CloseButton: true,
                        ProgressBar: true
                    });
            },
            onSizeErr: function(index, file) {
                toastr.error("{{ translate('messages.file_size_too_big') }}", {
                    CloseButton: true,
                    ProgressBar: true
                });
            }
        });
    })



</script>
<script>
    document.getElementById('customVideoFile').addEventListener('change', function (event) {
        const videoFile = event.target.files[0];
        const videoPreview = document.getElementById('videoPreview');
        const placeholder = document.getElementById('videoPlaceholder');

        if (videoFile) {
            const url = URL.createObjectURL(videoFile);
            videoPreview.src = url;
            videoPreview.style.display = 'block';
            placeholder.style.display = 'none';
        }
    });
</script>
@endpush
