<?php

namespace App\Http\Controllers\Admin;

use App\Models\EcommerceItemDetails;
use App\Models\PharmacyItemDetails;
use App\Models\PriorityList;
use Carbon\Carbon;
use App\Models\Item;
use App\Models\Store;
use App\Models\Setting;
use App\Models\Currency;
use App\Traits\Processor;
use App\Models\DataSetting;
use App\Models\TempProduct;
use App\Models\Translation;
use App\Models\AdminFeature;
use App\Models\RefundReason;
use Illuminate\Http\Request;
use App\Models\EmailTemplate;
use App\CentralLogics\Helpers;
use App\Models\BusinessSetting;
use App\Models\AdminTestimonial;
use App\Models\ReactTestimonial;
use App\Models\OrderCancelReason;
use App\Models\StoreSubscription;
use Illuminate\Support\Facades\DB;
use App\Models\NotificationMessage;
use App\Models\NotificationSetting;
use App\Http\Controllers\Controller;
use App\Models\AdminSpecialCriteria;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use App\Models\AdminPromotionalBanner;
use App\Models\AutomatedMessage;
use App\Models\FlutterSpecialCriteria;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Storage;
use App\Models\Category;
use Illuminate\Support\Facades\Validator;

class BusinessSettingsController extends Controller
{
    use Processor;
    
    public function business_index(Request $request, $tab = 'business')
    {
        if (!Helpers::module_permission_check('settings')) {
            Toastr::error(translate('messages.access_denied'));
            return back();
        }
        $type = $request->type;
        if ($tab == 'business') {
            return view('admin-views.business-settings.business-index');
        } else if ($tab == 'customer') {
            $data = BusinessSetting::where('key', 'like', 'wallet_%')
            ->orWhere('key', 'like', 'loyalty_%')
            ->orWhere('key', 'like', 'ref_earning_%')
            ->orWhere('key', 'like', 'add_fund_status%')
            ->orWhere('key', 'like', 'customer_%')
            ->orWhere('key', 'like', 'new_customer_discount_%')
            ->orWhere('key', 'like', 'ref_earning_%')->get();
            $data = array_column($data->toArray(), 'value', 'key');
            return view('admin-views.business-settings.customer-index', compact('data'));
        } else if ($tab == 'deliveryman') {
            return view('admin-views.business-settings.deliveryman-index');
        } else if ($tab == 'order') {
            $reasons = OrderCancelReason::when($request->type && ($request->type != 'all'), function ($query) use ($request) {
                $query->where('user_type', $request->type);
            })->latest()->paginate(config('default_pagination'));
            return view('admin-views.business-settings.order-index', compact('reasons', 'type'));
        } else if ($tab == 'store') {
            return view('admin-views.business-settings.store-index');
        } else if ($tab == 'refund-settings') {
            $refund_active_status = BusinessSetting::where(['key' => 'refund_active_status'])->first();
            $reasons = RefundReason::orderBy('id', 'desc')
            ->paginate(config('default_pagination'));
            return view('admin-views.business-settings.refund-index', compact('refund_active_status', 'reasons'));
        } else if ($tab == 'landing-page') {
            $landing = BusinessSetting::where('key', 'landing_page')->exists();
            if (!$landing) {
                Helpers::insert_business_settings_key('landing_page', '1');
                Helpers::insert_business_settings_key('landing_integration_type', 'none');
            }
            return view('admin-views.business-settings.landing-index');
        } else if ($tab == 'websocket') {
            return view('admin-views.business-settings.websocket-index');
        } else if ($tab == 'disbursement') {
            return view('admin-views.business-settings.disbursement-index');
        } else if ($tab == 'priority') {
            $categories=Category::where('parent_id',0)->get();
            $trending_product_ids_Record = BusinessSetting::where(['key' => 'trending_product_ids'])->first();
            if(!empty( $trending_product_ids_Record)){
            $trending_product_ids = json_decode($trending_product_ids_Record->value, true);
            }else{
                $trending_product_ids =[];
            }
             $suggestedRecord = BusinessSetting::where(['key' => 'suggested_products'])->first();
            if(!empty( $suggestedRecord)){
            $suggested_products_ids = json_decode($suggestedRecord->value, true);
            }else{
                $suggested_products_ids =[];
            }
            $selectedProducts = \App\Models\Item::whereIn('id', $trending_product_ids)->get();
             $suggestedProducts = \App\Models\Item::whereIn('id', $suggested_products_ids)->get();
            return view('admin-views.business-settings.priority-index',compact('categories','selectedProducts','suggestedProducts'));
        } else if ($tab == 'automated-message') {
            $key = explode(' ', $request['search']);
            $messages = AutomatedMessage::orderBy('id', 'desc')
            ->when($request?->search ,function($query)use($key) {
                foreach ($key as $value) {
                    $query->where('message' ,'like', "%{$value}%");
                };
            })
            ->paginate(config('default_pagination'));
            $language = getWebConfig('language');
            
            return view('admin-views.business-settings.automated_message', compact( 'messages','language'));
        }
    }
    
    public function update_priority(Request $request)
    {
        $list = ['category_list', 'popular_store', 'recommended_store', 'special_offer', 'popular_item', 'best_reviewed_item', 'item_campaign', 'latest_items', 'all_stores', 'category_sub_category_item', 'product_search', 'basic_medicine', 'common_condition', 'brand', 'brand_item', 'latest_stores','top_offer_near_me_stores'];
        foreach ($list as $item) {
            Helpers::businessUpdateOrInsert(['key' => $item . '_default_status'], [
                'value' => $request[$item . '_default_status'] ?? 0
            ]);
            
            if ($request[$item . '_default_status'] == '0') {
                
                if (!$request[$item . '_sort_by_general'] && $item != 'search_bar') {
                    Toastr::error(translate('you_must_selcet_an_option_for') . ' ' . translate($item));
                    return back();
                }
                
                if ($request[$item . '_sort_by_general']) {
                    PriorityList::query()->updateOrInsert(['name' => $item . '_sort_by_general', 'type' => 'general'], [
                        'value' => $request[$item . '_sort_by_general']
                    ]);
                }
                if ($request[$item . '_sort_by_unavailable']) {
                    PriorityList::query()->updateOrInsert(['name' => $item . '_sort_by_unavailable', 'type' => 'unavailable'], [
                        'value' => $request[$item . '_sort_by_unavailable']
                    ]);
                }
                if ($request[$item . '_sort_by_temp_closed']) {
                    PriorityList::query()->updateOrInsert(['name' => $item . '_sort_by_temp_closed', 'type' => 'temp_closed'], [
                        'value' => $request[$item . '_sort_by_temp_closed']
                    ]);
                }
                if ($request[$item . '_sort_by_rating']) {
                    PriorityList::query()->updateOrInsert(['name' => $item . '_sort_by_rating', 'type' => 'rating'], [
                        'value' => $request[$item . '_sort_by_rating']
                    ]);
                }
            }
        }
        $trendingIds = $request->input('trending_product_ids', []);
        
        Helpers::businessUpdateOrInsert(['key' => 'trending_product_ids'], [
            'value' => json_encode($trendingIds)
        ]);
         $suggestedIds = $request->input('suggested_product_ids', []);
        
        Helpers::businessUpdateOrInsert(['key' => 'suggested_products'], [
            'value' => json_encode($suggestedIds)
        ]);
        
        
        Toastr::success(translate('messages.successfully_updated_to_changes_restart_app'));
        return back();
    }
    
    public function update_dm(Request $request)
    {
        if (env('APP_MODE') == 'demo') {
            Toastr::info(translate('messages.update_option_is_disable_for_demo'));
            return back();
        }
        
        Helpers::businessUpdateOrInsert(['key' => 'min_amount_to_pay_dm'], [
            'value' => $request['min_amount_to_pay_dm']
        ]);
        Helpers::businessUpdateOrInsert(['key' => 'cash_in_hand_overflow_delivery_man'], [
            'value' => $request['cash_in_hand_overflow_delivery_man'] ?? 0
        ]);
        Helpers::businessUpdateOrInsert(['key' => 'dm_max_cash_in_hand'], [
            'value' => $request['dm_max_cash_in_hand']
        ]);
        
        Helpers::businessUpdateOrInsert(['key' => 'dm_tips_status'], [
            'value' => $request['dm_tips_status']
        ]);
        
        Helpers::businessUpdateOrInsert(['key' => 'dm_maximum_orders'], [
            'value' => $request['dm_maximum_orders']
        ]);
        
        Helpers::businessUpdateOrInsert(['key' => 'canceled_by_deliveryman'], [
            'value' => $request['canceled_by_deliveryman']
        ]);
        
        Helpers::businessUpdateOrInsert(['key' => 'show_dm_earning'], [
            'value' => $request['show_dm_earning']
        ]);
        
        Helpers::businessUpdateOrInsert(['key' => 'toggle_dm_registration'], [
            'value' => $request['dm_self_registration']
        ]);
        
        Helpers::businessUpdateOrInsert(['key' => 'dm_picture_upload_status'], [
            'value' => $request['dm_picture_upload_status']
        ]);
        
        Toastr::success(translate('messages.successfully_updated_to_changes_restart_app'));
        return back();
    }
    
    public function update_websocket(Request $request)
    {
        if (env('APP_MODE') == 'demo') {
            Toastr::info(translate('messages.update_option_is_disable_for_demo'));
            return back();
        }
        Helpers::businessUpdateOrInsert(['key' => 'websocket_status'], [
            'value' => $request['websocket_status']
        ]);
        Helpers::businessUpdateOrInsert(['key' => 'websocket_url'], [
            'value' => $request['websocket_url']
        ]);
        
        Helpers::businessUpdateOrInsert(['key' => 'websocket_port'], [
            'value' => $request['websocket_port']
        ]);
        
        Toastr::success(translate('messages.successfully_updated_to_changes_restart_app'));
        return back();
    }
    
    public function update_store(Request $request)
    {
        
        if ($request['product_approval'] == null) {
            $this->product_approval_all();
        }
        if ($request['product_approval'] == 1) {
            if (!($request->Update_product_price || $request->Add_new_product || $request->Update_product_variation || $request->Update_anything_in_product_details)) {
                Helpers::businessUpdateOrInsert(['key' => 'product_approval'], [
                    'value' => 0
                ]);
                Toastr::error(translate('messages.need_to_check_minimum_1_criteria_for_product_approval'));
                return back();
            }
        }
        Helpers::businessUpdateOrInsert(['key' => 'cash_in_hand_overflow_store'], [
            'value' => $request['cash_in_hand_overflow_store'] ?? 0
        ]);
        Helpers::businessUpdateOrInsert(['key' => 'cash_in_hand_overflow_store_amount'], [
            'value' => $request['cash_in_hand_overflow_store_amount']
        ]);
        Helpers::businessUpdateOrInsert(['key' => 'min_amount_to_pay_store'], [
            'value' => $request['min_amount_to_pay_store']
        ]);
        
        Helpers::businessUpdateOrInsert(['key' => 'store_review_reply'], [
            'value' => $request['store_review_reply']
        ]);
        
        Helpers::businessUpdateOrInsert(['key' => 'canceled_by_store'], [
            'value' => $request['canceled_by_store']
        ]);
        
        Helpers::businessUpdateOrInsert(['key' => 'toggle_store_registration'], [
            'value' => $request['store_self_registration']
        ]);
        Helpers::businessUpdateOrInsert(['key' => 'product_approval'], [
            'value' => $request['product_approval']
        ]);
        
        $values = [
            'Update_product_price' => $request->Update_product_price ?? 0,
            'Add_new_product' => $request->Add_new_product ?? 0,
            'Update_product_variation' => $request->Update_product_variation ?? 0,
            'Update_anything_in_product_details' => $request->Update_anything_in_product_details ?? 0,
        ];
        
        Helpers::businessUpdateOrInsert(['key' => 'product_approval_datas'], [
            'value' => json_encode($values)
        ]);
        
        
        Helpers::businessUpdateOrInsert(['key' => 'access_all_products'], [
            'value' => $request['access_all_products']
        ]);
        Helpers::businessUpdateOrInsert(['key' => 'product_gallery'], [
            'value' => $request['product_gallery']
        ]);
        
        
        Toastr::success(translate('messages.successfully_updated_to_changes_restart_app'));
        return back();
    }
    
    public function update_order(Request $request)
    {
        $request->validate([
            'home_delivery_status' => 'required_without:takeaway_status',
            'takeaway_status' => 'required_without:home_delivery_status',
        ]);
        $key_datas=[
            'order_cancelation_rate_limit_status' => 'order_cancelation_rate_limit_status',
            'order_cancelation_rate_block_limit' => 'order_cancelation_rate_block_limit',
            'order_cancelation_rate_warning_limit' => 'order_cancelation_rate_warning_limit',
            'order_delivery_verification' => 'odc',
            'schedule_order' => 'schedule_order',
            'prescription_order_status' => 'prescription_order_status',
            'home_delivery_status' => 'home_delivery_status',
            'takeaway_status' => 'takeaway_status',
            'schedule_order_slot_duration_time_format' => 'schedule_order_slot_duration_time_format',
            'takeaway_status' => 'takeaway_status',
        ];
        
        if($request->order_cancelation_rate_limit_status &&  $request->order_cancelation_rate_warning_limit >  $request->order_cancelation_rate_block_limit ){
            Toastr::error(translate('messages.Providers_will_be_blocked_with_out_warning.Warning_rate_must_be_smaller'));
            return back();
        }
        foreach($key_datas as $key => $request_key){
            Helpers::businessUpdateOrInsert(['key' => $key], [
                'value' => $request->{$request_key} ?? 0
            ]);
        }
        
        $time = $request['schedule_order_slot_duration'];
        if ($request['schedule_order_slot_duration_time_format'] == 'hour') {
            $time = $request['schedule_order_slot_duration'] * 60;
        }
        Helpers::businessUpdateOrInsert(['key' => 'schedule_order_slot_duration'], [
            'value' => $time
        ]);
        $values = [];
        foreach (config('module.module_type') as $key => $value) {
            $values[$value] = $request[$value] ?? 0;
        }
        
        Helpers::businessUpdateOrInsert(['key' => 'extra_packaging_data'], [
            'value' => json_encode($values)
        ]);
        
        Toastr::success(translate('messages.successfully_updated_to_changes_restart_app'));
        return back();
    }
    
    
    public function update_disbursement(Request $request)
    {
        if (env('APP_MODE') == 'demo') {
            Toastr::info(translate('messages.update_option_is_disable_for_demo'));
            return back();
        }
        
        Helpers::businessUpdateOrInsert(['key' => 'disbursement_type'], [
            'value' => $request['disbursement_type']
        ]);
        
        Helpers::businessUpdateOrInsert(['key' => 'store_disbursement_time_period'], [
            'value' => $request['store_disbursement_time_period']
        ]);
        
        Helpers::businessUpdateOrInsert(['key' => 'store_disbursement_week_start'], [
            'value' => $request['store_disbursement_week_start']
        ]);
        
        Helpers::businessUpdateOrInsert(['key' => 'store_disbursement_waiting_time'], [
            'value' => $request['store_disbursement_waiting_time']
        ]);
        
        Helpers::businessUpdateOrInsert(['key' => 'store_disbursement_create_time'], [
            'value' => $request['store_disbursement_create_time']
        ]);
        
        Helpers::businessUpdateOrInsert(['key' => 'store_disbursement_min_amount'], [
            'value' => $request['store_disbursement_min_amount']
        ]);
        
        Helpers::businessUpdateOrInsert(['key' => 'dm_disbursement_time_period'], [
            'value' => $request['dm_disbursement_time_period']
        ]);
        Helpers::businessUpdateOrInsert(['key' => 'dm_disbursement_week_start'], [
            'value' => $request['dm_disbursement_week_start']
        ]);
        Helpers::businessUpdateOrInsert(['key' => 'dm_disbursement_waiting_time'], [
            'value' => $request['dm_disbursement_waiting_time']
        ]);
        Helpers::businessUpdateOrInsert(['key' => 'dm_disbursement_create_time'], [
            'value' => $request['dm_disbursement_create_time']
        ]);
        Helpers::businessUpdateOrInsert(['key' => 'dm_disbursement_min_amount'], [
            'value' => $request['dm_disbursement_min_amount']
        ]);
        Helpers::businessUpdateOrInsert(['key' => 'system_php_path'], [
            'value' => $request['system_php_path']
        ]);
        
        if (function_exists('exec')) {
            $data = self::generateCronCommand(disbursement_type: $request['disbursement_type']);
            $scriptPath = 'script.sh';
            exec('sh ' . $scriptPath);
            Helpers::businessUpdateOrInsert(['key' => 'store_disbursement_command'], [
                'value' => $data['storeCronCommand']
            ]);
            Helpers::businessUpdateOrInsert(['key' => 'dm_disbursement_command'], [
                'value' => $data['dmCronCommand']
            ]);
            Toastr::success(translate('messages.successfully_updated_disbursement_functionality'));
            return back();
        } else {
            $data = self::generateCronCommand(disbursement_type: $request['disbursement_type']);
            Helpers::businessUpdateOrInsert(['key' => 'store_disbursement_command'], [
                'value' => $data['storeCronCommand']
            ]);
            Helpers::businessUpdateOrInsert(['key' => 'dm_disbursement_command'], [
                'value' => $data['dmCronCommand']
            ]);
            if ($request['disbursement_type'] == 'automated') {
                Session::flash('disbursement_exec', true);
                Toastr::warning(translate('messages.Servers_PHP_exec_function_is_disabled_check_dependencies_&_start_cron_job_manualy_in_server'));
            }
            Toastr::success(translate('messages.successfully_updated_disbursement_functionality'));
            return back();
        }
        
    }
    
    private function dmSchedule()
    {
        $key = [
            'dm_disbursement_time_period', 'dm_disbursement_week_start', 'dm_disbursement_create_time'
        ];
        $settings = array_column(BusinessSetting::whereIn('key', $key)->get()->toArray(), 'value', 'key');
        
        $scheduleFrequency = $settings['dm_disbursement_time_period'] ?? 'daily';
        $weekDay = $settings['dm_disbursement_week_start'] ?? 'sunday';
        $time = $settings['dm_disbursement_create_time'] ?? '12:00';
        
        
        $time = explode(":", $time);
        
        $hour = $time[0];
        $min = $time[1];
        
        $days = ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'];
        $day = array_search($weekDay, $days);
        $schedule = "* * * * *";
        if ($scheduleFrequency == 'daily') {
            $schedule = $min . " " . $hour . " " . "* * *";
            
        } elseif ($scheduleFrequency == 'weekly') {
            
            $schedule = $min . " " . $hour . " " . "* * " . $day;
        } elseif ($scheduleFrequency == 'monthly') {
            $schedule = $min . " " . $hour . " " . "28-31 * *";
            
        }
        return $schedule;
    }
    
    private function storeSchedule()
    {
        $key = [
            'store_disbursement_time_period', 'store_disbursement_week_start', 'store_disbursement_create_time'
        ];
        $settings = array_column(BusinessSetting::whereIn('key', $key)->get()->toArray(), 'value', 'key');
        
        $scheduleFrequency = $settings['store_disbursement_time_period'] ?? 'daily';
        $weekDay = $settings['store_disbursement_week_start'] ?? 'sunday';
        $time = $settings['store_disbursement_create_time'] ?? '12:00';
        
        
        $time = explode(":", $time);
        
        $hour = $time[0];
        $min = $time[1];
        
        $days = ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'];
        $day = array_search($weekDay, $days);
        $schedule = "* * * * *";
        if ($scheduleFrequency == 'daily') {
            $schedule = $min . " " . $hour . " " . "* * *";
            
        } elseif ($scheduleFrequency == 'weekly') {
            
            $schedule = $min . " " . $hour . " " . "* * " . $day;
        } elseif ($scheduleFrequency == 'monthly') {
            $schedule = $min . " " . $hour . " " . "28-31 * *";
            
        }
        return $schedule;
    }
    
    private function generateCronCommand($disbursement_type = 'automated')
    {
        $system_php_path = BusinessSetting::where('key', 'system_php_path')->first();
        $system_php_path = $system_php_path ? $system_php_path->value : "/usr/bin/php";
        $dmSchedule = self::dmSchedule();
        $storeSchedule = self::storeSchedule();
        $scriptFilename = $_SERVER['SCRIPT_FILENAME'];
        $rootPath = dirname($scriptFilename);
        $phpCommand = $system_php_path;
        $dmScriptPath = $rootPath . "/artisan dm:disbursement";
        $storeScriptPath = $rootPath . "/artisan store:disbursement";
        $dmClearCronCommand = "(crontab -l | grep -v \"$phpCommand $dmScriptPath\") | crontab -";
        $dmCronCommand = $disbursement_type == 'automated' ? "(crontab -l ; echo \"$dmSchedule $phpCommand $dmScriptPath\") | crontab -" : "";
        $storeClearCronCommand = "(crontab -l | grep -v \"$phpCommand $storeScriptPath\") | crontab -";
        $storeCronCommand = $disbursement_type == 'automated' ? "(crontab -l ; echo \"$storeSchedule $phpCommand $storeScriptPath\") | crontab -" : "";
        $scriptContent = "#!/bin/bash\n";
        $scriptContent .= $dmClearCronCommand . "\n";
        $scriptContent .= $dmCronCommand . "\n";
        $scriptContent .= $storeClearCronCommand . "\n";
        $scriptContent .= $storeCronCommand . "\n";
        $scriptFilePath = $rootPath . "/script.sh";
        file_put_contents($scriptFilePath, $scriptContent);
        
        return [
            'dmCronCommand' => $dmCronCommand,
            'storeCronCommand' => $storeCronCommand
        ];
    }
    
    public function business_setup(Request $request)
    {
        
        if (env('APP_MODE') == 'demo') {
            Toastr::info(translate('messages.update_option_is_disable_for_demo'));
            return back();
        }
        
        Helpers::businessUpdateOrInsert(['key' => 'country_picker_status'], [
            'value' => $request['country_picker_status'] ? $request['country_picker_status'] : 0
        ]);
        
        Helpers::businessUpdateOrInsert(['key' => 'business_name'], [
            'value' => $request['store_name']
        ]);
        
        Helpers::businessUpdateOrInsert(['key' => 'currency'], [
            'value' => $request['currency']
        ]);
        
        Helpers::businessUpdateOrInsert(['key' => 'timezone'], [
            'value' => $request['timezone']
        ]);
        
        $curr_logo = BusinessSetting::firstOrNew(['key' => 'logo']);
        if ($request->has('logo')) {
            $image_name = Helpers::update('business/', $curr_logo->value, 'png', $request->file('logo'));
        } else {
            $image_name = $curr_logo['value'];
        }
        $curr_logo->value = $image_name;
        $curr_logo->save();
        
        $fav_icon = BusinessSetting::firstOrNew(['key' => 'icon']);
        if ($request->has('icon')) {
            $image_name = Helpers::update('business/', $fav_icon->save, 'png', $request->file('icon'));
        } else {
            $image_name = $fav_icon['value'];
        }
        $fav_icon->value = $image_name;
        $fav_icon->save();
        
        Config::set('currency', $request['currency']);
        Config::set('currency_symbol_position', $request['currency_symbol_position']);
        
        Helpers::businessUpdateOrInsert(['key' => 'site_direction'], [
            'value' => $request['site_direction']
        ]);
        
        Helpers::businessUpdateOrInsert(['key' => 'phone'], [
            'value' => $request['phone']
        ]);
        
        Helpers::businessUpdateOrInsert(['key' => 'email_address'], [
            'value' => $request['email']
        ]);
        
        Helpers::businessUpdateOrInsert(['key' => 'address'], [
            'value' => $request['address']
        ]);
        
        Helpers::businessUpdateOrInsert(['key' => 'footer_text'], [
            'value' => $request['footer_text']
        ]);
        
        Helpers::businessUpdateOrInsert(['key' => 'cookies_text'], [
            'value' => $request['cookies_text']
        ]);
        
        Helpers::businessUpdateOrInsert(['key' => 'minimum_shipping_charge'], [
            'value' => $request['minimum_shipping_charge']
        ]);
        Helpers::businessUpdateOrInsert(['key' => 'per_km_shipping_charge'], [
            'value' => $request['per_km_shipping_charge']
        ]);
          Helpers::businessUpdateOrInsert(['key' => 'delivery_tat'], [
            'value' => $request['delivery_tat']
        ]);
        Helpers::businessUpdateOrInsert(['key' => 'currency_symbol_position'], [
            'value' => $request['currency_symbol_position']
        ]);
        
        Helpers::businessUpdateOrInsert(['key' => 'tax_included'], [
            'value' => $request['tax_included']
        ]);
        Helpers::businessUpdateOrInsert(['key' => 'order_confirmation_model'], [
            'value' => $request['order_confirmation_model']
        ]);
        Helpers::businessUpdateOrInsert(['key' => 'partial_payment_status'], [
            'value' => $request['partial_payment_status']
        ]);
        Helpers::businessUpdateOrInsert(['key' => 'partial_payment_method'], [
            'value' => $request['partial_payment_method']
        ]);
        
        Helpers::businessUpdateOrInsert(['key' => 'tax'], [
            'value' => $request['tax']
        ]);
        
        Helpers::businessUpdateOrInsert(['key' => 'admin_commission'], [
            'value' => $request['admin_commission']
        ]);
        
        Helpers::businessUpdateOrInsert(['key' => 'country'], [
            'value' => $request['country']
        ]);
        
        Helpers::businessUpdateOrInsert(['key' => 'default_location'], [
            'value' => json_encode(['lat' => $request['latitude'], 'lng' => $request['longitude']])
        ]);
        
        Helpers::businessUpdateOrInsert(['key' => 'admin_order_notification'], [
            'value' => $request['admin_order_notification']
        ]);
        
        Helpers::businessUpdateOrInsert(['key' => 'order_notification_type'], [
            'value' => $request['order_notification_type']
        ]);
        
        Helpers::businessUpdateOrInsert(['key' => 'admin_free_delivery_status'], [
            'value' => $request['admin_free_delivery_status'] ? $request['admin_free_delivery_status'] : null
        ]);
        
        Helpers::businessUpdateOrInsert(['key' => 'free_delivery_over'], [
            'value' => $request['admin_free_delivery_status'] && $request['admin_free_delivery_option'] == 'free_delivery_by_order_amount' ? $request['free_delivery_over'] : null
        ]);
        Helpers::businessUpdateOrInsert(['key' => 'admin_free_delivery_option'], [
            'value' => $request['admin_free_delivery_status'] && $request['admin_free_delivery_option'] ?  $request['admin_free_delivery_option'] : null
        ]);
        
        Helpers::businessUpdateOrInsert(['key' => 'additional_charge_status'], [
            'value' => $request['additional_charge_status'] ? $request['additional_charge_status'] : null
        ]);
        
        Helpers::businessUpdateOrInsert(['key' => 'additional_charge_name'], [
            'value' => $request['additional_charge_name'] ? $request['additional_charge_name'] : null
        ]);
        
        Helpers::businessUpdateOrInsert(['key' => 'additional_charge'], [
            'value' => $request['additional_charge'] ? $request['additional_charge'] : null
        ]);
        
        Helpers::businessUpdateOrInsert(['key' => 'guest_checkout_status'], [
            'value' => $request['guest_checkout_status'] ? $request['guest_checkout_status'] : 0
        ]);
        
        Helpers::businessUpdateOrInsert(['key' => 'timeformat'], [
            'value' => $request['time_format']
        ]);
        
        
        Helpers::businessUpdateOrInsert(['key' => 'toggle_veg_non_veg'], [
            'value' => $request['vnv']
        ]);
        
        
        Helpers::businessUpdateOrInsert(['key' => 'digit_after_decimal_point'], [
            'value' => $request['digit_after_decimal_point']
        ]);
        
        Helpers::businessUpdateOrInsert(['key' => 'delivery_charge_comission'], [
            'value' => $request['admin_comission_in_delivery_charge']
        ]);
        // dd( $request['commission_business_model']);
        
        if (!isset($request->subscription_business_model) && !isset($request->commission_business_model)) {
            Toastr::error(translate('You_must_select_at_least_one_business_model_between_commission_and_subscription'));
            return back();
        }
        
        // For subscription Model
        if (isset($request->subscription_business_model) && !isset($request->commission_business_model)) {
            Helpers::businessUpdateOrInsert(['key' => 'subscription_business_model'], [
                'value' => $request['subscription_business_model'] ?? 1
            ]);
            
            Helpers::businessUpdateOrInsert(['key' => 'commission_business_model'], [
                'value' => $request['commission_business_model'] ?? 0
            ]);
            
            if (Helpers::commission_check() == 0) {
                Store::where('store_business_model', 'commission')
                ->update(['store_business_model' => 'unsubscribed',
                'status' => 0,]);
            }
            
            
        } // For commission model
        elseif (isset($request->commission_business_model) && !isset($request->subscription_business_model)) {
            
            
            if (StoreSubscription::where('status', 1)->count() > 0) {
                Toastr::warning(translate('You_need_to_switch_your_subscribers_to_commission_first'));
                return back();
            }
            Helpers::businessUpdateOrInsert(['key' => 'commission_business_model'], [
                'value' => $request['commission_business_model'] ?? 1
            ]);
            Helpers::businessUpdateOrInsert(['key' => 'subscription_business_model'], [
                'value' => $request['subscription_business_model'] ?? 0
            ]);
            
            if (Helpers::subscription_check() == 0) {
                Store::query()->update(['store_business_model' => 'commission']);
            }
            
            
        } else {
            Helpers::businessUpdateOrInsert(['key' => 'commission_business_model'], [
                'value' => $request['commission_business_model'] ?? 1
            ]);
            if (!isset($request->subscription_business_model) && StoreSubscription::where('status', 1)->count() > 0) {
                Toastr::warning(translate('You_need_to_switch_your_subscribers_to_commission_first'));
                return back();
            }
            Helpers::businessUpdateOrInsert(['key' => 'subscription_business_model'], [
                'value' => $request['subscription_business_model'] ?? 1
            ]);
        }
        $activationMode = DB::table('external_configurations')->where('key', 'activation_mode')->first();
        $driveMondBaseUrl = DB::table('external_configurations')->where('key', 'drivemond_base_url')->first();
        if ($activationMode && $activationMode->value == 1 && $driveMondBaseUrl && $driveMondBaseUrl->value != null) {
            $name = \App\Models\BusinessSetting::where('key', 'business_name')->first();
            $logo = \App\Models\BusinessSetting::where('key', 'logo')->first();
            
            $app_minimum_version_android=BusinessSetting::where(['key'=>'app_minimum_version_android'])->first()?->value;
            $app_url_android=BusinessSetting::where(['key'=>'app_url_android'])->first()?->value;
            $app_minimum_version_ios=BusinessSetting::where(['key'=>'app_minimum_version_ios'])->first()?->value;
            $app_url_ios=BusinessSetting::where(['key'=>'app_url_ios'])->first()?->value;
            
            $response = Http::post($driveMondBaseUrl->value . '/api/store-configurations', [
                'mart_business_name' => $name->value ?? "6amMart",
                'mart_business_logo' => \App\CentralLogics\Helpers::get_full_url('business', $logo?->value ?? '', $logo?->storage[0]?->value ?? 'public', 'favicon') ?? asset('public/assets/admin/img/160x160/img2.jpg'),
                'mart_app_minimum_version_android' => $app_minimum_version_android,
                'mart_app_url_android' => $app_url_android,
                'mart_app_minimum_version_ios' => $app_minimum_version_ios,
                'mart_app_url_ios' => $app_url_ios,
                
            ]);
        }
        
        Toastr::success(translate('messages.successfully_updated_to_changes_restart_app'));
        return back();
    }
    
    public function mail_index()
    {
        return view('admin-views.business-settings.mail-index');
    }
    
    public function test_mail()
    {
        return view('admin-views.business-settings.send-mail-index');
    }
    
    public function mail_config(Request $request)
    {
        if (env('APP_MODE') == 'demo') {
            Toastr::info(translate('messages.update_option_is_disable_for_demo'));
            return back();
        }
        Helpers::businessUpdateOrInsert(
            ['key' => 'mail_config'],
            [
                'value' => json_encode([
                    "status" => $request['status'] ?? 0,
                    "name" => $request['name'],
                    "host" => $request['host'],
                    "driver" => $request['driver'],
                    "port" => $request['port'],
                    "username" => $request['username'],
                    "email_id" => $request['email'],
                    "encryption" => $request['encryption'],
                    "password" => $request['password']
                ]),
                'updated_at' => now()
                ]
            );
            Toastr::success(translate('messages.configuration_updated_successfully'));
            return back();
        }
        
        public function mail_config_status(Request $request)
        {
            if (env('APP_MODE') == 'demo') {
                Toastr::info(translate('messages.update_option_is_disable_for_demo'));
                return back();
            }
            $config = BusinessSetting::where(['key' => 'mail_config'])->first();
            
            $data = $config ? json_decode($config['value'], true) : null;
            
            Helpers::businessUpdateOrInsert(
                ['key' => 'mail_config'],
                [
                    'value' => json_encode([
                        "status" => $request['status'] ?? 0,
                        "name" => $data['name'] ?? '',
                        "host" => $data['host'] ?? '',
                        "driver" => $data['driver'] ?? '',
                        "port" => $data['port'] ?? '',
                        "username" => $data['username'] ?? '',
                        "email_id" => $data['email_id'] ?? '',
                        "encryption" => $data['encryption'] ?? '',
                        "password" => $data['password'] ?? ''
                    ]),
                    'updated_at' => now()
                    ]
                );
                Toastr::success(translate('messages.configuration_updated_successfully'));
                return back();
            }
            
            public function payment_index()
            {
                $published_status = 0; // Set a default value
                $payment_published_status = config('get_payment_publish_status');
                if (isset($payment_published_status[0]['is_published'])) {
                    $published_status = $payment_published_status[0]['is_published'];
                }
                
                $routes = config('addon_admin_routes');
                $desiredName = 'payment_setup';
                $payment_url = '';
                
                foreach ($routes as $routeArray) {
                    foreach ($routeArray as $route) {
                        if ($route['name'] === $desiredName) {
                            $payment_url = $route['url'];
                            break 2;
                        }
                    }
                }
                $data_values = Setting::whereIn('settings_type', ['payment_config'])->whereIn('key_name', ['ssl_commerz', 'paypal', 'stripe', 'razor_pay', 'senang_pay', 'paytabs', 'paystack', 'paymob_accept', 'paytm', 'flutterwave', 'liqpay', 'bkash', 'mercadopago'])->get();
                
                return view('admin-views.business-settings.payment-index', compact('published_status', 'payment_url', 'data_values'));
            }
            
            public function payment_update(Request $request, $name)
            {
                // dd($name);
                if (env('APP_MODE') == 'demo') {
                    Toastr::info(translate('messages.update_option_is_disable_for_demo'));
                    return back();
                }
                if ($name == 'cash_on_delivery') {
                    $payment = BusinessSetting::where('key', 'cash_on_delivery')->first();
                    if (isset($payment) == false) {
                        Helpers::businessInsert([
                            'key' => 'cash_on_delivery',
                            'value' => json_encode([
                                'status' => $request['status'],
                            ]),
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    } else {
                        BusinessSetting::where(['key' => 'cash_on_delivery'])->update([
                            'key' => 'cash_on_delivery',
                            'value' => json_encode([
                                'status' => $request['status'],
                            ]),
                            'updated_at' => now(),
                        ]);
                    }
                } elseif ($name == 'digital_payment') {
                    $payment = BusinessSetting::where('key', 'digital_payment')->first();
                    if (isset($payment) == false) {
                        Helpers::businessInsert([
                            'key' => 'digital_payment',
                            'value' => json_encode([
                                'status' => $request['status'],
                            ]),
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    } else {
                        BusinessSetting::where(['key' => 'digital_payment'])->update([
                            'key' => 'digital_payment',
                            'value' => json_encode([
                                'status' => $request['status'],
                            ]),
                            'updated_at' => now(),
                        ]);
                    }
                } elseif ($name == 'ssl_commerz_payment') {
                    $payment = BusinessSetting::where('key', 'ssl_commerz_payment')->first();
                    if (isset($payment) == false) {
                        Helpers::businessInsert([
                            'key' => 'ssl_commerz_payment',
                            'value' => json_encode([
                                'status' => 1,
                                'store_id' => '',
                                'store_password' => '',
                            ]),
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    } else {
                        BusinessSetting::where(['key' => 'ssl_commerz_payment'])->update([
                            'key' => 'ssl_commerz_payment',
                            'value' => json_encode([
                                'status' => $request['status'],
                                'store_id' => $request['store_id'],
                                'store_password' => $request['store_password'],
                            ]),
                            'updated_at' => now(),
                        ]);
                    }
                } elseif ($name == 'razor_pay') {
                    $payment = BusinessSetting::where('key', 'razor_pay')->first();
                    if (isset($payment) == false) {
                        Helpers::businessInsert([
                            'key' => 'razor_pay',
                            'value' => json_encode([
                                'status' => 1,
                                'razor_key' => '',
                                'razor_secret' => '',
                            ]),
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    } else {
                        BusinessSetting::where(['key' => 'razor_pay'])->update([
                            'key' => 'razor_pay',
                            'value' => json_encode([
                                'status' => $request['status'],
                                'razor_key' => $request['razor_key'],
                                'razor_secret' => $request['razor_secret'],
                            ]),
                            'updated_at' => now(),
                        ]);
                    }
                } elseif ($name == 'paypal') {
                    $payment = BusinessSetting::where('key', 'paypal')->first();
                    if (isset($payment) == false) {
                        Helpers::businessInsert([
                            'key' => 'paypal',
                            'value' => json_encode([
                                'status' => 1,
                                'mode' => '',
                                'paypal_client_id' => '',
                                'paypal_secret' => '',
                            ]),
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    } else {
                        BusinessSetting::where(['key' => 'paypal'])->update([
                            'key' => 'paypal',
                            'value' => json_encode([
                                'status' => $request['status'],
                                'mode' => $request['mode'],
                                'paypal_client_id' => $request['paypal_client_id'],
                                'paypal_secret' => $request['paypal_secret'],
                            ]),
                            'updated_at' => now(),
                        ]);
                    }
                } elseif ($name == 'stripe') {
                    $payment = BusinessSetting::where('key', 'stripe')->first();
                    if (isset($payment) == false) {
                        Helpers::businessInsert([
                            'key' => 'stripe',
                            'value' => json_encode([
                                'status' => 1,
                                'api_key' => '',
                                'published_key' => '',
                            ]),
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    } else {
                        BusinessSetting::where(['key' => 'stripe'])->update([
                            'key' => 'stripe',
                            'value' => json_encode([
                                'status' => $request['status'],
                                'api_key' => $request['api_key'],
                                'published_key' => $request['published_key'],
                            ]),
                            'updated_at' => now(),
                        ]);
                    }
                } elseif ($name == 'senang_pay') {
                    $payment = BusinessSetting::where('key', 'senang_pay')->first();
                    if (isset($payment) == false) {
                        Helpers::businessInsert([
                            
                            'key' => 'senang_pay',
                            'value' => json_encode([
                                'status' => 1,
                                'secret_key' => '',
                                'published_key' => '',
                                'merchant_id' => '',
                            ]),
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    } else {
                        BusinessSetting::where(['key' => 'senang_pay'])->update([
                            'key' => 'senang_pay',
                            'value' => json_encode([
                                'status' => $request['status'],
                                'secret_key' => $request['secret_key'],
                                'published_key' => $request['publish_key'],
                                'merchant_id' => $request['merchant_id'],
                            ]),
                            'updated_at' => now(),
                        ]);
                    }
                } elseif ($name == 'paystack') {
                    $payment = BusinessSetting::where('key', 'paystack')->first();
                    if (isset($payment) == false) {
                        Helpers::businessInsert([
                            'key' => 'paystack',
                            'value' => json_encode([
                                'status' => 1,
                                'publicKey' => '',
                                'secretKey' => '',
                                'paymentUrl' => '',
                                'merchantEmail' => '',
                            ]),
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    } else {
                        BusinessSetting::where(['key' => 'paystack'])->update([
                            'key' => 'paystack',
                            'value' => json_encode([
                                'status' => $request['status'],
                                'publicKey' => $request['publicKey'],
                                'secretKey' => $request['secretKey'],
                                'paymentUrl' => $request['paymentUrl'],
                                'merchantEmail' => $request['merchantEmail'],
                            ]),
                            'updated_at' => now(),
                        ]);
                    }
                } elseif ($name == 'flutterwave') {
                    $payment = BusinessSetting::where('key', 'flutterwave')->first();
                    if (isset($payment) == false) {
                        Helpers::businessInsert([
                            'key' => 'flutterwave',
                            'value' => json_encode([
                                'status' => 1,
                                'public_key' => '',
                                'secret_key' => '',
                                'hash' => '',
                            ]),
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    } else {
                        BusinessSetting::where(['key' => 'flutterwave'])->update([
                            'key' => 'flutterwave',
                            'value' => json_encode([
                                'status' => $request['status'],
                                'public_key' => $request['public_key'],
                                'secret_key' => $request['secret_key'],
                                'hash' => $request['hash'],
                            ]),
                            'updated_at' => now(),
                        ]);
                    }
                } elseif ($name == 'mercadopago') {
                    $payment = Helpers::businessUpdateOrInsert(
                        ['key' => 'mercadopago'],
                        [
                            'value' => json_encode([
                                'status' => $request['status'],
                                'public_key' => $request['public_key'],
                                'access_token' => $request['access_token'],
                            ]),
                            'updated_at' => now()
                            ]
                        );
                    } elseif ($name == 'paymob_accept') {
                        Helpers::businessUpdateOrInsert(['key' => 'paymob_accept'], [
                            'value' => json_encode([
                                'status' => $request['status'],
                                'api_key' => $request['api_key'],
                                'iframe_id' => $request['iframe_id'],
                                'integration_id' => $request['integration_id'],
                                'hmac' => $request['hmac'],
                            ]),
                            'updated_at' => now()
                        ]);
                    } elseif ($name == 'liqpay') {
                        Helpers::businessUpdateOrInsert(['key' => 'liqpay'], [
                            'value' => json_encode([
                                'status' => $request['status'],
                                'public_key' => $request['public_key'],
                                'private_key' => $request['private_key']
                            ]),
                            'updated_at' => now()
                        ]);
                    } elseif ($name == 'paytm') {
                        Helpers::businessUpdateOrInsert(['key' => 'paytm'], [
                            'value' => json_encode([
                                'status' => $request['status'],
                                'paytm_merchant_key' => $request['paytm_merchant_key'],
                                'paytm_merchant_mid' => $request['paytm_merchant_mid'],
                                'paytm_merchant_website' => $request['paytm_merchant_website'],
                                'paytm_refund_url' => $request['paytm_refund_url'],
                            ]),
                            'updated_at' => now()
                        ]);
                    } elseif ($name == 'bkash') {
                        Helpers::businessUpdateOrInsert(['key' => 'bkash'], [
                            'value' => json_encode([
                                'status' => $request['status'],
                                'api_key' => $request['api_key'],
                                'api_secret' => $request['api_secret'],
                                'username' => $request['username'],
                                'password' => $request['password'],
                            ]),
                            'updated_at' => now()
                        ]);
                    } elseif ($name == 'paytabs') {
                        Helpers::businessUpdateOrInsert(['key' => 'paytabs'], [
                            'value' => json_encode([
                                'status' => $request['status'],
                                'profile_id' => $request['profile_id'],
                                'server_key' => $request['server_key'],
                                'base_url' => $request['base_url']
                            ]),
                            'updated_at' => now()
                        ]);
                    }
                    
                    Toastr::success(translate('messages.payment_settings_updated'));
                    return back();
                }
                
                public function payment_config_update(Request $request)
                {
                    if ($request->toggle_type) {
                        Helpers::businessUpdateOrInsert(['key' => $request->toggle_type], [
                            'value' => $request->toggle_type == 'offline_payment_status' ? $request?->status : json_encode(['status' => $request?->status]),
                            'updated_at' => now()
                        ]);
                        Toastr::success(translate('messages.payment_settings_updated'));
                        return back();
                    }
                    
                    $request['status'] = $request->status ?? 0;
                    
                    $validation = [
                        'gateway' => 'required|in:ssl_commerz,paypal,stripe,razor_pay,senang_pay,paytabs,paystack,paymob_accept,paytm,flutterwave,liqpay,bkash,mercadopago',
                        'mode' => 'required|in:live,test'
                    ];
                    
                    $additional_data = [];
                    
                    if ($request['gateway'] == 'ssl_commerz') {
                        $additional_data = [
                            'status' => 'required|in:1,0',
                            'store_id' => 'required_if:status,1',
                            'store_password' => 'required_if:status,1'
                        ];
                    } elseif ($request['gateway'] == 'paypal') {
                        $additional_data = [
                            'status' => 'required|in:1,0',
                            'client_id' => 'required_if:status,1',
                            'client_secret' => 'required_if:status,1'
                        ];
                    } elseif ($request['gateway'] == 'stripe') {
                        $additional_data = [
                            'status' => 'required|in:1,0',
                            'api_key' => 'required_if:status,1',
                            'published_key' => 'required_if:status,1',
                        ];
                    } elseif ($request['gateway'] == 'razor_pay') {
                        $additional_data = [
                            'status' => 'required|in:1,0',
                            'api_key' => 'required_if:status,1',
                            'api_secret' => 'required_if:status,1'
                        ];
                    } elseif ($request['gateway'] == 'senang_pay') {
                        $additional_data = [
                            'status' => 'required|in:1,0',
                            'callback_url' => 'required_if:status,1',
                            'secret_key' => 'required_if:status,1',
                            'merchant_id' => 'required_if:status,1'
                        ];
                    } elseif ($request['gateway'] == 'paytabs') {
                        $additional_data = [
                            'status' => 'required|in:1,0',
                            'profile_id' => 'required_if:status,1',
                            'server_key' => 'required_if:status,1',
                            'base_url' => 'required_if:status,1'
                        ];
                    } elseif ($request['gateway'] == 'paystack') {
                        $additional_data = [
                            'status' => 'required|in:1,0',
                            'public_key' => 'required_if:status,1',
                            'secret_key' => 'required_if:status,1',
                            'merchant_email' => 'required_if:status,1'
                        ];
                    } elseif ($request['gateway'] == 'paymob_accept') {
                        $additional_data = [
                            'status' => 'required|in:1,0',
                            'callback_url' => 'required_if:status,1',
                            'api_key' => 'required_if:status,1',
                            'iframe_id' => 'required_if:status,1',
                            'integration_id' => 'required_if:status,1',
                            'hmac' => 'required_if:status,1'
                        ];
                    } elseif ($request['gateway'] == 'mercadopago') {
                        $additional_data = [
                            'status' => 'required|in:1,0',
                            'access_token' => 'required_if:status,1',
                            'public_key' => 'required_if:status,1'
                        ];
                    } elseif ($request['gateway'] == 'liqpay') {
                        $additional_data = [
                            'status' => 'required|in:1,0',
                            'private_key' => 'required_if:status,1',
                            'public_key' => 'required_if:status,1'
                        ];
                    } elseif ($request['gateway'] == 'flutterwave') {
                        $additional_data = [
                            'status' => 'required|in:1,0',
                            'secret_key' => 'required_if:status,1',
                            'public_key' => 'required_if:status,1',
                            'hash' => 'required_if:status,1'
                        ];
                    } elseif ($request['gateway'] == 'paytm') {
                        $additional_data = [
                            'status' => 'required|in:1,0',
                            'merchant_key' => 'required_if:status,1',
                            'merchant_id' => 'required_if:status,1',
                            'merchant_website_link' => 'required_if:status,1'
                        ];
                    } elseif ($request['gateway'] == 'bkash') {
                        $additional_data = [
                            'status' => 'required|in:1,0',
                            'app_key' => 'required_if:status,1',
                            'app_secret' => 'required_if:status,1',
                            'username' => 'required_if:status,1',
                            'password' => 'required_if:status,1',
                        ];
                    }
                    
                    $request->validate(array_merge($validation, $additional_data));
                    
                    $settings = Setting::where('key_name', $request['gateway'])->where('settings_type', 'payment_config')->first();
                    
                    $additional_data_image = $settings['additional_data'] != null ? json_decode($settings['additional_data']) : null;
                    
                    if ($request->has('gateway_image')) {
                        $gateway_image = $this->file_uploader('payment_modules/gateway_image/', 'png', $request['gateway_image'], $additional_data_image != null ? $additional_data_image->gateway_image : '');
                    } else {
                        $gateway_image = $additional_data_image != null ? $additional_data_image->gateway_image : '';
                    }
                    
                    $payment_additional_data = [
                        'gateway_title' => $request['gateway_title'],
                        'gateway_image' => $gateway_image,
                        'storage' => self::getDisk(),
                    ];
                    
                    $validator = Validator::make($request->all(), array_merge($validation, $additional_data));
                    
                    
                    $settings = Setting::firstOrNew(['key_name' => $request['gateway'], 'settings_type' => 'payment_config']);
                    $settings->live_values = $validator->validate();
                    $settings->test_values = $validator->validate();
                    $settings->mode = $request['mode'];
                    $settings->is_active = $request['status'];
                    $settings->additional_data = json_encode($payment_additional_data);
                    $settings->save();
                    
                    Toastr::success(GATEWAYS_DEFAULT_UPDATE_200['message']);
                    return back();
                }
                
                public function app_settings()
                {
                    return view('admin-views.business-settings.app-settings');
                }
                
                public function update_app_settings(Request $request)
                {
                    if (env('APP_MODE') == 'demo') {
                        Toastr::info(translate('messages.update_option_is_disable_for_demo'));
                        return back();
                    }
                    
                    if ($request->type == 'user_app') {
                        
                        Helpers::businessUpdateOrInsert(['key' => 'app_minimum_version_android'], [
                            'value' => $request['app_minimum_version_android']
                        ]);
                        
                        Helpers::businessUpdateOrInsert(['key' => 'app_minimum_version_ios'], [
                            'value' => $request['app_minimum_version_ios']
                        ]);
                        
                        Helpers::businessUpdateOrInsert(['key' => 'app_url_android'], [
                            'value' => $request['app_url_android']
                        ]);
                        
                        Helpers::businessUpdateOrInsert(['key' => 'app_url_ios'], [
                            'value' => $request['app_url_ios']
                        ]);
                        
                        $activationMode = DB::table('external_configurations')->where('key', 'activation_mode')->first();
                        $driveMondBaseUrl = DB::table('external_configurations')->where('key', 'drivemond_base_url')->first();
                        if ($activationMode && $activationMode->value == 1 && $driveMondBaseUrl && $driveMondBaseUrl->value != null) {
                            $name = \App\Models\BusinessSetting::where('key', 'business_name')->first();
                            $logo = \App\Models\BusinessSetting::where('key', 'logo')->first();
                            
                            $app_minimum_version_android=BusinessSetting::where(['key'=>'app_minimum_version_android'])->first()?->value;
                            $app_url_android=BusinessSetting::where(['key'=>'app_url_android'])->first()?->value;
                            $app_minimum_version_ios=BusinessSetting::where(['key'=>'app_minimum_version_ios'])->first()?->value;
                            $app_url_ios=BusinessSetting::where(['key'=>'app_url_ios'])->first()?->value;
                            
                            $response = Http::post($driveMondBaseUrl->value . '/api/store-configurations', [
                                'mart_business_name' => $name->value ?? "6amMart",
                                'mart_business_logo' => \App\CentralLogics\Helpers::get_full_url('business', $logo?->value ?? '', $logo?->storage[0]?->value ?? 'public', 'favicon') ?? asset('public/assets/admin/img/160x160/img2.jpg'),
                                'mart_app_minimum_version_android' => $app_minimum_version_android,
                                'mart_app_url_android' => $app_url_android,
                                'mart_app_minimum_version_ios' => $app_minimum_version_ios,
                                'mart_app_url_ios' => $app_url_ios,
                                
                            ]);
                        }
                        
                        Toastr::success(translate('messages.User_app_settings_updated'));
                        return back();
                    }
                    
                    if ($request->type == 'store_app') {
                        
                        Helpers::businessUpdateOrInsert(['key' => 'app_minimum_version_android_store'], [
                            'value' => $request['app_minimum_version_android_store']
                        ]);
                        Helpers::businessUpdateOrInsert(['key' => 'app_url_android_store'], [
                            'value' => $request['app_url_android_store']
                        ]);
                        Helpers::businessUpdateOrInsert(['key' => 'app_minimum_version_ios_store'], [
                            'value' => $request['app_minimum_version_ios_store']
                        ]);
                        Helpers::businessUpdateOrInsert(['key' => 'app_url_ios_store'], [
                            'value' => $request['app_url_ios_store']
                        ]);
                        
                        Toastr::success(translate('messages.Store_app_settings_updated'));
                        return back();
                    }
                    
                    
                    if ($request->type == 'deliveryman_app') {
                        
                        Helpers::businessUpdateOrInsert(['key' => 'app_minimum_version_android_deliveryman'], [
                            'value' => $request['app_minimum_version_android_deliveryman']
                        ]);
                        Helpers::businessUpdateOrInsert(['key' => 'app_url_android_deliveryman'], [
                            'value' => $request['app_url_android_deliveryman']
                        ]);
                        Helpers::businessUpdateOrInsert(['key' => 'app_minimum_version_ios_deliveryman'], [
                            'value' => $request['app_minimum_version_ios_deliveryman']
                        ]);
                        Helpers::businessUpdateOrInsert(['key' => 'app_url_ios_deliveryman'], [
                            'value' => $request['app_url_ios_deliveryman']
                        ]);
                        
                        Toastr::success(translate('messages.Delivery_app_settings_updated'));
                        return back();
                    }
                    
                    return back();
                }
                
                public function update_landing_page_settings(Request $request, $tab)
                {
                    if (env('APP_MODE') == 'demo') {
                        Toastr::info(translate('messages.update_option_is_disable_for_demo'));
                        return back();
                    }
                    
                    if ($tab == 'text') {
                        Helpers::businessUpdateOrInsert(['key' => 'landing_page_text'], [
                            'value' => json_encode([
                                'header_title_1' => $request['header_title_1'],
                                'header_title_2' => $request['header_title_2'],
                                'header_title_3' => $request['header_title_3'],
                                'about_title' => $request['about_title'],
                                'why_choose_us' => $request['why_choose_us'],
                                'why_choose_us_title' => $request['why_choose_us_title'],
                                'module_section_title' => $request['module_section_title'],
                                'module_section_sub_title' => $request['module_section_sub_title'],
                                'refer_section_title' => $request['refer_section_title'],
                                'refer_section_sub_title' => $request['refer_section_sub_title'],
                                'refer_section_description' => $request['refer_section_description'],
                                'joinus_section_title' => $request['joinus_section_title'],
                                'joinus_section_sub_title' => $request['joinus_section_sub_title'],
                                'download_app_section_title' => $request['download_app_section_title'],
                                'download_app_section_sub_title' => $request['download_app_section_sub_title'],
                                'testimonial_title' => $request['testimonial_title'],
                                'mobile_app_section_heading' => $request['mobile_app_section_heading'],
                                'mobile_app_section_text' => $request['mobile_app_section_text'],
                                'feature_section_description' => $request['feature_section_description'],
                                'feature_section_title' => $request['feature_section_title'],
                                'newsletter_title' => $request['newsletter_title'],
                                'newsletter_sub_title' => $request['newsletter_sub_title'],
                                'contact_us_title' => $request['contact_us_title'],
                                'contact_us_sub_title' => $request['contact_us_sub_title'],
                                'footer_article' => $request['footer_article']
                                ])
                            ]);
                            Toastr::success(translate('messages.landing_page_text_updated'));
                        } else if ($tab == 'links') {
                            Helpers::businessUpdateOrInsert(['key' => 'landing_page_links'], [
                                'value' => json_encode([
                                    'app_url_android_status' => $request['app_url_android_status'],
                                    'app_url_android' => $request['app_url_android'],
                                    'app_url_ios_status' => $request['app_url_ios_status'],
                                    'app_url_ios' => $request['app_url_ios'],
                                    'web_app_url_status' => $request['web_app_url_status'],
                                    'web_app_url' => $request['web_app_url'],
                                    'seller_app_url_status' => $request['seller_app_url_status'],
                                    'seller_app_url' => $request['seller_app_url'],
                                    'deliveryman_app_url_status' => $request['deliveryman_app_url_status'],
                                    'deliveryman_app_url' => $request['deliveryman_app_url']
                                    ])
                                ]);
                                Toastr::success(translate('messages.landing_page_links_updated'));
                            } else if ($tab == 'speciality') {
                                $data = [];
                                $imageName = null;
                                $speciality = BusinessSetting::where('key', 'speciality')->first();
                                if ($speciality) {
                                    $data = json_decode($speciality->value, true);
                                }
                                if ($request->has('image')) {
                                    $imageName = \Carbon\Carbon::now()->toDateString() . "-" . uniqid() . ".png";
                                    $request->image->move(public_path('assets/landing/image'), $imageName);
                                }
                                array_push($data, [
                                    'img' => $imageName,
                                    'title' => $request->speciality_title
                                ]);
                                
                                Helpers::businessUpdateOrInsert(['key' => 'speciality'], [
                                    'value' => json_encode($data)
                                ]);
                                Toastr::success(translate('messages.landing_page_speciality_updated'));
                            } else if ($tab == 'joinas') {
                                $data = [];
                                $joinas = BusinessSetting::where('key', 'join_as_images')->first();
                                if ($joinas) {
                                    $data = json_decode($joinas->value, true);
                                }
                                if ($request->has('seller_banner_bg')) {
                                    if (isset($data['seller_banner_bg']) && file_exists(public_path('assets/landing/image/' . $data['seller_banner_bg']))) {
                                        unlink(public_path('assets/landing/image/' . $data['seller_banner_bg']));
                                    }
                                    $imageName = \Carbon\Carbon::now()->toDateString() . "-" . uniqid() . ".png";
                                    $request->seller_banner_bg->move(public_path('assets/landing/image'), $imageName);
                                    $data['seller_banner_bg'] = $imageName;
                                }
                                
                                if ($request->has('deliveryman_banner_bg')) {
                                    if (isset($data['deliveryman_banner_bg']) && file_exists(public_path('assets/landing/image/' . $data['deliveryman_banner_bg']))) {
                                        unlink(public_path('assets/landing/image/' . $data['deliveryman_banner_bg']));
                                    }
                                    $imageName = \Carbon\Carbon::now()->toDateString() . "-" . uniqid() . ".png";
                                    $request->deliveryman_banner_bg->move(public_path('assets/landing/image'), $imageName);
                                    $data['deliveryman_banner_bg'] = $imageName;
                                }
                                Helpers::businessUpdateOrInsert(['key' => 'join_as_images'], [
                                    'value' => json_encode($data)
                                ]);
                                Toastr::success(translate('messages.landing_page_image_updated'));
                            } else if ($tab == 'download-section') {
                                $data = [];
                                $imageName = null;
                                $download = BusinessSetting::where('key', 'download_app_section')->first();
                                if ($download) {
                                    $data = json_decode($download->value, true);
                                }
                                if ($request->has('image')) {
                                    if (isset($data['img']) && file_exists(public_path('assets/landing/image/' . $data['img']))) {
                                        unlink(public_path('assets/landing/image/' . $data['img']));
                                    }
                                    $imageName = \Carbon\Carbon::now()->toDateString() . "-" . uniqid() . ".png";
                                    $request->image->move(public_path('assets/landing/image'), $imageName);
                                    $data['img'] = $imageName;
                                }
                                
                                if ($request->has('description')) {
                                    $data['description'] = $request->description;
                                }
                                
                                Helpers::businessUpdateOrInsert(['key' => 'download_app_section'], [
                                    'value' => json_encode($data)
                                ]);
                                
                                Toastr::success(translate('messages.landing_page_download_app_section_updated'));
                            } else if ($tab == 'counter-section') {
                                Helpers::businessUpdateOrInsert(['key' => 'counter_section'], [
                                    'value' => json_encode([
                                        'app_download_count_numbers' => $request['app_download_count_numbers'],
                                        'seller_count_numbers' => $request['seller_count_numbers'],
                                        'deliveryman_count_numbers' => $request['deliveryman_count_numbers'],
                                        ])
                                    ]);
                                    
                                    Toastr::success(translate('messages.landing_page_counter_section_updated'));
                                } else if ($tab == 'promotion-banner') {
                                    $data = [];
                                    $imageName = null;
                                    $promotion_banner = BusinessSetting::where('key', 'promotion_banner')->first();
                                    if ($promotion_banner) {
                                        $data = json_decode($promotion_banner->value, true);
                                    }
                                    if (count($data) >= 6) {
                                        Toastr::error(translate('messages.you_have_already_added_maximum_banner_image'));
                                        return back();
                                    }
                                    if ($request->has('image')) {
                                        $imageName = \Carbon\Carbon::now()->toDateString() . "-" . uniqid() . ".png";
                                        $request->image->move(public_path('assets/landing/image'), $imageName);
                                    }
                                    array_push($data, [
                                        'img' => $imageName,
                                        'title' => $request->title,
                                        'sub_title' => $request->sub_title,
                                    ]);
                                    
                                    Helpers::businessUpdateOrInsert(['key' => 'promotion_banner'], [
                                        'value' => json_encode($data)
                                    ]);
                                    Toastr::success(translate('messages.landing_page_promotion_banner_updated'));
                                } else if ($tab == 'module-section') {
                                    $request->validate([
                                        'module' => 'required',
                                        'description' => 'required'
                                    ]);
                                    $data = [];
                                    $imageName = null;
                                    $preImageName = null;
                                    $module_section = BusinessSetting::where('key', 'module_section')->first();
                                    if ($module_section) {
                                        $data = json_decode($module_section->value, true);
                                        if (isset($data[$request->module]['img'])) {
                                            $preImageName = $data[$request->module]['img'];
                                        }
                                    }
                                    
                                    if ($request->has('image')) {
                                        if ($preImageName && file_exists(public_path('assets/landing/image') . $preImageName)) {
                                            unlink(public_path('assets/landing/image') . $preImageName);
                                        }
                                        $imageName = \Carbon\Carbon::now()->toDateString() . "-" . uniqid() . ".png";
                                        $request->image->move(public_path('assets/landing/image'), $imageName);
                                    }
                                    
                                    $data[$request->module] = [
                                        'description' => $request->description,
                                        'img' => $imageName ?? $preImageName
                                    ];
                                    
                                    Helpers::businessUpdateOrInsert(['key' => 'module_section'], [
                                        'value' => json_encode($data)
                                    ]);
                                    Toastr::success(translate('messages.landing_page_module_section_updated'));
                                } else if ($tab == 'feature') {
                                    $data = [];
                                    $imageName = null;
                                    $feature = BusinessSetting::where('key', 'feature')->first();
                                    if ($feature) {
                                        $data = json_decode($feature->value, true);
                                    }
                                    if ($request->has('image')) {
                                        $imageName = \Carbon\Carbon::now()->toDateString() . "-" . uniqid() . ".png";
                                        $request->image->move(public_path('assets/landing/image'), $imageName);
                                    }
                                    array_push($data, [
                                        'img' => $imageName,
                                        'title' => $request->feature_title,
                                        'feature_description' => $request->feature_description
                                    ]);
                                    
                                    Helpers::businessUpdateOrInsert(['key' => 'feature'], [
                                        'value' => json_encode($data)
                                    ]);
                                    Toastr::success(translate('messages.landing_page_feature_updated'));
                                } else if ($tab == 'testimonial') {
                                    $data = [];
                                    $imageName = null;
                                    $brandImageName = null;
                                    $testimonial = BusinessSetting::where('key', 'testimonial')->first();
                                    if ($testimonial) {
                                        $data = json_decode($testimonial->value, true);
                                    }
                                    if ($request->has('image')) {
                                        $imageName = \Carbon\Carbon::now()->toDateString() . "-" . uniqid() . ".png";
                                        $request->image->move(public_path('assets/landing/image'), $imageName);
                                    }
                                    if ($request->has('brand_image')) {
                                        $brandImageName = \Carbon\Carbon::now()->toDateString() . "-" . uniqid() . ".png";
                                        $request->brand_image->move(public_path('assets/landing/image'), $brandImageName);
                                    }
                                    array_push($data, [
                                        'img' => $imageName,
                                        'brand_image' => $brandImageName,
                                        'name' => $request->reviewer_name,
                                        'position' => $request->reviewer_designation,
                                        'detail' => $request->review,
                                    ]);
                                    
                                    Helpers::businessUpdateOrInsert(['key' => 'testimonial'], [
                                        'value' => json_encode($data)
                                    ]);
                                    Toastr::success(translate('messages.landing_page_testimonial_updated'));
                                } else if ($tab == 'image') {
                                    $data = [];
                                    $images = BusinessSetting::where('key', 'landing_page_images')->first();
                                    if ($images) {
                                        $data = json_decode($images->value, true);
                                    }
                                    if ($request->has('top_content_image')) {
                                        if (isset($data['top_content_image']) && file_exists(public_path('assets/landing/image/' . $data['top_content_image']))) {
                                            unlink(public_path('assets/landing/image/' . $data['top_content_image']));
                                        }
                                        $imageName = \Carbon\Carbon::now()->toDateString() . "-" . uniqid() . ".png";
                                        $request->top_content_image->move(public_path('assets/landing/image'), $imageName);
                                        $data['top_content_image'] = $imageName;
                                    }
                                    if ($request->has('about_us_image')) {
                                        if (isset($data['about_us_image']) && file_exists(public_path('assets/landing/image/' . $data['about_us_image']))) {
                                            unlink(public_path('assets/landing/image/' . $data['about_us_image']));
                                        }
                                        $imageName = \Carbon\Carbon::now()->toDateString() . "-" . uniqid() . ".png";
                                        $request->about_us_image->move(public_path('assets/landing/image'), $imageName);
                                        $data['about_us_image'] = $imageName;
                                    }
                                    
                                    if ($request->has('feature_section_image')) {
                                        if (isset($data['feature_section_image']) && file_exists(public_path('assets/landing/image/' . $data['feature_section_image']))) {
                                            unlink(public_path('assets/landing/image/' . $data['feature_section_image']));
                                        }
                                        $imageName = \Carbon\Carbon::now()->toDateString() . "-" . uniqid() . ".png";
                                        $request->feature_section_image->move(public_path('assets/landing/image'), $imageName);
                                        $data['feature_section_image'] = $imageName;
                                    }
                                    if ($request->has('mobile_app_section_image')) {
                                        if (isset($data['mobile_app_section_image']) && file_exists(public_path('assets/landing/image/' . $data['mobile_app_section_image']))) {
                                            unlink(public_path('assets/landing/image/' . $data['mobile_app_section_image']));
                                        }
                                        $imageName = \Carbon\Carbon::now()->toDateString() . "-" . uniqid() . ".png";
                                        $request->mobile_app_section_image->move(public_path('assets/landing/image'), $imageName);
                                        $data['mobile_app_section_image'] = $imageName;
                                    }
                                    
                                    if ($request->has('contact_us_image')) {
                                        if (isset($data['contact_us_image']) && file_exists(public_path('assets/landing/image/' . $data['contact_us_image']))) {
                                            unlink(public_path('assets/landing/image/' . $data['contact_us_image']));
                                        }
                                        $imageName = \Carbon\Carbon::now()->toDateString() . "-" . uniqid() . ".png";
                                        $request->contact_us_image->move(public_path('assets/landing/image'), $imageName);
                                        $data['contact_us_image'] = $imageName;
                                    }
                                    
                                    Helpers::businessUpdateOrInsert(['key' => 'landing_page_images'], [
                                        'value' => json_encode($data)
                                    ]);
                                    Toastr::success(translate('messages.landing_page_image_updated'));
                                } else if ($tab == 'background-change') {
                                    Helpers::businessUpdateOrInsert(['key' => 'backgroundChange'], [
                                        'value' => json_encode([
                                            'primary_1_hex' => $request['header-bg'],
                                            'primary_1_rgb' => Helpers::hex_to_rbg($request['header-bg']),
                                            'primary_2_hex' => $request['footer-bg'],
                                            'primary_2_rgb' => Helpers::hex_to_rbg($request['footer-bg']),
                                            ])
                                        ]);
                                        Toastr::success(translate('messages.background_updated'));
                                    } else if ($tab == 'web-app') {
                                        $data = [];
                                        $images = BusinessSetting::where('key', 'web_app_landing_page_settings')->first();
                                        if ($images) {
                                            $data = json_decode($images->value, true);
                                        }
                                        if ($request->has('top_content_image')) {
                                            if (isset($data['top_content_image']) && file_exists(public_path('assets/landing/image/' . $data['top_content_image']))) {
                                                unlink(public_path('assets/landing/image/' . $data['top_content_image']));
                                            }
                                            $imageName = \Carbon\Carbon::now()->toDateString() . "-" . uniqid() . ".png";
                                            $request->top_content_image->move(public_path('assets/landing/image'), $imageName);
                                            $data['top_content_image'] = ['img' => $imageName, 'storage' => Helpers::getDisk()];
                                        }
                                        
                                        if ($request->has('mobile_app_section_image')) {
                                            if (isset($data['mobile_app_section_image']) && file_exists(public_path('assets/landing/image/' . $data['mobile_app_section_image']))) {
                                                unlink(public_path('assets/landing/image/' . $data['mobile_app_section_image']));
                                            }
                                            $imageName = \Carbon\Carbon::now()->toDateString() . "-" . uniqid() . ".png";
                                            $request->mobile_app_section_image->move(public_path('assets/landing/image'), $imageName);
                                            $data['mobile_app_section_image'] = ['img' => $imageName, 'storage' => Helpers::getDisk()];
                                        }
                                        Helpers::businessUpdateOrInsert(['key' => 'web_app_landing_page_settings'], [
                                            'value' => json_encode($data)
                                        ]);
                                        Toastr::success(translate('messages.web_app_landing_page_settings'));
                                    } else if ($tab == 'react_header') {
                                        $data = null;
                                        $image = BusinessSetting::firstOrNew(['key' => 'react_header_banner']);
                                        if ($image) {
                                            $data = $image->value;
                                        }
                                        $image_name = $data ?? \Carbon\Carbon::now()->toDateString() . "-" . uniqid() . ".png";
                                        if ($request->has('react_header_banner')) {
                                            // $image_name = ;
                                            $data = Helpers::update('react_landing/', $image_name, 'png', $request->file('react_header_banner')) ?? null;
                                        }
                                        
                                        $image->value = $data;
                                        $image->save();
                                        
                                        Toastr::success(translate('Landing page header banner updated'));
                                    } else if ($tab == 'hero-section') {
                                        $data = [];
                                        $hero_section = BusinessSetting::where('key', 'hero_section')->first();
                                        $data = [
                                            'hero_section_heading' => $request->hero_section_heading ?? $hero_section['hero_section_heading'],
                                            'hero_section_slogan' => $request->hero_section_slogan ?? $hero_section['hero_section_slogan'],
                                            'hero_section_short_description' => $request->hero_section_short_description ?? $hero_section['hero_section_short_description'],
                                        ];
                                        Helpers::businessUpdateOrInsert(['key' => 'hero_section'], [
                                            'value' => json_encode($data)
                                        ]);
                                        Toastr::success(translate('messages.landing_page_hero_section_updated'));
                                    } else if ($tab == 'full-banner') {
                                        $data = [];
                                        $banner_section_full = BusinessSetting::firstOrNew(['key' => 'banner_section_full']);
                                        $imageName = null;
                                        if ($banner_section_full) {
                                            $data = json_decode($banner_section_full->value, true);
                                            $imageName = $data['banner_section_img_full'] ?? null;
                                        }
                                        if ($request->has('banner_section_img_full')) {
                                            if (empty($imageName)) {
                                                $imageName = Helpers::upload('react_landing/', 'png', $request->file('banner_section_img_full'));
                                            } else {
                                                $imageName = Helpers::update('react_landing/', $data['banner_section_img_full'], 'png', $request->file('banner_section_img_full'));
                                            }
                                        }
                                        $data = [
                                            'banner_section_img_full' => $imageName,
                                            'full_banner_section_title' => $request->full_banner_section_title ?? $banner_section_full['full_banner_section_title'],
                                            'full_banner_section_sub_title' => $request->full_banner_section_sub_title ?? $banner_section_full['full_banner_section_sub_title'],
                                        ];
                                        $banner_section_full->value = json_encode($data);
                                        
                                        $banner_section_full->save();
                                        Toastr::success(translate('messages.landing_page_banner_section_updated'));
                                    } else if ($tab == 'delivery-service-section') {
                                        $data = [];
                                        $delivery_service_section = BusinessSetting::firstOrNew(['key' => 'delivery_service_section']);
                                        $imageName = null;
                                        if ($delivery_service_section) {
                                            $data = json_decode($delivery_service_section->value, true);
                                            $imageName = $data['delivery_service_section_image'] ?? null;
                                        }
                                        if ($request->has('delivery_service_section_image')) {
                                            if (empty($imageName)) {
                                                $imageName = Helpers::upload('react_landing/', 'png', $request->file('delivery_service_section_image'));
                                            } else {
                                                $imageName = Helpers::update('react_landing/', $data['delivery_service_section_image'], 'png', $request->file('delivery_service_section_image'));
                                            }
                                        }
                                        $data = [
                                            'delivery_service_section_image' => $imageName,
                                            'delivery_service_section_title' => $request->delivery_service_section_title ?? $delivery_service_section['delivery_service_section_title'],
                                            'delivery_service_section_description' => $request->delivery_service_section_description ?? $delivery_service_section['delivery_service_section_description'],
                                        ];
                                        $delivery_service_section->value = json_encode($data);
                                        
                                        $delivery_service_section->save();
                                        Toastr::success(translate('messages.landing_page_delivery_service_section_updated'));
                                    } else if ($tab == 'discount-banner') {
                                        $data = [];
                                        $discount_banner = BusinessSetting::firstOrNew(['key' => 'discount_banner']);
                                        $imageName = null;
                                        if ($discount_banner) {
                                            $data = json_decode($discount_banner->value, true);
                                            $imageName = $data['img'] ?? null;
                                        }
                                        if ($request->has('img')) {
                                            if (empty($imageName)) {
                                                $imageName = Helpers::upload('react_landing/', 'png', $request->file('img'));
                                            } else {
                                                $imageName = Helpers::update('react_landing/', $data['img'], 'png', $request->file('img'));
                                            }
                                        }
                                        $data = [
                                            'img' => $imageName,
                                            'title' => $request->title ?? $discount_banner['title'],
                                            'sub_title' => $request->sub_title ?? $discount_banner['sub_title'],
                                        ];
                                        $discount_banner->value = json_encode($data);
                                        
                                        $discount_banner->save();
                                        Toastr::success(translate('messages.landing_page_discount_banner_section_updated'));
                                    } else if ($tab == 'banner-section-half') {
                                        
                                        $data = [];
                                        $imageName = null;
                                        $banner_section_half = BusinessSetting::firstOrNew(['key' => 'banner_section_half']);
                                        if ($banner_section_half) {
                                            $data = json_decode($banner_section_half->value, true);
                                        }
                                        
                                        foreach ($request->banner_section_half as $key => $value) {
                                            
                                            if ($request->hasfile("banner_section_half.{$key}.img")) {
                                                if (isset($data[$key]['img'])) {
                                                    Helpers::check_and_delete('react_landing/', $data[$key]['img']);
                                                }
                                                
                                                $value['img'] = Helpers::upload('react_landing/', 'png', $request->file("banner_section_half.{$key}.img"));
                                            } elseif (isset($data[$key]['img'])) {
                                                $value['img'] = $data[$key]['img'];
                                            } else {
                                                $value['img'] = null;
                                            }
                                            $data[$key] = $value;
                                        }
                                        $banner_section_half->value = json_encode($data);
                                        
                                        $banner_section_half->save();
                                        Toastr::success(translate('messages.landing_page_banner_section_updated'));
                                    } else if ($tab == 'app_section_image') {
                                        $data = null;
                                        $image = BusinessSetting::firstOrNew(['key' => 'app_section_image']);
                                        if ($image) {
                                            $data = $image->value;
                                        }
                                        $image_name = $data ?? \Carbon\Carbon::now()->toDateString() . "-" . uniqid() . ".png";
                                        if ($request->has('app_section_image')) {
                                            $data = Helpers::update('react_landing/', $image_name, 'png', $request->file('app_section_image')) ?? null;
                                        }
                                        $image->value = $data;
                                        $image->save();
                                        Toastr::success(translate('App section image updated'));
                                    } else if ($tab == 'footer_logo') {
                                        $data = null;
                                        $image = BusinessSetting::firstOrNew(['key' => 'footer_logo']);
                                        if ($image) {
                                            $data = $image->value;
                                        }
                                        $image_name = $data ?? \Carbon\Carbon::now()->toDateString() . "-" . uniqid() . ".png";
                                        if ($request->has('footer_logo')) {
                                            $data = Helpers::update('react_landing/', $image_name, 'png', $request->file('footer_logo')) ?? null;
                                        }
                                        $image->value = $data;
                                        $image->save();
                                        Toastr::success(translate('Footer logo updated'));
                                    } else if ($tab == 'react-feature') {
                                        $data = [];
                                        $imageName = null;
                                        $feature = BusinessSetting::firstOrNew(['key' => 'react_feature']);
                                        if ($feature) {
                                            $data = json_decode($feature->value, true);
                                        }
                                        if ($request->has('image')) {
                                            $imageName = Helpers::upload('react_landing/feature/', 'png', $request->file('image'));
                                        }
                                        array_push($data, [
                                            'img' => $imageName,
                                            'title' => $request->feature_title,
                                            'feature_description' => $request->feature_description
                                        ]);
                                        $feature->value = json_encode($data);
                                        $feature->save();
                                        Toastr::success(translate('messages.landing_page_feature_updated'));
                                    } else if ($tab == 'app-download-button') {
                                        $data = [];
                                        $feature = BusinessSetting::where('key', 'app_download_button')->first();
                                        if ($feature) {
                                            $data = json_decode($feature->value, true);
                                        }
                                        array_push($data, [
                                            'button_text' => $request->button_text,
                                            'link' => $request->link
                                        ]);
                                        
                                        Helpers::businessUpdateOrInsert(['key' => 'app_download_button'], [
                                            'value' => json_encode($data)
                                        ]);
                                        Toastr::success(translate('messages.app_download_button_updated'));
                                    }
                                    return back();
                                }
                                
                                public function delete_landing_page_settings($tab, $key)
                                {
                                    if (env('APP_MODE') == 'demo') {
                                        Toastr::info(translate('messages.update_option_is_disable_for_demo'));
                                        return back();
                                    }
                                    
                                    $item = BusinessSetting::where('key', $tab)->first();
                                    $data = $item ? json_decode($item->value, true) : null;
                                    if ($data && array_key_exists($key, $data)) {
                                        if (isset($data[$key]['img']) && file_exists(public_path('assets/landing/image') . $data[$key]['img'])) {
                                            unlink(public_path('assets/landing/image') . $data[$key]['img']);
                                        }
                                        array_splice($data, $key, 1);
                                        
                                        $item->value = json_encode($data);
                                        $item->save();
                                        Toastr::success(translate('messages.' . $tab) . ' ' . translate('messages.deleted'));
                                        return back();
                                    }
                                    Toastr::error(translate('messages.not_found'));
                                    return back();
                                }
                                
                                
                                public function currency_index()
                                {
                                    return view('admin-views.business-settings.currency-index');
                                }
                                
                                public function currency_store(Request $request)
                                {
                                    $request->validate([
                                        'currency_code' => 'required|unique:currencies',
                                    ]);
                                    
                                    Currency::create([
                                        "country" => $request['country'],
                                        "currency_code" => $request['currency_code'],
                                        "currency_symbol" => $request['symbol'],
                                        "exchange_rate" => $request['exchange_rate'],
                                    ]);
                                    Toastr::success(translate('messages.currency_added_successfully'));
                                    return back();
                                }
                                
                                public function currency_edit($id)
                                {
                                    $currency = Currency::find($id);
                                    return view('admin-views.business-settings.currency-update', compact('currency'));
                                }
                                
                                public function currency_update(Request $request, $id)
                                {
                                    Currency::where(['id' => $id])->update([
                                        "country" => $request['country'],
                                        "currency_code" => $request['currency_code'],
                                        "currency_symbol" => $request['symbol'],
                                        "exchange_rate" => $request['exchange_rate'],
                                    ]);
                                    Toastr::success(translate('messages.currency_updated_successfully'));
                                    return redirect('vendor-panel/business-settings/currency-add');
                                }
                                
                                public function currency_delete($id)
                                {
                                    Currency::where(['id' => $id])->delete();
                                    Toastr::success(translate('messages.currency_deleted_successfully'));
                                    return back();
                                }
                                
                                private function update_data($request, $key_data)
                                {
                                    $data = DataSetting::firstOrNew(
                                        ['key' => $key_data,
                                        'type' => 'admin_landing_page'],
                                    );
                                    
                                    $data->value = $request->{$key_data}[array_search('default', $request->lang)];
                                    $data->save();
                                    $default_lang = str_replace('_', '-', app()->getLocale());
                                    foreach ($request->lang as $index => $key) {
                                        if ($default_lang == $key && !($request->{$key_data}[$index])) {
                                            if ($key != 'default') {
                                                Translation::updateOrInsert(
                                                    [
                                                        'translationable_type' => 'App\Models\DataSetting',
                                                        'translationable_id' => $data->id,
                                                        'locale' => $key,
                                                        'key' => $key_data
                                                    ],
                                                    ['value' => $data->getRawOriginal('value')]
                                                );
                                            }
                                        } else {
                                            if ($request->{$key_data}[$index] && $key != 'default') {
                                                Translation::updateOrInsert(
                                                    [
                                                        'translationable_type' => 'App\Models\DataSetting',
                                                        'translationable_id' => $data->id,
                                                        'locale' => $key,
                                                        'key' => $key_data
                                                    ],
                                                    ['value' => $request->{$key_data}[$index]]
                                                );
                                            }
                                        }
                                    }
                                    
                                    return true;
                                }
                                
                                
                                private function policy_status_update($key_data, $status)
                                {
                                    $data = DataSetting::firstOrNew(
                                        ['key' => $key_data,
                                        'type' => 'admin_landing_page'],
                                    );
                                    $data->value = $status;
                                    $data->save();
                                    
                                    return true;
                                }
                                
                                
                                public function terms_and_conditions()
                                {
                                    $terms_and_conditions = DataSetting::withoutGlobalScope('translate')->where('type', 'admin_landing_page')->where('key', 'terms_and_conditions')->first();
                                    return view('admin-views.business-settings.terms-and-conditions', compact('terms_and_conditions'));
                                }
                                
                                public function terms_and_conditions_update(Request $request)
                                {
                                    $this->update_data($request, 'terms_and_conditions');
                                    Toastr::success(translate('messages.terms_and_condition_updated'));
                                    return back();
                                }
                                
                                public function privacy_policy()
                                {
                                    $privacy_policy = DataSetting::withoutGlobalScope('translate')->where('type', 'admin_landing_page')->where('key', 'privacy_policy')->first();
                                    return view('admin-views.business-settings.privacy-policy', compact('privacy_policy'));
                                }
                                
                                public function privacy_policy_update(Request $request)
                                {
                                    $this->update_data($request, 'privacy_policy');
                                    Toastr::success(translate('messages.privacy_policy_updated'));
                                    return back();
                                }
                                
                                public function refund_policy()
                                {
                                    $refund_policy = DataSetting::withoutGlobalScope('translate')->where('type', 'admin_landing_page')->where('key', 'refund_policy')->first();
                                    $refund_policy_status = DataSetting::where('type', 'admin_landing_page')->where('key', 'refund_policy_status')->first();
                                    return view('admin-views.business-settings.refund_policy', compact('refund_policy', 'refund_policy_status'));
                                }
                                
                                public function refund_update(Request $request)
                                {
                                    $this->update_data($request, 'refund_policy');
                                    Toastr::success(translate('messages.refund_policy_updated'));
                                    return back();
                                }
                                
                                public function refund_policy_status($status)
                                {
                                    $this->policy_status_update('refund_policy_status', $status);
                                    return response()->json(['status' => "changed"]);
                                }
                                
                                public function shipping_policy()
                                {
                                    
                                    $shipping_policy = DataSetting::withoutGlobalScope('translate')->where('type', 'admin_landing_page')->where('key', 'shipping_policy')->first();
                                    $shipping_policy_status = DataSetting::where('type', 'admin_landing_page')->where('key', 'shipping_policy_status')->first();
                                    return view('admin-views.business-settings.shipping_policy', compact('shipping_policy', 'shipping_policy_status'));
                                }
                                
                                public function shipping_policy_update(Request $request)
                                {
                                    $this->update_data($request, 'shipping_policy');
                                    Toastr::success(translate('messages.shipping_policy_updated'));
                                    return back();
                                }
                                
                                
                                public function shipping_policy_status($status)
                                {
                                    $this->policy_status_update('shipping_policy_status', $status);
                                    return response()->json(['status' => "changed"]);
                                }
                                
                                public function cancellation_policy()
                                {
                                    $cancellation_policy = DataSetting::withoutGlobalScope('translate')->where('type', 'admin_landing_page')->where('key', 'cancellation_policy')->first();
                                    $cancellation_policy_status = DataSetting::where('type', 'admin_landing_page')->where('key', 'cancellation_policy_status')->first();
                                    return view('admin-views.business-settings.cancelation_policy', compact('cancellation_policy', 'cancellation_policy_status'));
                                }
                                
                                public function cancellation_policy_update(Request $request)
                                {
                                    $this->update_data($request, 'cancellation_policy');
                                    Toastr::success(translate('messages.cancellation_policy_updated'));
                                    return back();
                                }
                                
                                public function cancellation_policy_status($status)
                                {
                                    $this->policy_status_update('cancellation_policy_status', $status);
                                    return response()->json(['status' => "changed"]);
                                }
                                
                                public function about_us()
                                {
                                    $about_us = DataSetting::withoutGlobalScope('translate')->with('translations')->where('type', 'admin_landing_page')->where('key', 'about_us')->first();
                                    $about_title = DataSetting::withoutGlobalScope('translate')->with('translations')->where('type', 'admin_landing_page')->where('key', 'about_title')->first();
                                    return view('admin-views.business-settings.about-us', compact('about_us', 'about_title'));
                                }
                                
                                public function about_us_update(Request $request)
                                {
                                    $this->update_data($request, 'about_us');
                                    $this->update_data($request, 'about_title');
                                    Toastr::success(translate('messages.about_us_updated'));
                                    return back();
                                }
                                
                                public function fcm_index(Request $request)
                                {
                                    abort_if($request?->module_type == 'rental' && !addon_published_status('Rental'), 404);
                                    return view($request->module_type == 'rental' && addon_published_status('Rental')
                                    ? 'admin-views.business-settings.fcm-index-rental'
                                    : 'admin-views.business-settings.fcm-index');
                                }
                                
                                public function fcm_config()
                                {
                                    $fcm_credentials = Helpers::get_business_settings('fcm_credentials');
                                    return view('admin-views.business-settings.fcm-config', compact('fcm_credentials'));
                                }
                                
                                public function update_fcm(Request $request)
                                {
                                    Helpers::businessUpdateOrInsert(['key' => 'push_notification_service_file_content'], [
                                        'value' => $request['push_notification_service_file_content'],
                                    ]);
                                    
                                    Helpers::businessUpdateOrInsert(['key' => 'fcm_project_id'], [
                                        'value' => $request['projectId']
                                    ]);
                                    
                                    Helpers::businessUpdateOrInsert(['key' => 'fcm_credentials'], [
                                        'value' => json_encode([
                                            'apiKey' => $request->apiKey,
                                            'authDomain' => $request->authDomain,
                                            'projectId' => $request->projectId,
                                            'storageBucket' => $request->storageBucket,
                                            'messagingSenderId' => $request->messagingSenderId,
                                            'appId' => $request->appId,
                                            'measurementId' => $request->measurementId
                                            ])
                                        ]);
                                        self::firebase_message_config_file_gen();
                                        Toastr::success(translate('messages.settings_updated'));
                                        return back();
                                    }
                                    
                                    function firebase_message_config_file_gen()
                                    {
                                        $config = Helpers::get_business_settings('fcm_credentials');
                                        
                                        $apiKey = $config['apiKey'] ?? '';
                                        $authDomain = $config['authDomain'] ?? '';
                                        $projectId = $config['projectId'] ?? '';
                                        $storageBucket = $config['storageBucket'] ?? '';
                                        $messagingSenderId = $config['messagingSenderId'] ?? '';
                                        $appId = $config['appId'] ?? '';
                                        $measurementId = $config['measurementId'] ?? '';
                                        
                                        $filePath = base_path('firebase-messaging-sw.js');
                                        
                                        try {
                                            if (file_exists($filePath) && !is_writable($filePath)) {
                                                if (!chmod($filePath, 0644)) {
                                                    throw new \Exception('File is not writable and permission change failed: ' . $filePath);
                                                }
                                            }
                                            
                                            $fileContent = <<<JS
                                            importScripts('https://www.gstatic.com/firebasejs/8.3.2/firebase-app.js');
                                            importScripts('https://www.gstatic.com/firebasejs/8.3.2/firebase-messaging.js');
                                            
                                            firebase.initializeApp({
                                                apiKey: "$apiKey",
                                                authDomain: "$authDomain",
                                                projectId: "$projectId",
                                                storageBucket: "$storageBucket",
                                                messagingSenderId: "$messagingSenderId",
                                                appId: "$appId",
                                                measurementId: "$measurementId"
                                            });
                                            
                                            const messaging = firebase.messaging();
                                            messaging.setBackgroundMessageHandler(function (payload) {
                                                return self.registration.showNotification(payload.data.title, {
                                                    body: payload.data.body ? payload.data.body : '',
                                                    icon: payload.data.icon ? payload.data.icon : ''
                                                });
                                            });
                                            JS;
                                            
                                            
                                            if (file_put_contents($filePath, $fileContent) === false) {
                                                throw new \Exception('Failed to write to file: ' . $filePath);
                                            }
                                            
                                        } catch (\Exception $e) {
                                            //
                                        }
                                    }
                                    
                                    public function update_fcm_messages(Request $request)
                                    {
                                        $notification = NotificationMessage::where('module_type', $request->module_type)->where('key', 'order_pending_message')->first();
                                        if ($notification == null) {
                                            $notification = new NotificationMessage();
                                        }
                                        
                                        $notification->key = 'order_pending_message';
                                        $notification->module_type = $request->module_type;
                                        $notification->message = $request->pending_message[array_search('en', $request->lang)];
                                        $notification->status = $request['pending_status'] == 1 ? 1 : 0;
                                        $notification->save();
                                        foreach ($request->lang as $index => $key) {
                                            if ($request->pending_message[$index]) {
                                                Translation::updateOrInsert(
                                                    [
                                                        'translationable_type' => 'App\Models\NotificationMessage',
                                                        'translationable_id' => $notification->id,
                                                        'locale' => $key,
                                                        'key' => $notification->key
                                                    ],
                                                    ['value' => $request->pending_message[$index]]
                                                );
                                            }
                                        }
                                        
                                        $notification = NotificationMessage::where('module_type', $request->module_type)->where('key', 'order_confirmation_msg')->first();
                                        if ($notification == null) {
                                            $notification = new NotificationMessage();
                                        }
                                        
                                        $notification->key = 'order_confirmation_msg';
                                        $notification->module_type = $request->module_type;
                                        $notification->message = $request->confirm_message[array_search('en', $request->lang)];
                                        $notification->status = $request['confirm_status'] == 1 ? 1 : 0;
                                        $notification->save();
                                        foreach ($request->lang as $index => $key) {
                                            if ($request->confirm_message[$index]) {
                                                Translation::updateOrInsert(
                                                    [
                                                        'translationable_type' => 'App\Models\NotificationMessage',
                                                        'translationable_id' => $notification->id,
                                                        'locale' => $key,
                                                        'key' => $notification->key
                                                    ],
                                                    ['value' => $request->confirm_message[$index]]
                                                );
                                            }
                                        }
                                        if ($request->module_type != 'parcel') {
                                            
                                            
                                            $notification = NotificationMessage::where('module_type', $request->module_type)->where('key', 'order_processing_message')->first();
                                            if ($notification == null) {
                                                $notification = new NotificationMessage();
                                            }
                                            
                                            $notification->key = 'order_processing_message';
                                            $notification->module_type = $request->module_type;
                                            $notification->message = $request->processing_message[array_search('en', $request->lang)];
                                            $notification->status = $request['processing_status'] == 1 ? 1 : 0;
                                            $notification->save();
                                            foreach ($request->lang as $index => $key) {
                                                if ($request->processing_message[$index]) {
                                                    Translation::updateOrInsert(
                                                        [
                                                            'translationable_type' => 'App\Models\NotificationMessage',
                                                            'translationable_id' => $notification->id,
                                                            'locale' => $key,
                                                            'key' => $notification->key
                                                        ],
                                                        ['value' => $request->processing_message[$index]]
                                                    );
                                                }
                                            }
                                            
                                            $notification = NotificationMessage::where('module_type', $request->module_type)->where('key', 'order_handover_message')->first();
                                            if ($notification == null) {
                                                $notification = new NotificationMessage();
                                            }
                                            
                                            $notification->key = 'order_handover_message';
                                            $notification->module_type = $request->module_type;
                                            $notification->message = $request->order_handover_message[array_search('en', $request->lang)];
                                            $notification->status = $request['order_handover_message_status'] == 1 ? 1 : 0;
                                            $notification->save();
                                            foreach ($request->lang as $index => $key) {
                                                if ($request->order_handover_message[$index]) {
                                                    Translation::updateOrInsert(
                                                        [
                                                            'translationable_type' => 'App\Models\NotificationMessage',
                                                            'translationable_id' => $notification->id,
                                                            'locale' => $key,
                                                            'key' => $notification->key
                                                        ],
                                                        ['value' => $request->order_handover_message[$index]]
                                                    );
                                                }
                                            }
                                            
                                            $notification = NotificationMessage::where('module_type', $request->module_type)->where('key', 'order_refunded_message')->first();
                                            if ($notification == null) {
                                                $notification = new NotificationMessage();
                                            }
                                            
                                            $notification->key = 'order_refunded_message';
                                            $notification->module_type = $request->module_type;
                                            $notification->message = $request->order_refunded_message[array_search('en', $request->lang)];
                                            $notification->status = $request['order_refunded_message_status'] == 1 ? 1 : 0;
                                            $notification->save();
                                            foreach ($request->lang as $index => $key) {
                                                if ($request->order_refunded_message[$index]) {
                                                    Translation::updateOrInsert(
                                                        [
                                                            'translationable_type' => 'App\Models\NotificationMessage',
                                                            'translationable_id' => $notification->id,
                                                            'locale' => $key,
                                                            'key' => $notification->key
                                                        ],
                                                        ['value' => $request->order_refunded_message[$index]]
                                                    );
                                                }
                                            }
                                            
                                            $notification = NotificationMessage::where('module_type', $request->module_type)->where('key', 'refund_request_canceled')->first();
                                            
                                            if ($notification == null) {
                                                $notification = new NotificationMessage();
                                            }
                                            
                                            $notification->key = 'refund_request_canceled';
                                            $notification->module_type = $request->module_type;
                                            $notification->message = $request->refund_request_canceled[array_search('en', $request->lang)];
                                            $notification->status = $request['refund_request_canceled_status'] == 1 ? 1 : 0;
                                            $notification->save();
                                            foreach ($request->lang as $index => $key) {
                                                if ($request->refund_request_canceled[$index]) {
                                                    Translation::updateOrInsert(
                                                        [
                                                            'translationable_type' => 'App\Models\NotificationMessage',
                                                            'translationable_id' => $notification->id,
                                                            'locale' => $key,
                                                            'key' => $notification->key
                                                        ],
                                                        ['value' => $request->refund_request_canceled[$index]]
                                                    );
                                                }
                                            }
                                        }
                                        
                                        
                                        $notification = NotificationMessage::where('module_type', $request->module_type)->where('key', 'out_for_delivery_message')->first();
                                        if ($notification == null) {
                                            $notification = new NotificationMessage();
                                        }
                                        
                                        $notification->key = 'out_for_delivery_message';
                                        $notification->module_type = $request->module_type;
                                        $notification->message = $request->out_for_delivery_message[array_search('en', $request->lang)];
                                        $notification->status = $request['out_for_delivery_status'] == 1 ? 1 : 0;
                                        $notification->save();
                                        foreach ($request->lang as $index => $key) {
                                            if ($request->out_for_delivery_message[$index]) {
                                                Translation::updateOrInsert(
                                                    [
                                                        'translationable_type' => 'App\Models\NotificationMessage',
                                                        'translationable_id' => $notification->id,
                                                        'locale' => $key,
                                                        'key' => $notification->key
                                                    ],
                                                    ['value' => $request->out_for_delivery_message[$index]]
                                                );
                                            }
                                        }
                                        
                                        $notification = NotificationMessage::where('module_type', $request->module_type)->where('key', 'order_delivered_message')->first();
                                        if ($notification == null) {
                                            $notification = new NotificationMessage();
                                        }
                                        
                                        $notification->key = 'order_delivered_message';
                                        $notification->module_type = $request->module_type;
                                        $notification->message = $request->delivered_message[array_search('en', $request->lang)];
                                        $notification->status = $request['delivered_status'] == 1 ? 1 : 0;
                                        $notification->save();
                                        foreach ($request->lang as $index => $key) {
                                            if ($request->delivered_message[$index]) {
                                                Translation::updateOrInsert(
                                                    [
                                                        'translationable_type' => 'App\Models\NotificationMessage',
                                                        'translationable_id' => $notification->id,
                                                        'locale' => $key,
                                                        'key' => $notification->key
                                                    ],
                                                    ['value' => $request->delivered_message[$index]]
                                                );
                                            }
                                        }
                                        
                                        $notification = NotificationMessage::where('module_type', $request->module_type)->where('key', 'delivery_boy_assign_message')->first();
                                        if ($notification == null) {
                                            $notification = new NotificationMessage();
                                        }
                                        
                                        $notification->key = 'delivery_boy_assign_message';
                                        $notification->module_type = $request->module_type;
                                        $notification->message = $request->delivery_boy_assign_message[array_search('en', $request->lang)];
                                        $notification->status = $request['delivery_boy_assign_status'] == 1 ? 1 : 0;
                                        $notification->save();
                                        foreach ($request->lang as $index => $key) {
                                            if ($request->delivery_boy_assign_message[$index]) {
                                                Translation::updateOrInsert(
                                                    [
                                                        'translationable_type' => 'App\Models\NotificationMessage',
                                                        'translationable_id' => $notification->id,
                                                        'locale' => $key,
                                                        'key' => $notification->key
                                                    ],
                                                    ['value' => $request->delivery_boy_assign_message[$index]]
                                                );
                                            }
                                        }
                                        
                                        $notification = NotificationMessage::where('module_type', $request->module_type)->where('key', 'delivery_boy_delivered_message')->first();
                                        if ($notification == null) {
                                            $notification = new NotificationMessage();
                                        }
                                        
                                        $notification->key = 'delivery_boy_delivered_message';
                                        $notification->module_type = $request->module_type;
                                        $notification->message = $request->delivery_boy_delivered_message[array_search('en', $request->lang)];
                                        $notification->status = $request['delivery_boy_delivered_status'] == 1 ? 1 : 0;
                                        $notification->save();
                                        foreach ($request->lang as $index => $key) {
                                            if ($request->delivery_boy_delivered_message[$index]) {
                                                Translation::updateOrInsert(
                                                    [
                                                        'translationable_type' => 'App\Models\NotificationMessage',
                                                        'translationable_id' => $notification->id,
                                                        'locale' => $key,
                                                        'key' => $notification->key
                                                    ],
                                                    ['value' => $request->delivery_boy_delivered_message[$index]]
                                                );
                                            }
                                        }
                                        
                                        $notification = NotificationMessage::where('module_type', $request->module_type)->where('key', 'order_cancled_message')->first();
                                        if ($notification == null) {
                                            $notification = new NotificationMessage();
                                        }
                                        
                                        $notification->key = 'order_cancled_message';
                                        $notification->module_type = $request->module_type;
                                        $notification->message = $request->order_cancled_message[array_search('en', $request->lang)];
                                        $notification->status = $request['order_cancled_message_status'] == 1 ? 1 : 0;
                                        $notification->save();
                                        foreach ($request->lang as $index => $key) {
                                            if ($request->order_cancled_message[$index]) {
                                                Translation::updateOrInsert(
                                                    [
                                                        'translationable_type' => 'App\Models\NotificationMessage',
                                                        'translationable_id' => $notification->id,
                                                        'locale' => $key,
                                                        'key' => $notification->key
                                                    ],
                                                    ['value' => $request->order_cancled_message[$index]]
                                                );
                                            }
                                        }
                                        
                                        $notification = NotificationMessage::where('module_type', $request->module_type)->where('key', 'offline_order_accept_message')->first();
                                        if ($notification == null) {
                                            $notification = new NotificationMessage();
                                        }
                                        
                                        $notification->key = 'offline_order_accept_message';
                                        $notification->module_type = $request->module_type;
                                        $notification->message = $request->offline_order_accept_message[array_search('en', $request->lang)];
                                        $notification->status = $request['offline_order_accept_message_status'] == 1 ? 1 : 0;
                                        $notification->save();
                                        foreach ($request->lang as $index => $key) {
                                            if ($request->offline_order_accept_message[$index]) {
                                                Translation::updateOrInsert(
                                                    [
                                                        'translationable_type' => 'App\Models\NotificationMessage',
                                                        'translationable_id' => $notification->id,
                                                        'locale' => $key,
                                                        'key' => $notification->key
                                                    ],
                                                    ['value' => $request->offline_order_accept_message[$index]]
                                                );
                                            }
                                        }
                                        
                                        $notification = NotificationMessage::where('module_type', $request->module_type)->where('key', 'offline_order_deny_message')->first();
                                        if ($notification == null) {
                                            $notification = new NotificationMessage();
                                        }
                                        
                                        $notification->key = 'offline_order_deny_message';
                                        $notification->module_type = $request->module_type;
                                        $notification->message = $request->offline_order_deny_message[array_search('en', $request->lang)];
                                        $notification->status = $request['offline_order_deny_message_status'] == 1 ? 1 : 0;
                                        $notification->save();
                                        foreach ($request->lang as $index => $key) {
                                            if ($request->offline_order_deny_message[$index]) {
                                                Translation::updateOrInsert(
                                                    [
                                                        'translationable_type' => 'App\Models\NotificationMessage',
                                                        'translationable_id' => $notification->id,
                                                        'locale' => $key,
                                                        'key' => $notification->key
                                                    ],
                                                    ['value' => $request->offline_order_deny_message[$index]]
                                                );
                                            }
                                        }
                                        
                                        
                                        Toastr::success(translate('messages.message_updated'));
                                        return back();
                                    }
                                    public function update_fcm_messages_rental(Request $request)
                                    {
                                        $messageKeys = [
                                            'trip_pending_message' => 'trip_pending_message',
                                            'trip_confirm_message' => 'trip_confirm_message',
                                            'trip_ongoing_message' => 'trip_ongoing_message',
                                            'trip_complete_message' => 'trip_complete_message',
                                            'trip_cancel_message' => 'trip_cancel_message',
                                        ];
                                        
                                        foreach ($messageKeys as $requestKey => $notificationKey) {
                                            
                                            $notification = NotificationMessage::firstOrNew([
                                                'module_type' => 'rental',
                                                'key' => $notificationKey,
                                            ]);
                                            
                                            
                                            $notification->message = $request[$requestKey][array_search('en', $request->lang)];
                                            $notification->status = isset($request[$requestKey . '_status']) && $request[$requestKey . '_status'] == 1 ? 1 : 0;
                                            $notification->save();
                                            
                                            foreach ($request->lang as $index => $locale) {
                                                if (!empty($request[$requestKey][$index])) {
                                                    Translation::updateOrInsert(
                                                        [
                                                            'translationable_type' => NotificationMessage::class,
                                                            'translationable_id' => $notification->id,
                                                            'locale' => $locale,
                                                            'key' => $notificationKey,
                                                        ],
                                                        ['value' => $request[$requestKey][$index]]
                                                    );
                                                }
                                            }
                                        }
                                        
                                        Toastr::success(translate('messages.message_updated'));
                                        return back();
                                    }
                                    
                                    
                                    public function location_setup(Request $request)
                                    {
                                        $store = Helpers::get_store_id();
                                        $store->latitude = $request['latitude'];
                                        $store->longitude = $request['longitude'];
                                        $store->save();
                                        
                                        Toastr::success(translate('messages.settings_updated'));
                                        return back();
                                    }
                                    
                                    public function config_setup()
                                    {
                                        return view('admin-views.business-settings.config');
                                    }
                                    
                                    public function config_update(Request $request)
                                    {
                                        Helpers::businessUpdateOrInsert(['key' => 'map_api_key'], [
                                            'value' => $request['map_api_key']
                                        ]);
                                        
                                        Helpers::businessUpdateOrInsert(['key' => 'map_api_key_server'], [
                                            'value' => $request['map_api_key_server']
                                        ]);
                                        
                                        Toastr::success(translate('messages.config_data_updated'));
                                        return back();
                                    }
                                    
                                    public function toggle_settings($key, $value)
                                    {
                                        Helpers::businessUpdateOrInsert(['key' => $key], [
                                            'value' => $value
                                        ]);
                                        
                                        Toastr::success(translate('messages.app_settings_updated'));
                                        return back();
                                    }
                                    
                                    public function viewSocialLogin()
                                    {
                                        $data = BusinessSetting::where('key', 'social_login')->first();
                                        if (!$data) {
                                            Helpers::insert_business_settings_key('social_login', '[{"login_medium":"google","client_id":"","client_secret":"","status":"0"},{"login_medium":"facebook","client_id":"","client_secret":"","status":""}]');
                                            $data = BusinessSetting::where('key', 'social_login')->first();
                                        }
                                        $apple = BusinessSetting::where('key', 'apple_login')->first();
                                        if (!$apple) {
                                            Helpers::insert_business_settings_key('apple_login', '[{"login_medium":"apple","client_id":"","client_secret":"","team_id":"","key_id":"","service_file":"","redirect_url":"","status":""}]');
                                            $apple = BusinessSetting::where('key', 'apple_login')->first();
                                        }
                                        $appleLoginServices = json_decode($apple->value, true);
                                        $socialLoginServices = json_decode($data->value, true);
                                        return view('admin-views.business-settings.social-login.view', compact('socialLoginServices', 'appleLoginServices'));
                                    }
                                    
                                    public function updateSocialLogin($service, Request $request)
                                    {
                                        $login_setup_status = Helpers::get_business_settings($service.'_login_status')??0;
                                        if($login_setup_status && ($request['status']==0)){
                                            Toastr::warning(translate($service.'_login_status_is_enabled_in_login_setup._First_disable_from_login_setup.'));
                                            return redirect()->back();
                                        }
                                        $socialLogin = BusinessSetting::where('key', 'social_login')->first();
                                        $credential_array = [];
                                        foreach (json_decode($socialLogin['value'], true) as $key => $data) {
                                            if ($data['login_medium'] == $service) {
                                                $cred = [
                                                    'login_medium' => $service,
                                                    'client_id' => $request['client_id'],
                                                    'client_secret' => $request['client_secret'],
                                                    'status' => $request['status'],
                                                ];
                                                array_push($credential_array, $cred);
                                            } else {
                                                array_push($credential_array, $data);
                                            }
                                        }
                                        
                                        Helpers::businessUpdateOrInsert(['key' => 'social_login'], [
                                            'value' =>$credential_array
                                        ]);
                                        
                                        Toastr::success(translate('messages.credential_updated', ['service' => $service]));
                                        return redirect()->back();
                                    }
                                    
                                    public function updateAppleLogin($service, Request $request)
                                    {
                                        $appleLogin = BusinessSetting::where('key', 'apple_login')->firstOrNew(['key' => 'apple_login']);
                                        $credential_array = [];
                                        if ($request->hasfile('service_file')) {
                                            $fileName = Helpers::upload('apple-login/', 'p8', $request->file('service_file'));
                                        }
                                        foreach (json_decode($appleLogin['value'], true) as $key => $data) {
                                            if ($data['login_medium'] == $service) {
                                                $cred = [
                                                    'login_medium' => $service,
                                                    'client_id' => $request['client_id'],
                                                    'client_id_app' => $request['client_id_app'],
                                                    'client_secret' => $request['client_secret'],
                                                    'status' => $request['status'],
                                                    'team_id' => $request['team_id'],
                                                    'key_id' => $request['key_id'],
                                                    'service_file' => isset($fileName) ? $fileName : $data['service_file'],
                                                    'redirect_url_flutter' => $request['redirect_url_flutter'],
                                                    'redirect_url_react' => $request['redirect_url_react'],
                                                ];
                                                array_push($credential_array, $cred);
                                            } else {
                                                array_push($credential_array, $data);
                                            }
                                        }
                                        $appleLogin->value = $credential_array;
                                        
                                        $appleLogin->save();
                                        
                                        Toastr::success(translate('messages.credential_updated', ['service' => $service]));
                                        return redirect()->back();
                                    }
                                    
                                    public function login_settings(){
                                        $data = array_column(BusinessSetting::whereIn('key',['manual_login_status','otp_login_status','social_login_status','google_login_status','facebook_login_status','apple_login_status','email_verification_status','phone_verification_status'
                                        ])->get(['key','value'])->toArray(), 'value', 'key');
                                        
                                        return view('admin-views.login-setup.login_page',compact('data'));
                                    }
                                    
                                    public function login_settings_update(Request $request)
                                    {
                                        $social_login = [];
                                        $social_login_data=Helpers::get_business_settings('social_login') ?? [];
                                        foreach ($social_login_data as $social) {
                                            $social_login[$social['login_medium']] = (boolean)$social['status'];
                                        }
                                        $social_login_data=Helpers::get_business_settings('apple_login') ?? [];
                                        foreach ($social_login_data as $social) {
                                            $social_login[$social['login_medium']] = (boolean)$social['status'];
                                        }
                                        
                                        $is_firebase_active=Helpers::get_business_settings('firebase_otp_verification') ?? 0;
                                        
                                        $is_sms_active= Setting::whereJsonContains('live_values->status','1')->where('settings_type', 'sms_config')->exists();
                                        
                                        $is_mail_active= config('mail.status');
                                        
                                        if(!$request['manual_login_status'] && !$request['otp_login_status'] && !$request['social_login_status']){
                                            Session::flash('select-one-method', true);
                                            return back();
                                        }
                                        
                                        if($request['otp_login_status'] && !$is_sms_active && !$is_firebase_active){
                                            Session::flash('sms-config', true);
                                            return back();
                                        }
                                        
                                        if(!$request['manual_login_status'] && !$request['otp_login_status'] && $request['social_login_status']){
                                            if(!$request['google_login_status'] && !$request['facebook_login_status']){
                                                Session::flash('select-one-method-android', true);
                                                return back();
                                            }
                                        }
                                        if( $request['social_login_status'] &&  !$request['google_login_status'] && !$request['facebook_login_status'] && !$request['apple_login_status']){
                                            Session::flash('select-one-method-social-login', true);
                                            return back();
                                        }
                                        
                                        if(($request['social_login_status'] && $request['google_login_status'] && !isset($social_login['google'])) || ($request['social_login_status'] && ($request['google_login_status'] && isset($social_login['google'])) && !$social_login['google'])){
                                            Session::flash('setup-google', true);
                                            return back();
                                        }
                                        
                                        if(($request['social_login_status'] && $request['facebook_login_status'] && !isset($social_login['facebook'])) || ($request['social_login_status'] && ($request['facebook_login_status'] && isset($social_login['facebook'])) && !$social_login['facebook'])){
                                            Session::flash('setup-facebook', true);
                                            return back();
                                        }
                                        
                                        if(($request['social_login_status'] && $request['apple_login_status'] && !isset($social_login['apple'])) || ($request['social_login_status'] && ($request['apple_login_status'] && isset($social_login['apple'])) && !$social_login['apple'])){
                                            Session::flash('setup-apple', true);
                                            return back();
                                        }
                                        
                                        if($request['phone_verification_status'] && !$is_sms_active && !$is_firebase_active){
                                            Session::flash('sms-config-verification', true);
                                            return back();
                                        }
                                        
                                        if($request['email_verification_status'] && !$is_mail_active){
                                            Session::flash('mail-config-verification', true);
                                            return back();
                                        }
                                        
                                        
                                        Helpers::businessUpdateOrInsert(['key' => 'manual_login_status'], [
                                            'value' => $request['manual_login_status'] ? 1 : 0
                                        ]);
                                        
                                        Helpers::businessUpdateOrInsert(['key' => 'otp_login_status'], [
                                            'value' => $request['otp_login_status'] ? 1 : 0
                                        ]);
                                        
                                        Helpers::businessUpdateOrInsert(['key' => 'social_login_status'], [
                                            'value' => $request['social_login_status'] ? 1 : 0
                                        ]);
                                        
                                        Helpers::businessUpdateOrInsert(['key' => 'google_login_status'], [
                                            'value' => $request['social_login_status']?($request['google_login_status'] ? 1 : 0):0
                                        ]);
                                        
                                        Helpers::businessUpdateOrInsert(['key' => 'facebook_login_status'], [
                                            'value' => $request['social_login_status']?($request['facebook_login_status'] ? 1 : 0):0
                                        ]);
                                        
                                        Helpers::businessUpdateOrInsert(['key' => 'apple_login_status'], [
                                            'value' => $request['social_login_status']?($request['apple_login_status'] ? 1 : 0):0
                                        ]);
                                        
                                        Helpers::businessUpdateOrInsert(['key' => 'email_verification_status'], [
                                            'value' => $request['email_verification_status'] ? 1 : 0
                                        ]);
                                        
                                        Helpers::businessUpdateOrInsert(['key' => 'phone_verification_status'], [
                                            'value' => $request['phone_verification_status'] ? 1 : 0
                                        ]);
                                        
                                        Toastr::success(translate('messages.login_settings_data_updated_successfully'));
                                        return back();
                                    }
                                    
                                    //recaptcha
                                    public function recaptcha_index(Request $request)
                                    {
                                        return view('admin-views.business-settings.recaptcha-index');
                                    }
                                    
                                    public function recaptcha_update(Request $request)
                                    {
                                        Helpers::businessUpdateOrInsert(['key' => 'recaptcha'], [
                                            'key' => 'recaptcha',
                                            'value' => json_encode([
                                                'status' => $request['status'],
                                                'site_key' => $request['site_key'],
                                                'secret_key' => $request['secret_key']
                                            ]),
                                            'created_at' => now(),
                                            'updated_at' => now(),
                                        ]);
                                        
                                        Toastr::success(translate('messages.updated_successfully'));
                                        return back();
                                    }
                                    
                                    //recaptcha
                                    
                                    public function firebase_otp_index(Request $request)
                                    {
                                        $is_sms_active= Setting::whereJsonContains('live_values->status','1')->where('settings_type', 'sms_config')
                                        ->exists();
                                        $is_mail_active= config('mail.status');
                                        return view('admin-views.business-settings.firebase-otp-index',compact('is_sms_active','is_mail_active'));
                                    }
                                    
                                    public function firebase_otp_update(Request $request)
                                    {
                                        $login_setup_status = Helpers::get_business_settings('otp_login_status')??0;
                                        $phone_verification_status = Helpers::get_business_settings('phone_verification_status')??0;
                                        $is_sms_active= Setting::whereJsonContains('live_values->status','1')->where('settings_type', 'sms_config')
                                        ->exists();
                                        if(!$is_sms_active && $login_setup_status && ($request['firebase_otp_verification']==0)){
                                            Toastr::warning(translate('otp_login_status_is_enabled_in_login_setup._First_disable_from_login_setup.'));
                                            return redirect()->back();
                                        }
                                        if(!$is_sms_active && $phone_verification_status && ($request['firebase_otp_verification']==0)){
                                            Toastr::warning(translate('phone_verification_status_is_enabled_in_login_setup._First_disable_from_login_setup.'));
                                            return redirect()->back();
                                        }
                                        Helpers::businessUpdateOrInsert(['key' => 'firebase_otp_verification'], [
                                            'value' => $request['firebase_otp_verification'] ?? 0
                                        ]);
                                        Helpers::businessUpdateOrInsert(['key' => 'firebase_web_api_key'], [
                                            'value' => $request['firebase_web_api_key']
                                        ]);
                                        
                                        Toastr::success(translate('messages.updated_successfully'));
                                        return back();
                                    }
                                    public function storage_connection_index(Request $request)
                                    {
                                        return view('admin-views.business-settings.storage-connection-index');
                                    }
                                    
                                    public function storage_connection_update(Request $request, $name)
                                    {
                                        if ($name == 'local_storage') {
                                            Helpers::businessUpdateOrInsert(['key' => 'local_storage'], [
                                                'key' => 'local_storage',
                                                'value' => $request->status ?? 0,
                                                'created_at' => now(),
                                                'updated_at' => now(),
                                            ]);
                                            Helpers::businessUpdateOrInsert(['key' => '3rd_party_storage'], [
                                                'key' => '3rd_party_storage',
                                                'value' => $request->status == '1' ? 0 : 1,
                                                'created_at' => now(),
                                                'updated_at' => now(),
                                            ]);
                                        }
                                        if ($name == '3rd_party_storage') {
                                            Helpers::businessUpdateOrInsert(['key' => '3rd_party_storage'], [
                                                'key' => '3rd_party_storage',
                                                'value' => $request->status ?? 0,
                                                'created_at' => now(),
                                                'updated_at' => now(),
                                            ]);
                                            Helpers::businessUpdateOrInsert(['key' => 'local_storage'], [
                                                'key' => 'local_storage',
                                                'value' => $request->status == '1' ? 0 : 1,
                                                'created_at' => now(),
                                                'updated_at' => now(),
                                            ]);
                                        }
                                        if ($name == 'storage_connection') {
                                            Helpers::businessUpdateOrInsert(['key' => 's3_credential'], [
                                                'key' => 's3_credential',
                                                'value' => json_encode([
                                                    'key' => $request['key'],
                                                    'secret' => $request['secret'],
                                                    'region' => $request['region'],
                                                    'bucket' => $request['bucket'],
                                                    'url' => $request['url'],
                                                    'end_point' => $request['end_point']
                                                ]),
                                                'created_at' => now(),
                                                'updated_at' => now(),
                                            ]);
                                        }
                                        
                                        //        $credentials=\App\CentralLogics\Helpers::get_business_settings('s3_credential');
                                        //        $config=\App\CentralLogics\Helpers::get_business_data('local_storage');
                                        //
                                        //        $s3Credentials = [
                                        //            'FILESYSTEM_DRIVER' => isset($config)?($config==0?'s3':'local'):'local',
                                        //            'AWS_ACCESS_KEY_ID' => $credentials['key'],
                                        //            'AWS_SECRET_ACCESS_KEY' => $credentials['secret'],
                                        //            'AWS_DEFAULT_REGION' => $credentials['region'],
                                        //            'AWS_BUCKET' => $credentials['bucket'],
                                        //            'AWS_URL' => $credentials['url'],
                                        //            'AWS_ENDPOINT' => $credentials['end_point']
                                        //        ];
                                        
                                        //        // Load existing environment file into an array
                                        //        $envFile = file(base_path('.env'), FILE_IGNORE_NEW_LINES);
                                        //        $data = [];
                                        //        foreach ($envFile as $line) {
                                        //            if (!empty(trim($line))) {
                                        //                list($key, $value) = explode('=', $line, 2);
                                        //                $data[$key] = $value;
                                        //            } else {
                                        //                // Preserve empty lines
                                        //                $data[] = '';
                                        //            }
                                        //        }
                                        //
                                        //        // Update existing keys
                                        //        foreach ($s3Credentials as $key => $value) {
                                        //            if (isset($data[$key])) {
                                        //                // Update the value
                                        //                $data[$key] = $value;
                                        //            }
                                        //        }
                                        //
                                        //        // Append any new keys that were not present in the original file
                                        //        foreach ($s3Credentials as $key => $value) {
                                        //            if (!isset($data[$key])) {
                                        //                $data[$key] = $value;
                                        //            }
                                        //        }
                                        //
                                        //        // Write the updated environment file
                                        //        $lines = [];
                                        //        foreach ($data as $key => $value) {
                                        //            if (is_numeric($key)) {
                                        //                // Preserve empty lines
                                        //                $lines[] = '';
                                        //            } else {
                                        //                $lines[] = $key . '=' . $value;
                                        //            }
                                        //        }
                                        //
                                        //        file_put_contents(base_path('.env'), implode(PHP_EOL, $lines) . PHP_EOL);
                                        
                                        
                                        Toastr::success(translate('messages.updated_successfully'));
                                        return back();
                                    }
                                    
                                    //Send Mail
                                    public function send_mail(Request $request)
                                    {
                                        if (env('APP_MODE') == 'demo') {
                                            Toastr::info(translate('messages.update_option_is_disable_for_demo'));
                                            return back();
                                        }
                                        $response_flag = 0;
                                        try {
                                            Mail::to($request->email)->send(new \App\Mail\TestEmailSender());
                                            $response_flag = 1;
                                        } catch (\Exception $exception) {
                                            info($exception->getMessage());
                                            $response_flag = 2;
                                        }
                                        
                                        return response()->json(['success' => $response_flag]);
                                    }
                                    
                                    
                                    public function site_direction(Request $request)
                                    {
                                        if (env('APP_MODE') == 'demo') {
                                            session()->put('site_direction', ($request->status == 1 ? 'ltr' : 'rtl'));
                                            return response()->json();
                                        }
                                        if ($request->status == 1) {
                                            Helpers::businessUpdateOrInsert(['key' => 'site_direction'], [
                                                'value' => 'ltr'
                                            ]);
                                        } else {
                                            Helpers::businessUpdateOrInsert(['key' => 'site_direction'], [
                                                'value' => 'rtl'
                                            ]);
                                        }
                                        return;
                                    }
                                    
                                    public function admin_landing_page_settings($tab)
                                    {
                                        if ($tab == 'fixed-data') {
                                            return view('admin-views.business-settings.landing-page-settings.admin-fixed-data');
                                        } else if ($tab == 'promotional-section') {
                                            return view('admin-views.business-settings.landing-page-settings.admin-promotional-section');
                                        } else if ($tab == 'feature-list') {
                                            return view('admin-views.business-settings.landing-page-settings.admin-feature-list');
                                        } else if ($tab == 'earn-money') {
                                            return view('admin-views.business-settings.landing-page-settings.admin-earn-money');
                                        } else if ($tab == 'why-choose-us') {
                                            return view('admin-views.business-settings.landing-page-settings.admin-landing-why-choose');
                                        } else if ($tab == 'available-zone') {
                                            return view('admin-views.business-settings.landing-page-settings.admin-landing-available-zone');
                                        } else if ($tab == 'download-apps') {
                                            return view('admin-views.business-settings.landing-page-settings.admin-landing-download-apps');
                                        } else if ($tab == 'testimonials') {
                                            return view('admin-views.business-settings.landing-page-settings.admin-landing-testimonial');
                                        } else if ($tab == 'contact-us') {
                                            return view('admin-views.business-settings.landing-page-settings.admin-landing-contact');
                                        } else if ($tab == 'background-color') {
                                            return view('admin-views.business-settings.landing-page-settings.admin-landing-background-color');
                                        }
                                    }
                                    
                                    public function update_admin_landing_page_settings(Request $request, $tab)
                                    {
                                        if (env('APP_MODE') == 'demo') {
                                            Toastr::info(translate('messages.update_option_is_disable_for_demo'));
                                            return back();
                                        }
                                        
                                        if ($tab == 'fixed-data') {
                                            $fixed_header_title = DataSetting::where('type', 'admin_landing_page')->where('key', 'fixed_header_title')->first();
                                            if ($fixed_header_title == null) {
                                                $fixed_header_title = new DataSetting();
                                            }
                                            
                                            $fixed_header_title->key = 'fixed_header_title';
                                            $fixed_header_title->type = 'admin_landing_page';
                                            $fixed_header_title->value = $request->fixed_header_title[array_search('default', $request->lang)];
                                            $fixed_header_title->save();
                                            
                                            $fixed_header_sub_title = DataSetting::where('type', 'admin_landing_page')->where('key', 'fixed_header_sub_title')->first();
                                            if ($fixed_header_sub_title == null) {
                                                $fixed_header_sub_title = new DataSetting();
                                            }
                                            
                                            $fixed_header_sub_title->key = 'fixed_header_sub_title';
                                            $fixed_header_sub_title->type = 'admin_landing_page';
                                            $fixed_header_sub_title->value = $request->fixed_header_sub_title[array_search('default', $request->lang)];
                                            $fixed_header_sub_title->save();
                                            
                                            $fixed_module_title = DataSetting::where('type', 'admin_landing_page')->where('key', 'fixed_module_title')->first();
                                            if ($fixed_module_title == null) {
                                                $fixed_module_title = new DataSetting();
                                            }
                                            
                                            $fixed_module_title->key = 'fixed_module_title';
                                            $fixed_module_title->type = 'admin_landing_page';
                                            $fixed_module_title->value = $request->fixed_module_title[array_search('default', $request->lang)];
                                            $fixed_module_title->save();
                                            
                                            $fixed_module_sub_title = DataSetting::where('type', 'admin_landing_page')->where('key', 'fixed_module_sub_title')->first();
                                            if ($fixed_module_sub_title == null) {
                                                $fixed_module_sub_title = new DataSetting();
                                            }
                                            
                                            $fixed_module_sub_title->key = 'fixed_module_sub_title';
                                            $fixed_module_sub_title->type = 'admin_landing_page';
                                            $fixed_module_sub_title->value = $request->fixed_module_sub_title[array_search('default', $request->lang)];
                                            $fixed_module_sub_title->save();
                                            
                                            $fixed_referal_title = DataSetting::where('type', 'admin_landing_page')->where('key', 'fixed_referal_title')->first();
                                            if ($fixed_referal_title == null) {
                                                $fixed_referal_title = new DataSetting();
                                            }
                                            
                                            $fixed_referal_title->key = 'fixed_referal_title';
                                            $fixed_referal_title->type = 'admin_landing_page';
                                            $fixed_referal_title->value = $request->fixed_referal_title[array_search('default', $request->lang)];
                                            $fixed_referal_title->save();
                                            
                                            $fixed_referal_sub_title = DataSetting::where('type', 'admin_landing_page')->where('key', 'fixed_referal_sub_title')->first();
                                            if ($fixed_referal_sub_title == null) {
                                                $fixed_referal_sub_title = new DataSetting();
                                            }
                                            
                                            $fixed_referal_sub_title->key = 'fixed_referal_sub_title';
                                            $fixed_referal_sub_title->type = 'admin_landing_page';
                                            $fixed_referal_sub_title->value = $request->fixed_referal_sub_title[array_search('default', $request->lang)];
                                            $fixed_referal_sub_title->save();
                                            
                                            $fixed_newsletter_title = DataSetting::where('type', 'admin_landing_page')->where('key', 'fixed_newsletter_title')->first();
                                            if ($fixed_newsletter_title == null) {
                                                $fixed_newsletter_title = new DataSetting();
                                            }
                                            
                                            $fixed_newsletter_title->key = 'fixed_newsletter_title';
                                            $fixed_newsletter_title->type = 'admin_landing_page';
                                            $fixed_newsletter_title->value = $request->fixed_newsletter_title[array_search('default', $request->lang)];
                                            $fixed_newsletter_title->save();
                                            
                                            $fixed_newsletter_sub_title = DataSetting::where('type', 'admin_landing_page')->where('key', 'fixed_newsletter_sub_title')->first();
                                            if ($fixed_newsletter_sub_title == null) {
                                                $fixed_newsletter_sub_title = new DataSetting();
                                            }
                                            
                                            $fixed_newsletter_sub_title->key = 'fixed_newsletter_sub_title';
                                            $fixed_newsletter_sub_title->type = 'admin_landing_page';
                                            $fixed_newsletter_sub_title->value = $request->fixed_newsletter_sub_title[array_search('default', $request->lang)];
                                            $fixed_newsletter_sub_title->save();
                                            
                                            $fixed_footer_article_title = DataSetting::where('type', 'admin_landing_page')->where('key', 'fixed_footer_article_title')->first();
                                            if ($fixed_footer_article_title == null) {
                                                $fixed_footer_article_title = new DataSetting();
                                            }
                                            
                                            $fixed_footer_article_title->key = 'fixed_footer_article_title';
                                            $fixed_footer_article_title->type = 'admin_landing_page';
                                            $fixed_footer_article_title->value = $request->fixed_footer_article_title[array_search('default', $request->lang)];
                                            $fixed_footer_article_title->save();
                                            // dd($fixed_module_sub_title?->getRawOriginal('value'));
                                            
                                            $default_lang = str_replace('_', '-', app()->getLocale());
                                            foreach ($request->lang as $index => $key) {
                                                if ($default_lang == $key && !($request->fixed_header_title[$index])) {
                                                    if ($key != 'default') {
                                                        Translation::updateOrInsert(
                                                            [
                                                                'translationable_type' => 'App\Models\DataSetting',
                                                                'translationable_id' => $fixed_header_title->id,
                                                                'locale' => $key,
                                                                'key' => 'fixed_header_title'
                                                            ],
                                                            ['value' => $fixed_header_title?->getRawOriginal('value')]
                                                        );
                                                    }
                                                } else {
                                                    if ($request->fixed_header_title[$index] && $key != 'default') {
                                                        Translation::updateOrInsert(
                                                            [
                                                                'translationable_type' => 'App\Models\DataSetting',
                                                                'translationable_id' => $fixed_header_title->id,
                                                                'locale' => $key,
                                                                'key' => 'fixed_header_title'
                                                            ],
                                                            ['value' => $request->fixed_header_title[$index]]
                                                        );
                                                    }
                                                }
                                                if ($default_lang == $key && !($request->fixed_header_sub_title[$index])) {
                                                    if ($key != 'default') {
                                                        Translation::updateOrInsert(
                                                            [
                                                                'translationable_type' => 'App\Models\DataSetting',
                                                                'translationable_id' => $fixed_header_sub_title->id,
                                                                'locale' => $key,
                                                                'key' => 'fixed_header_sub_title'
                                                            ],
                                                            ['value' => $fixed_header_sub_title?->getRawOriginal('value')]
                                                        );
                                                    }
                                                } else {
                                                    if ($request->fixed_header_sub_title[$index] && $key != 'default') {
                                                        Translation::updateOrInsert(
                                                            [
                                                                'translationable_type' => 'App\Models\DataSetting',
                                                                'translationable_id' => $fixed_header_sub_title->id,
                                                                'locale' => $key,
                                                                'key' => 'fixed_header_sub_title'
                                                            ],
                                                            ['value' => $request->fixed_header_sub_title[$index]]
                                                        );
                                                    }
                                                }
                                                if ($default_lang == $key && !($request->fixed_module_title[$index])) {
                                                    if ($key != 'default') {
                                                        Translation::updateOrInsert(
                                                            [
                                                                'translationable_type' => 'App\Models\DataSetting',
                                                                'translationable_id' => $fixed_module_title->id,
                                                                'locale' => $key,
                                                                'key' => 'fixed_module_title'
                                                            ],
                                                            ['value' => $fixed_module_title?->getRawOriginal('value')]
                                                        );
                                                    }
                                                } else {
                                                    if ($request->fixed_module_title[$index] && $key != 'default') {
                                                        Translation::updateOrInsert(
                                                            [
                                                                'translationable_type' => 'App\Models\DataSetting',
                                                                'translationable_id' => $fixed_module_title->id,
                                                                'locale' => $key,
                                                                'key' => 'fixed_module_title'
                                                            ],
                                                            ['value' => $request->fixed_module_title[$index]]
                                                        );
                                                    }
                                                }
                                                if ($default_lang == $key && !($request->fixed_module_sub_title[$index])) {
                                                    if ($key != 'default') {
                                                        Translation::updateOrInsert(
                                                            [
                                                                'translationable_type' => 'App\Models\DataSetting',
                                                                'translationable_id' => $fixed_module_sub_title->id,
                                                                'locale' => $key,
                                                                'key' => 'fixed_module_sub_title'
                                                            ],
                                                            ['value' => $fixed_module_sub_title?->getRawOriginal('value')]
                                                        );
                                                    }
                                                } else {
                                                    if ($request->fixed_module_sub_title[$index] && $key != 'default') {
                                                        Translation::updateOrInsert(
                                                            [
                                                                'translationable_type' => 'App\Models\DataSetting',
                                                                'translationable_id' => $fixed_module_sub_title->id,
                                                                'locale' => $key,
                                                                'key' => 'fixed_module_sub_title'
                                                            ],
                                                            ['value' => $request->fixed_module_sub_title[$index]]
                                                        );
                                                    }
                                                }
                                                if ($default_lang == $key && !($request->fixed_referal_title[$index])) {
                                                    if ($key != 'default') {
                                                        Translation::updateOrInsert(
                                                            [
                                                                'translationable_type' => 'App\Models\DataSetting',
                                                                'translationable_id' => $fixed_referal_title->id,
                                                                'locale' => $key,
                                                                'key' => 'fixed_referal_title'
                                                            ],
                                                            ['value' => $fixed_referal_title?->getRawOriginal('value')]
                                                        );
                                                    }
                                                } else {
                                                    if ($request->fixed_referal_title[$index] && $key != 'default') {
                                                        Translation::updateOrInsert(
                                                            [
                                                                'translationable_type' => 'App\Models\DataSetting',
                                                                'translationable_id' => $fixed_referal_title->id,
                                                                'locale' => $key,
                                                                'key' => 'fixed_referal_title'
                                                            ],
                                                            ['value' => $request->fixed_referal_title[$index]]
                                                        );
                                                    }
                                                }
                                                if ($default_lang == $key && !($request->fixed_referal_sub_title[$index])) {
                                                    if ($key != 'default') {
                                                        Translation::updateOrInsert(
                                                            [
                                                                'translationable_type' => 'App\Models\DataSetting',
                                                                'translationable_id' => $fixed_referal_sub_title->id,
                                                                'locale' => $key,
                                                                'key' => 'fixed_referal_sub_title'
                                                            ],
                                                            ['value' => $fixed_referal_sub_title?->getRawOriginal('value')]
                                                        );
                                                    }
                                                } else {
                                                    if ($request->fixed_referal_sub_title[$index] && $key != 'default') {
                                                        Translation::updateOrInsert(
                                                            [
                                                                'translationable_type' => 'App\Models\DataSetting',
                                                                'translationable_id' => $fixed_referal_sub_title->id,
                                                                'locale' => $key,
                                                                'key' => 'fixed_referal_sub_title'
                                                            ],
                                                            ['value' => $request->fixed_referal_sub_title[$index]]
                                                        );
                                                    }
                                                }
                                                if ($default_lang == $key && !($request->fixed_newsletter_title[$index])) {
                                                    if ($key != 'default') {
                                                        Translation::updateOrInsert(
                                                            [
                                                                'translationable_type' => 'App\Models\DataSetting',
                                                                'translationable_id' => $fixed_newsletter_title->id,
                                                                'locale' => $key,
                                                                'key' => 'fixed_newsletter_title'
                                                            ],
                                                            ['value' => $fixed_newsletter_title?->getRawOriginal('value')]
                                                        );
                                                    }
                                                } else {
                                                    if ($request->fixed_newsletter_title[$index] && $key != 'default') {
                                                        Translation::updateOrInsert(
                                                            [
                                                                'translationable_type' => 'App\Models\DataSetting',
                                                                'translationable_id' => $fixed_newsletter_title->id,
                                                                'locale' => $key,
                                                                'key' => 'fixed_newsletter_title'
                                                            ],
                                                            ['value' => $request->fixed_newsletter_title[$index]]
                                                        );
                                                    }
                                                }
                                                if ($default_lang == $key && !($request->fixed_newsletter_sub_title[$index])) {
                                                    if ($key != 'default') {
                                                        Translation::updateOrInsert(
                                                            [
                                                                'translationable_type' => 'App\Models\DataSetting',
                                                                'translationable_id' => $fixed_newsletter_sub_title->id,
                                                                'locale' => $key,
                                                                'key' => 'fixed_newsletter_sub_title'
                                                            ],
                                                            ['value' => $fixed_newsletter_sub_title?->getRawOriginal('value')]
                                                        );
                                                    }
                                                } else {
                                                    if ($request->fixed_newsletter_sub_title[$index] && $key != 'default') {
                                                        Translation::updateOrInsert(
                                                            [
                                                                'translationable_type' => 'App\Models\DataSetting',
                                                                'translationable_id' => $fixed_newsletter_sub_title->id,
                                                                'locale' => $key,
                                                                'key' => 'fixed_newsletter_sub_title'
                                                            ],
                                                            ['value' => $request->fixed_newsletter_sub_title[$index]]
                                                        );
                                                    }
                                                }
                                                if ($default_lang == $key && !($request->fixed_footer_article_title[$index])) {
                                                    if ($key != 'default') {
                                                        Translation::updateOrInsert(
                                                            [
                                                                'translationable_type' => 'App\Models\DataSetting',
                                                                'translationable_id' => $fixed_footer_article_title->id,
                                                                'locale' => $key,
                                                                'key' => 'fixed_footer_article_title'
                                                            ],
                                                            ['value' => $fixed_footer_article_title?->getRawOriginal('value')]
                                                        );
                                                    }
                                                } else {
                                                    if ($request->fixed_footer_article_title[$index] && $key != 'default') {
                                                        Translation::updateOrInsert(
                                                            [
                                                                'translationable_type' => 'App\Models\DataSetting',
                                                                'translationable_id' => $fixed_footer_article_title->id,
                                                                'locale' => $key,
                                                                'key' => 'fixed_footer_article_title'
                                                            ],
                                                            ['value' => $request->fixed_footer_article_title[$index]]
                                                        );
                                                    }
                                                }
                                            }
                                            
                                            Helpers::dataUpdateOrInsert(['key' => 'fixed_link', 'type' => 'admin_landing_page'], [
                                                'value' => json_encode([
                                                    'web_app_url_status' => $request['web_app_url_status'],
                                                    'web_app_url' => $request['web_app_url'],
                                                    ])
                                                ]);
                                                Toastr::success(translate('messages.landing_page_text_updated'));
                                            } elseif ($tab == 'promotional-section') {
                                                $request->validate([
                                                    'title' => 'required',
                                                    'sub_title' => 'required',
                                                    'image' => 'required',
                                                ]);
                                                if ($request->title[array_search('default', $request->lang)] == '') {
                                                    Toastr::error(translate('default_data_is_required'));
                                                    return back();
                                                }
                                                $banner = new AdminPromotionalBanner();
                                                $banner->title = $request->title[array_search('default', $request->lang)];
                                                $banner->sub_title = $request->sub_title[array_search('default', $request->lang)];
                                                $banner->image = Helpers::upload('promotional_banner/', 'png', $request->file('image'));
                                                $banner->save();
                                                $default_lang = str_replace('_', '-', app()->getLocale());
                                                $data = [];
                                                foreach ($request->lang as $index => $key) {
                                                    if ($default_lang == $key && !($request->title[$index])) {
                                                        if ($key != 'default') {
                                                            Translation::updateOrInsert(
                                                                [
                                                                    'translationable_type' => 'App\Models\AdminPromotionalBanner',
                                                                    'translationable_id' => $banner->id,
                                                                    'locale' => $key,
                                                                    'key' => 'title'
                                                                ],
                                                                ['value' => $banner->title]
                                                            );
                                                        }
                                                    } else {
                                                        
                                                        if ($request->title[$index] && $key != 'default') {
                                                            Translation::updateOrInsert(
                                                                [
                                                                    'translationable_type' => 'App\Models\AdminPromotionalBanner',
                                                                    'translationable_id' => $banner->id,
                                                                    'locale' => $key,
                                                                    'key' => 'title'
                                                                ],
                                                                ['value' => $request->title[$index]]
                                                            );
                                                        }
                                                    }
                                                    if ($default_lang == $key && !($request->sub_title[$index])) {
                                                        if ($key != 'default') {
                                                            Translation::updateOrInsert(
                                                                [
                                                                    'translationable_type' => 'App\Models\AdminPromotionalBanner',
                                                                    'translationable_id' => $banner->id,
                                                                    'locale' => $key,
                                                                    'key' => 'sub_title'
                                                                ],
                                                                ['value' => $banner->sub_title]
                                                            );
                                                        }
                                                    } else {
                                                        
                                                        if ($request->sub_title[$index] && $key != 'default') {
                                                            Translation::updateOrInsert(
                                                                [
                                                                    'translationable_type' => 'App\Models\AdminPromotionalBanner',
                                                                    'translationable_id' => $banner->id,
                                                                    'locale' => $key,
                                                                    'key' => 'sub_title'
                                                                ],
                                                                ['value' => $request->sub_title[$index]]
                                                            );
                                                        }
                                                    }
                                                }
                                                
                                                Toastr::success(translate('messages.banner_added_successfully'));
                                                return back();
                                            } elseif ($tab == 'feature-list') {
                                                $request->validate([
                                                    'title' => 'required',
                                                    'sub_title' => 'required',
                                                    'image' => 'required',
                                                ]);
                                                if ($request->title[array_search('default', $request->lang)] == '') {
                                                    Toastr::error(translate('default_data_is_required'));
                                                    return back();
                                                }
                                                $feature = new AdminFeature();
                                                $feature->title = $request->title[array_search('default', $request->lang)];
                                                $feature->sub_title = $request->sub_title[array_search('default', $request->lang)];
                                                $feature->image = Helpers::upload('admin_feature/', 'png', $request->file('image'));
                                                $feature->save();
                                                $default_lang = str_replace('_', '-', app()->getLocale());
                                                $data = [];
                                                foreach ($request->lang as $index => $key) {
                                                    if ($default_lang == $key && !($request->title[$index])) {
                                                        if ($key != 'default') {
                                                            Translation::updateOrInsert(
                                                                [
                                                                    'translationable_type' => 'App\Models\AdminFeature',
                                                                    'translationable_id' => $feature->id,
                                                                    'locale' => $key,
                                                                    'key' => 'title'
                                                                ],
                                                                ['value' => $feature->title]
                                                            );
                                                        }
                                                    } else {
                                                        
                                                        if ($request->title[$index] && $key != 'default') {
                                                            Translation::updateOrInsert(
                                                                [
                                                                    'translationable_type' => 'App\Models\AdminFeature',
                                                                    'translationable_id' => $feature->id,
                                                                    'locale' => $key,
                                                                    'key' => 'title'
                                                                ],
                                                                ['value' => $request->title[$index]]
                                                            );
                                                        }
                                                    }
                                                    if ($default_lang == $key && !($request->sub_title[$index])) {
                                                        if ($key != 'default') {
                                                            Translation::updateOrInsert(
                                                                [
                                                                    'translationable_type' => 'App\Models\AdminFeature',
                                                                    'translationable_id' => $feature->id,
                                                                    'locale' => $key,
                                                                    'key' => 'sub_title'
                                                                ],
                                                                ['value' => $feature->sub_title]
                                                            );
                                                        }
                                                    } else {
                                                        
                                                        if ($request->sub_title[$index] && $key != 'default') {
                                                            Translation::updateOrInsert(
                                                                [
                                                                    'translationable_type' => 'App\Models\AdminFeature',
                                                                    'translationable_id' => $feature->id,
                                                                    'locale' => $key,
                                                                    'key' => 'sub_title'
                                                                ],
                                                                ['value' => $request->sub_title[$index]]
                                                            );
                                                        }
                                                    }
                                                }
                                                
                                                Toastr::success(translate('messages.feature_added_successfully'));
                                            } elseif ($tab == 'feature-title') {
                                                $feature_title = DataSetting::where('type', 'admin_landing_page')->where('key', 'feature_title')->first();
                                                if ($feature_title == null) {
                                                    $feature_title = new DataSetting();
                                                }
                                                
                                                $feature_title->key = 'feature_title';
                                                $feature_title->type = 'admin_landing_page';
                                                $feature_title->value = $request->feature_title[array_search('default', $request->lang)];
                                                $feature_title->save();
                                                
                                                $feature_short_description = DataSetting::where('type', 'admin_landing_page')->where('key', 'feature_short_description')->first();
                                                if ($feature_short_description == null) {
                                                    $feature_short_description = new DataSetting();
                                                }
                                                
                                                $feature_short_description->key = 'feature_short_description';
                                                $feature_short_description->type = 'admin_landing_page';
                                                $feature_short_description->value = $request->feature_short_description[array_search('default', $request->lang)];
                                                $feature_short_description->save();
                                                
                                                
                                                $data = [];
                                                $default_lang = str_replace('_', '-', app()->getLocale());
                                                foreach ($request->lang as $index => $key) {
                                                    if ($default_lang == $key && !($request->feature_title[$index])) {
                                                        if ($key != 'default') {
                                                            Translation::updateOrInsert(
                                                                [
                                                                    'translationable_type' => 'App\Models\DataSetting',
                                                                    'translationable_id' => $feature_title->id,
                                                                    'locale' => $key,
                                                                    'key' => 'feature_title'
                                                                ],
                                                                ['value' => $feature_title?->getRawOriginal('value')]
                                                            );
                                                        }
                                                    } else {
                                                        if ($request->feature_title[$index] && $key != 'default') {
                                                            Translation::updateOrInsert(
                                                                [
                                                                    'translationable_type' => 'App\Models\DataSetting',
                                                                    'translationable_id' => $feature_title->id,
                                                                    'locale' => $key,
                                                                    'key' => 'feature_title'
                                                                ],
                                                                ['value' => $request->feature_title[$index]]
                                                            );
                                                        }
                                                    }
                                                    if ($default_lang == $key && !($request->feature_short_description[$index])) {
                                                        if ($key != 'default') {
                                                            Translation::updateOrInsert(
                                                                [
                                                                    'translationable_type' => 'App\Models\DataSetting',
                                                                    'translationable_id' => $feature_short_description->id,
                                                                    'locale' => $key,
                                                                    'key' => 'feature_short_description'
                                                                ],
                                                                ['value' => $feature_short_description->getRawOriginal('value')]
                                                            );
                                                        }
                                                    } else {
                                                        if ($request->feature_short_description[$index] && $key != 'default') {
                                                            Translation::updateOrInsert(
                                                                [
                                                                    'translationable_type' => 'App\Models\DataSetting',
                                                                    'translationable_id' => $feature_short_description->id,
                                                                    'locale' => $key,
                                                                    'key' => 'feature_short_description'
                                                                ],
                                                                ['value' => $request->feature_short_description[$index]]
                                                            );
                                                        }
                                                    }
                                                }
                                                
                                                Toastr::success(translate('messages.feature_section_updated'));
                                            } elseif ($tab == 'earning-title') {
                                                $earning_title = DataSetting::where('type', 'admin_landing_page')->where('key', 'earning_title')->first();
                                                if ($earning_title == null) {
                                                    $earning_title = new DataSetting();
                                                }
                                                
                                                $earning_title->key = 'earning_title';
                                                $earning_title->type = 'admin_landing_page';
                                                $earning_title->value = $request->earning_title[array_search('default', $request->lang)];
                                                $earning_title->save();
                                                
                                                $earning_sub_title = DataSetting::where('type', 'admin_landing_page')->where('key', 'earning_sub_title')->first();
                                                if ($earning_sub_title == null) {
                                                    $earning_sub_title = new DataSetting();
                                                }
                                                
                                                $earning_sub_title->key = 'earning_sub_title';
                                                $earning_sub_title->type = 'admin_landing_page';
                                                $earning_sub_title->value = $request->earning_sub_title[array_search('default', $request->lang)];
                                                $earning_sub_title->save();
                                                
                                                
                                                $data = [];
                                                $default_lang = str_replace('_', '-', app()->getLocale());
                                                foreach ($request->lang as $index => $key) {
                                                    if ($default_lang == $key && !($request->earning_title[$index])) {
                                                        if ($key != 'default') {
                                                            Translation::updateOrInsert(
                                                                [
                                                                    'translationable_type' => 'App\Models\DataSetting',
                                                                    'translationable_id' => $earning_title->id,
                                                                    'locale' => $key,
                                                                    'key' => 'earning_title'
                                                                ],
                                                                ['value' => $earning_title?->getRawOriginal('value')]
                                                            );
                                                        }
                                                    } else {
                                                        if ($request->earning_title[$index] && $key != 'default') {
                                                            Translation::updateOrInsert(
                                                                [
                                                                    'translationable_type' => 'App\Models\DataSetting',
                                                                    'translationable_id' => $earning_title->id,
                                                                    'locale' => $key,
                                                                    'key' => 'earning_title'
                                                                ],
                                                                ['value' => $request->earning_title[$index]]
                                                            );
                                                        }
                                                    }
                                                    if ($default_lang == $key && !($request->earning_sub_title[$index])) {
                                                        if ($key != 'default') {
                                                            Translation::updateOrInsert(
                                                                [
                                                                    'translationable_type' => 'App\Models\DataSetting',
                                                                    'translationable_id' => $earning_sub_title->id,
                                                                    'locale' => $key,
                                                                    'key' => 'earning_sub_title'
                                                                ],
                                                                ['value' => $earning_sub_title?->getRawOriginal('value')]
                                                            );
                                                        }
                                                    } else {
                                                        if ($request->earning_sub_title[$index] && $key != 'default') {
                                                            Translation::updateOrInsert(
                                                                [
                                                                    'translationable_type' => 'App\Models\DataSetting',
                                                                    'translationable_id' => $earning_sub_title->id,
                                                                    'locale' => $key,
                                                                    'key' => 'earning_sub_title'
                                                                ],
                                                                ['value' => $request->earning_sub_title[$index]]
                                                            );
                                                        }
                                                    }
                                                }
                                                
                                                Toastr::success(translate('messages.earning_section_updated'));
                                            } elseif ($tab == 'earning-seller-link') {
                                                $earning_seller_image = DataSetting::where('type', 'admin_landing_page')->where('key', 'earning_seller_image')->first();
                                                if ($earning_seller_image == null) {
                                                    $earning_seller_image = new DataSetting();
                                                }
                                                $earning_seller_image->key = 'earning_seller_image';
                                                $earning_seller_image->type = 'admin_landing_page';
                                                $earning_seller_image->value = $request->has('earning_seller_image') ? Helpers::update('earning/', $earning_seller_image->value, 'png', $request->file('earning_seller_image')) : $earning_seller_image->value;
                                                $earning_seller_image->save();
                                                
                                                
                                                if($request['playstore_url_status'] && !$request['playstore_url']){
                                                    Toastr::error(translate('messages.playstore download_url_is_empty'));
                                                    return back();
                                                }
                                                if($request['apple_store_url_status'] && !$request['apple_store_url']){
                                                    Toastr::error(translate('messages.App_store download_url_is_empty'));
                                                    return back();
                                                }
                                                
                                                
                                                Helpers::dataUpdateOrInsert(['key' => 'seller_app_earning_links', 'type' => 'admin_landing_page'], [
                                                    'value' => json_encode([
                                                        'playstore_url_status' => $request['playstore_url_status'],
                                                        'playstore_url' => $request['playstore_url'],
                                                        'apple_store_url_status' => $request['apple_store_url_status'],
                                                        'apple_store_url' => $request['apple_store_url']
                                                        ])
                                                    ]);
                                                    Toastr::success(translate('messages.seller_links_updated'));
                                                } elseif ($tab == 'earning-dm-link') {
                                                    $earning_delivery_image = DataSetting::where('type', 'admin_landing_page')->where('key', 'earning_delivery_image')->first();
                                                    if ($earning_delivery_image == null) {
                                                        $earning_delivery_image = new DataSetting();
                                                    }
                                                    $earning_delivery_image->key = 'earning_delivery_image';
                                                    $earning_delivery_image->type = 'admin_landing_page';
                                                    $earning_delivery_image->value = $request->has('earning_delivery_image') ? Helpers::update('earning/', $earning_delivery_image->value, 'png', $request->file('earning_delivery_image')) : $earning_delivery_image->value;
                                                    $earning_delivery_image->save();
                                                    
                                                    
                                                    if($request['playstore_url_status'] && !$request['playstore_url']){
                                                        Toastr::error(translate('messages.playstore download_url_is_empty'));
                                                        return back();
                                                    }
                                                    if($request['apple_store_url_status'] && !$request['apple_store_url']){
                                                        Toastr::error(translate('messages.App_store download_url_is_empty'));
                                                        return back();
                                                    }
                                                    
                                                    Helpers::dataUpdateOrInsert(['key' => 'dm_app_earning_links', 'type' => 'admin_landing_page'], [
                                                        'value' => json_encode([
                                                            'playstore_url_status' => $request['playstore_url_status'],
                                                            'playstore_url' => $request['playstore_url'],
                                                            'apple_store_url_status' => $request['apple_store_url_status'],
                                                            'apple_store_url' => $request['apple_store_url']
                                                            ])
                                                        ]);
                                                        Toastr::success(translate('messages.delivery_man_links_updated'));
                                                    } elseif ($tab == 'why-choose-title') {
                                                        $why_choose_title = DataSetting::where('type', 'admin_landing_page')->where('key', 'why_choose_title')->first();
                                                        if ($why_choose_title == null) {
                                                            $why_choose_title = new DataSetting();
                                                        }
                                                        
                                                        $why_choose_title->key = 'why_choose_title';
                                                        $why_choose_title->type = 'admin_landing_page';
                                                        $why_choose_title->value = $request->why_choose_title[array_search('default', $request->lang)];
                                                        $why_choose_title->save();
                                                        
                                                        $data = [];
                                                        $default_lang = str_replace('_', '-', app()->getLocale());
                                                        foreach ($request->lang as $index => $key) {
                                                            if ($default_lang == $key && !($request->why_choose_title[$index])) {
                                                                if ($key != 'default') {
                                                                    Translation::updateOrInsert(
                                                                        [
                                                                            'translationable_type' => 'App\Models\DataSetting',
                                                                            'translationable_id' => $why_choose_title->id,
                                                                            'locale' => $key,
                                                                            'key' => 'why_choose_title'
                                                                        ],
                                                                        ['value' => $why_choose_title?->getRawOriginal('value')]
                                                                    );
                                                                }
                                                            } else {
                                                                if ($request->why_choose_title[$index] && $key != 'default') {
                                                                    Translation::updateOrInsert(
                                                                        [
                                                                            'translationable_type' => 'App\Models\DataSetting',
                                                                            'translationable_id' => $why_choose_title->id,
                                                                            'locale' => $key,
                                                                            'key' => 'why_choose_title'
                                                                        ],
                                                                        ['value' => $request->why_choose_title[$index]]
                                                                    );
                                                                }
                                                            }
                                                        }
                                                        
                                                        Toastr::success(translate('messages.why_choose_section_updated'));
                                                    } elseif ($tab == 'special-criteria-list') {
                                                        $request->validate([
                                                            'title' => 'required',
                                                            'image' => 'required',
                                                        ]);
                                                        if ($request->title[array_search('default', $request->lang)] == '') {
                                                            Toastr::error(translate('default_data_is_required'));
                                                            return back();
                                                        }
                                                        $criteria = new AdminSpecialCriteria();
                                                        $criteria->title = $request->title[array_search('default', $request->lang)];
                                                        $criteria->image = Helpers::upload('special_criteria/', 'png', $request->file('image'));
                                                        $criteria->save();
                                                        $default_lang = str_replace('_', '-', app()->getLocale());
                                                        $data = [];
                                                        foreach ($request->lang as $index => $key) {
                                                            if ($default_lang == $key && !($request->title[$index])) {
                                                                if ($key != 'default') {
                                                                    Translation::updateOrInsert(
                                                                        [
                                                                            'translationable_type' => 'App\Models\AdminSpecialCriteria',
                                                                            'translationable_id' => $criteria->id,
                                                                            'locale' => $key,
                                                                            'key' => 'title'
                                                                        ],
                                                                        ['value' => $criteria->title]
                                                                    );
                                                                }
                                                            } else {
                                                                
                                                                if ($request->title[$index] && $key != 'default') {
                                                                    Translation::updateOrInsert(
                                                                        [
                                                                            'translationable_type' => 'App\Models\AdminSpecialCriteria',
                                                                            'translationable_id' => $criteria->id,
                                                                            'locale' => $key,
                                                                            'key' => 'title'
                                                                        ],
                                                                        ['value' => $request->title[$index]]
                                                                    );
                                                                }
                                                            }
                                                        }
                                                        
                                                        Toastr::success(translate('messages.criteria_added_successfully'));
                                                    } elseif ($tab == 'download-app-section') {
                                                        $download_user_app_title = DataSetting::where('type', 'admin_landing_page')->where('key', 'download_user_app_title')->first();
                                                        if ($download_user_app_title == null) {
                                                            $download_user_app_title = new DataSetting();
                                                        }
                                                        
                                                        $download_user_app_title->key = 'download_user_app_title';
                                                        $download_user_app_title->type = 'admin_landing_page';
                                                        $download_user_app_title->value = $request->download_user_app_title[array_search('default', $request->lang)];
                                                        $download_user_app_title->save();
                                                        
                                                        $download_user_app_sub_title = DataSetting::where('type', 'admin_landing_page')->where('key', 'download_user_app_sub_title')->first();
                                                        if ($download_user_app_sub_title == null) {
                                                            $download_user_app_sub_title = new DataSetting();
                                                        }
                                                        
                                                        $download_user_app_sub_title->key = 'download_user_app_sub_title';
                                                        $download_user_app_sub_title->type = 'admin_landing_page';
                                                        $download_user_app_sub_title->value = $request->download_user_app_sub_title[array_search('default', $request->lang)];
                                                        $download_user_app_sub_title->save();
                                                        
                                                        $download_user_app_image = DataSetting::where('type', 'admin_landing_page')->where('key', 'download_user_app_image')->first();
                                                        if ($download_user_app_image == null) {
                                                            $download_user_app_image = new DataSetting();
                                                        }
                                                        $download_user_app_image->key = 'download_user_app_image';
                                                        $download_user_app_image->type = 'admin_landing_page';
                                                        $download_user_app_image->value = $request->has('image') ? Helpers::update('download_user_app_image/', $download_user_app_image->value, 'png', $request->file('image')) : $download_user_app_image->value;
                                                        $download_user_app_image->save();
                                                        
                                                        $data = [];
                                                        $default_lang = str_replace('_', '-', app()->getLocale());
                                                        foreach ($request->lang as $index => $key) {
                                                            if ($default_lang == $key && !($request->download_user_app_title[$index])) {
                                                                if ($key != 'default') {
                                                                    Translation::updateOrInsert(
                                                                        [
                                                                            'translationable_type' => 'App\Models\DataSetting',
                                                                            'translationable_id' => $download_user_app_title->id,
                                                                            'locale' => $key,
                                                                            'key' => 'download_user_app_title'
                                                                        ],
                                                                        ['value' => $download_user_app_title?->getRawOriginal('value')]
                                                                    );
                                                                }
                                                            } else {
                                                                if ($request->download_user_app_title[$index] && $key != 'default') {
                                                                    Translation::updateOrInsert(
                                                                        [
                                                                            'translationable_type' => 'App\Models\DataSetting',
                                                                            'translationable_id' => $download_user_app_title->id,
                                                                            'locale' => $key,
                                                                            'key' => 'download_user_app_title'
                                                                        ],
                                                                        ['value' => $request->download_user_app_title[$index]]
                                                                    );
                                                                }
                                                            }
                                                            if ($default_lang == $key && !($request->download_user_app_sub_title[$index])) {
                                                                if ($key != 'default') {
                                                                    Translation::updateOrInsert(
                                                                        [
                                                                            'translationable_type' => 'App\Models\DataSetting',
                                                                            'translationable_id' => $download_user_app_sub_title->id,
                                                                            'locale' => $key,
                                                                            'key' => 'download_user_app_sub_title'
                                                                        ],
                                                                        ['value' => $download_user_app_sub_title?->getRawOriginal('value')]
                                                                    );
                                                                }
                                                            } else {
                                                                if ($request->download_user_app_sub_title[$index] && $key != 'default') {
                                                                    Translation::updateOrInsert(
                                                                        [
                                                                            'translationable_type' => 'App\Models\DataSetting',
                                                                            'translationable_id' => $download_user_app_sub_title->id,
                                                                            'locale' => $key,
                                                                            'key' => 'download_user_app_sub_title'
                                                                        ],
                                                                        ['value' => $request->download_user_app_sub_title[$index]]
                                                                    );
                                                                }
                                                            }
                                                        }
                                                        
                                                        Helpers::dataUpdateOrInsert(['key' => 'download_user_app_links', 'type' => 'admin_landing_page'], [
                                                            'value' => json_encode([
                                                                'playstore_url_status' => $request['playstore_url_status'],
                                                                'playstore_url' => $request['playstore_url'],
                                                                'apple_store_url_status' => $request['apple_store_url_status'],
                                                                'apple_store_url' => $request['apple_store_url']
                                                                ])
                                                            ]);
                                                            
                                                            
                                                            Toastr::success(translate('messages.download_app_section_updated'));
                                                        } else if ($tab == 'download-counter-section') {
                                                            Helpers::dataUpdateOrInsert(['key' => 'counter_section', 'type' => 'admin_landing_page'], [
                                                                'value' => json_encode([
                                                                    'app_download_count_numbers' => $request['app_download_count_numbers'],
                                                                    'seller_count_numbers' => $request['seller_count_numbers'],
                                                                    'deliveryman_count_numbers' => $request['deliveryman_count_numbers'],
                                                                    'customer_count_numbers' => $request['customer_count_numbers'],
                                                                    'status' => $request['status'],
                                                                    ])
                                                                ]);
                                                                
                                                                Toastr::success(translate('messages.landing_page_counter_section_updated'));
                                                            } elseif ($tab == 'testimonial-title') {
                                                                $testimonial_title = DataSetting::where('type', 'admin_landing_page')->where('key', 'testimonial_title')->first();
                                                                if ($testimonial_title == null) {
                                                                    $testimonial_title = new DataSetting();
                                                                }
                                                                
                                                                $testimonial_title->key = 'testimonial_title';
                                                                $testimonial_title->type = 'admin_landing_page';
                                                                $testimonial_title->value = $request->testimonial_title[array_search('default', $request->lang)];
                                                                $testimonial_title->save();
                                                                
                                                                $data = [];
                                                                $default_lang = str_replace('_', '-', app()->getLocale());
                                                                foreach ($request->lang as $index => $key) {
                                                                    if ($default_lang == $key && !($request->testimonial_title[$index])) {
                                                                        if ($key != 'default') {
                                                                            Translation::updateOrInsert(
                                                                                [
                                                                                    'translationable_type' => 'App\Models\DataSetting',
                                                                                    'translationable_id' => $testimonial_title->id,
                                                                                    'locale' => $key,
                                                                                    'key' => 'testimonial_title'
                                                                                ],
                                                                                ['value' => $testimonial_title?->getRawOriginal('value')]
                                                                            );
                                                                        }
                                                                    } else {
                                                                        if ($request->testimonial_title[$index] && $key != 'default') {
                                                                            Translation::updateOrInsert(
                                                                                [
                                                                                    'translationable_type' => 'App\Models\DataSetting',
                                                                                    'translationable_id' => $testimonial_title->id,
                                                                                    'locale' => $key,
                                                                                    'key' => 'testimonial_title'
                                                                                ],
                                                                                ['value' => $request->testimonial_title[$index]]
                                                                            );
                                                                        }
                                                                    }
                                                                }
                                                                
                                                                Toastr::success(translate('messages.testimonial_section_updated'));
                                                            } elseif ($tab == 'testimonial-list') {
                                                                $request->validate([
                                                                    'name' => 'required',
                                                                    'designation' => 'required',
                                                                    'review' => 'required',
                                                                    'reviewer_image' => 'required',
                                                                    'company_image' => 'required',
                                                                ]);
                                                                
                                                                $testimonial = new AdminTestimonial();
                                                                $testimonial->name = $request->name;
                                                                $testimonial->designation = $request->designation;
                                                                $testimonial->review = $request->review;
                                                                $testimonial->reviewer_image = Helpers::upload('reviewer_image/', 'png', $request->file('reviewer_image'));
                                                                $testimonial->company_image = Helpers::upload('reviewer_company_image/', 'png', $request->file('company_image'));
                                                                $testimonial->save();
                                                                Toastr::success(translate('messages.testimonial_added_successfully'));
                                                            } elseif ($tab == 'contact-us-section') {
                                                                $contact_us_title = DataSetting::where('type', 'admin_landing_page')->where('key', 'contact_us_title')->first();
                                                                if ($contact_us_title == null) {
                                                                    $contact_us_title = new DataSetting();
                                                                }
                                                                
                                                                $contact_us_title->key = 'contact_us_title';
                                                                $contact_us_title->type = 'admin_landing_page';
                                                                $contact_us_title->value = $request->contact_us_title[array_search('default', $request->lang)];
                                                                $contact_us_title->save();
                                                                
                                                                $contact_us_sub_title = DataSetting::where('type', 'admin_landing_page')->where('key', 'contact_us_sub_title')->first();
                                                                if ($contact_us_sub_title == null) {
                                                                    $contact_us_sub_title = new DataSetting();
                                                                }
                                                                
                                                                $contact_us_sub_title->key = 'contact_us_sub_title';
                                                                $contact_us_sub_title->type = 'admin_landing_page';
                                                                $contact_us_sub_title->value = $request->contact_us_sub_title[array_search('default', $request->lang)];
                                                                $contact_us_sub_title->save();
                                                                
                                                                $contact_us_image = DataSetting::where('type', 'admin_landing_page')->where('key', 'contact_us_image')->first();
                                                                if ($contact_us_image == null) {
                                                                    $contact_us_image = new DataSetting();
                                                                }
                                                                $contact_us_image->key = 'contact_us_image';
                                                                $contact_us_image->type = 'admin_landing_page';
                                                                $contact_us_image->value = $request->has('image') ? Helpers::update('contact_us_image/', $contact_us_image->value, 'png', $request->file('image')) : $contact_us_image->value;
                                                                $contact_us_image->save();
                                                                
                                                                $data = [];
                                                                $default_lang = str_replace('_', '-', app()->getLocale());
                                                                foreach ($request->lang as $index => $key) {
                                                                    if ($default_lang == $key && !($request->contact_us_title[$index])) {
                                                                        if ($key != 'default') {
                                                                            Translation::updateOrInsert(
                                                                                [
                                                                                    'translationable_type' => 'App\Models\DataSetting',
                                                                                    'translationable_id' => $contact_us_title->id,
                                                                                    'locale' => $key,
                                                                                    'key' => 'contact_us_title'
                                                                                ],
                                                                                ['value' => $contact_us_title?->getRawOriginal('value')]
                                                                            );
                                                                        }
                                                                    } else {
                                                                        if ($request->contact_us_title[$index] && $key != 'default') {
                                                                            Translation::updateOrInsert(
                                                                                [
                                                                                    'translationable_type' => 'App\Models\DataSetting',
                                                                                    'translationable_id' => $contact_us_title->id,
                                                                                    'locale' => $key,
                                                                                    'key' => 'contact_us_title'
                                                                                ],
                                                                                ['value' => $request->contact_us_title[$index]]
                                                                            );
                                                                        }
                                                                    }
                                                                    if ($default_lang == $key && !($request->contact_us_sub_title[$index])) {
                                                                        if ($key != 'default') {
                                                                            Translation::updateOrInsert(
                                                                                [
                                                                                    'translationable_type' => 'App\Models\DataSetting',
                                                                                    'translationable_id' => $contact_us_sub_title->id,
                                                                                    'locale' => $key,
                                                                                    'key' => 'contact_us_sub_title'
                                                                                ],
                                                                                ['value' => $contact_us_sub_title?->getRawOriginal('value')]
                                                                            );
                                                                        }
                                                                    } else {
                                                                        if ($request->contact_us_sub_title[$index] && $key != 'default') {
                                                                            Translation::updateOrInsert(
                                                                                [
                                                                                    'translationable_type' => 'App\Models\DataSetting',
                                                                                    'translationable_id' => $contact_us_sub_title->id,
                                                                                    'locale' => $key,
                                                                                    'key' => 'contact_us_sub_title'
                                                                                ],
                                                                                ['value' => $request->contact_us_sub_title[$index]]
                                                                            );
                                                                        }
                                                                    }
                                                                }
                                                                
                                                                Helpers::businessUpdateOrInsert(['key' => 'opening_time'], [
                                                                    'value' => $request['opening_time']
                                                                ]);
                                                                
                                                                Helpers::businessUpdateOrInsert(['key' => 'closing_time'], [
                                                                    'value' => $request['closing_time']
                                                                ]);
                                                                
                                                                if ($request->opening_day == $request->closing_day) {
                                                                    Toastr::error(translate('messages.the_start_day_and_end_day_is_same'));
                                                                } else {
                                                                    Helpers::businessUpdateOrInsert(['key' => 'opening_day'], [
                                                                        'value' => $request['opening_day']
                                                                    ]);
                                                                    
                                                                    Helpers::businessUpdateOrInsert(['key' => 'closing_day'], [
                                                                        'value' => $request['closing_day']
                                                                    ]);
                                                                }
                                                                
                                                                
                                                                Toastr::success(translate('messages.contact_section_updated'));
                                                            } elseif ($tab == 'available-zone-section') {
                                                                if($request['available_zone_status']){
                                                                    $request->validate([
                                                                        'available_zone_title.0' => 'required',
                                                                        
                                                                    ], [
                                                                        'available_zone_title.0.required' => translate('default_title_is_required'),
                                                                    ]);
                                                                }
                                                                $available_zone_title = DataSetting::where('type', 'admin_landing_page')->where('key', 'available_zone_title')->first();
                                                                if ($available_zone_title == null) {
                                                                    $available_zone_title = new DataSetting();
                                                                }
                                                                
                                                                $available_zone_title->key = 'available_zone_title';
                                                                $available_zone_title->type = 'admin_landing_page';
                                                                $available_zone_title->value = $request->available_zone_title[array_search('default', $request->lang)];
                                                                $available_zone_title->save();
                                                                
                                                                $available_zone_short_description = DataSetting::where('type', 'admin_landing_page')->where('key', 'available_zone_short_description')->first();
                                                                if ($available_zone_short_description == null) {
                                                                    $available_zone_short_description = new DataSetting();
                                                                }
                                                                
                                                                $available_zone_short_description->key = 'available_zone_short_description';
                                                                $available_zone_short_description->type = 'admin_landing_page';
                                                                $available_zone_short_description->value = $request->available_zone_short_description[array_search('default', $request->lang)];
                                                                $available_zone_short_description->save();
                                                                
                                                                $available_zone_image = DataSetting::where('type', 'admin_landing_page')->where('key', 'available_zone_image')->first();
                                                                if ($available_zone_image == null) {
                                                                    
                                                                    if($request['available_zone_status']){
                                                                        $request->validate([
                                                                            'image' => 'required',
                                                                        ]);
                                                                    }
                                                                    
                                                                    $available_zone_image = new DataSetting();
                                                                }
                                                                $available_zone_image->key = 'available_zone_image';
                                                                $available_zone_image->type = 'admin_landing_page';
                                                                $available_zone_image->value = $request->has('image') ? Helpers::update('available_zone_image/', $available_zone_image->value, 'png', $request->file('image')) : $available_zone_image->value;
                                                                $available_zone_image->save();
                                                                
                                                                $data = [];
                                                                $default_lang = str_replace('_', '-', app()->getLocale());
                                                                foreach ($request->lang as $index => $key) {
                                                                    if ($default_lang == $key && !($request->available_zone_title[$index])) {
                                                                        if ($key != 'default') {
                                                                            Translation::updateOrInsert(
                                                                                [
                                                                                    'translationable_type' => 'App\Models\DataSetting',
                                                                                    'translationable_id' => $available_zone_title->id,
                                                                                    'locale' => $key,
                                                                                    'key' => 'available_zone_title'
                                                                                ],
                                                                                ['value' => $available_zone_title?->getRawOriginal('value')]
                                                                            );
                                                                        }
                                                                    } else {
                                                                        if ($request->available_zone_title[$index] && $key != 'default') {
                                                                            Translation::updateOrInsert(
                                                                                [
                                                                                    'translationable_type' => 'App\Models\DataSetting',
                                                                                    'translationable_id' => $available_zone_title->id,
                                                                                    'locale' => $key,
                                                                                    'key' => 'available_zone_title'
                                                                                ],
                                                                                ['value' => $request->available_zone_title[$index]]
                                                                            );
                                                                        }
                                                                    }
                                                                    if ($default_lang == $key && !($request->available_zone_short_description[$index])) {
                                                                        if ($key != 'default') {
                                                                            Translation::updateOrInsert(
                                                                                [
                                                                                    'translationable_type' => 'App\Models\DataSetting',
                                                                                    'translationable_id' => $available_zone_short_description->id,
                                                                                    'locale' => $key,
                                                                                    'key' => 'available_zone_short_description'
                                                                                ],
                                                                                ['value' => $available_zone_short_description?->getRawOriginal('value')]
                                                                            );
                                                                        }
                                                                    } else {
                                                                        if ($request->available_zone_short_description[$index] && $key != 'default') {
                                                                            Translation::updateOrInsert(
                                                                                [
                                                                                    'translationable_type' => 'App\Models\DataSetting',
                                                                                    'translationable_id' => $available_zone_short_description->id,
                                                                                    'locale' => $key,
                                                                                    'key' => 'available_zone_short_description'
                                                                                ],
                                                                                ['value' => $request->available_zone_short_description[$index]]
                                                                            );
                                                                        }
                                                                    }
                                                                }
                                                                
                                                                Helpers::dataUpdateOrInsert(['type' => 'admin_landing_page','key' => 'available_zone_status'], [
                                                                    'value' => $request['available_zone_status']
                                                                ]);
                                                                
                                                                
                                                                Toastr::success(translate('messages.available_zone_section_updated'));
                                                            } elseif ($tab == 'background-color') {
                                                                Helpers::businessUpdateOrInsert(['key' => 'backgroundChange'], [
                                                                    'value' => json_encode([
                                                                        'primary_1_hex' => $request['header-bg'],
                                                                        'primary_1_rgb' => Helpers::hex_to_rbg($request['header-bg']),
                                                                        'primary_2_hex' => $request['footer-bg'],
                                                                        'primary_2_rgb' => Helpers::hex_to_rbg($request['footer-bg']),
                                                                        ])
                                                                    ]);
                                                                    Toastr::success(translate('messages.background_updated'));
                                                                }
                                                                return back();
                                                            }
                                                            
                                                            public function promotional_status(Request $request)
                                                            {
                                                                if (env('APP_MODE') == 'demo' && $request->id == 1) {
                                                                    Toastr::warning('Sorry!You can not inactive this banner!');
                                                                    return back();
                                                                }
                                                                $banner = AdminPromotionalBanner::findOrFail($request->id);
                                                                $banner->status = $request->status;
                                                                $banner->save();
                                                                Toastr::success(translate('messages.banner_status_updated'));
                                                                return back();
                                                            }
                                                            
                                                            public function promotional_edit($id)
                                                            {
                                                                $banner = AdminPromotionalBanner::withoutGlobalScope('translate')->findOrFail($id);
                                                                return view('admin-views.business-settings.landing-page-settings.admin-promotional-section-edit', compact('banner'));
                                                            }
                                                            
                                                            public function promotional_update(Request $request, $id)
                                                            {
                                                                $request->validate([
                                                                    'title' => 'required|max:100',
                                                                    'sub_title' => 'required'
                                                                ]);
                                                                
                                                                if ($request->title[array_search('default', $request->lang)] == '') {
                                                                    Toastr::error(translate('default_data_is_required'));
                                                                    return back();
                                                                }
                                                                $banner = AdminPromotionalBanner::find($id);
                                                                $banner->title = $request->title[array_search('default', $request->lang)];
                                                                $banner->sub_title = $request->sub_title[array_search('default', $request->lang)];
                                                                $banner->image = $request->has('image') ? Helpers::update('promotional_banner/', $banner->image, 'png', $request->file('image')) : $banner->image;
                                                                $banner->save();
                                                                $default_lang = str_replace('_', '-', app()->getLocale());
                                                                foreach ($request->lang as $index => $key) {
                                                                    if ($default_lang == $key && !($request->title[$index])) {
                                                                        if ($key != 'default') {
                                                                            Translation::updateOrInsert(
                                                                                [
                                                                                    'translationable_type' => 'App\Models\AdminPromotionalBanner',
                                                                                    'translationable_id' => $banner->id,
                                                                                    'locale' => $key,
                                                                                    'key' => 'title'
                                                                                ],
                                                                                ['value' => $banner->title]
                                                                            );
                                                                        }
                                                                    } else {
                                                                        
                                                                        if ($request->title[$index] && $key != 'default') {
                                                                            Translation::updateOrInsert(
                                                                                [
                                                                                    'translationable_type' => 'App\Models\AdminPromotionalBanner',
                                                                                    'translationable_id' => $banner->id,
                                                                                    'locale' => $key,
                                                                                    'key' => 'title'
                                                                                ],
                                                                                ['value' => $request->title[$index]]
                                                                            );
                                                                        }
                                                                    }
                                                                    if ($default_lang == $key && !($request->sub_title[$index])) {
                                                                        if ($key != 'default') {
                                                                            Translation::updateOrInsert(
                                                                                [
                                                                                    'translationable_type' => 'App\Models\AdminPromotionalBanner',
                                                                                    'translationable_id' => $banner->id,
                                                                                    'locale' => $key,
                                                                                    'key' => 'sub_title'
                                                                                ],
                                                                                ['value' => $banner->sub_title]
                                                                            );
                                                                        }
                                                                    } else {
                                                                        
                                                                        if ($request->sub_title[$index] && $key != 'default') {
                                                                            Translation::updateOrInsert(
                                                                                [
                                                                                    'translationable_type' => 'App\Models\AdminPromotionalBanner',
                                                                                    'translationable_id' => $banner->id,
                                                                                    'locale' => $key,
                                                                                    'key' => 'sub_title'
                                                                                ],
                                                                                ['value' => $request->sub_title[$index]]
                                                                            );
                                                                        }
                                                                    }
                                                                }
                                                                Toastr::success(translate('messages.banner_updated_successfully'));
                                                                return back();
                                                            }
                                                            
                                                            public function promotional_destroy(AdminPromotionalBanner $banner)
                                                            {
                                                                if (env('APP_MODE') == 'demo' && $banner->id == 1) {
                                                                    Toastr::warning(translate('messages.you_can_not_delete_this_banner_please_add_a_new_banner_to_delete'));
                                                                    return back();
                                                                }
                                                                $banner->delete();
                                                                Toastr::success(translate('messages.banner_deleted_successfully'));
                                                                return back();
                                                            }
                                                            
                                                            public function feature_status(Request $request)
                                                            {
                                                                if (env('APP_MODE') == 'demo' && $request->id == 1) {
                                                                    Toastr::warning('Sorry!You can not inactive this feature!');
                                                                    return back();
                                                                }
                                                                $feature = AdminFeature::findOrFail($request->id);
                                                                $feature->status = $request->status;
                                                                $feature->save();
                                                                Toastr::success(translate('messages.feature_status_updated'));
                                                                return back();
                                                            }
                                                            
                                                            public function feature_edit($id)
                                                            {
                                                                $feature = AdminFeature::withoutGlobalScope('translate')->findOrFail($id);
                                                                return view('admin-views.business-settings.landing-page-settings.admin-feature-list-edit', compact('feature'));
                                                            }
                                                            
                                                            public function feature_update(Request $request, $id)
                                                            {
                                                                $request->validate([
                                                                    'title' => 'required|max:100',
                                                                    'sub_title' => 'required'
                                                                ]);
                                                                
                                                                if ($request->title[array_search('default', $request->lang)] == '') {
                                                                    Toastr::error(translate('default_data_is_required'));
                                                                    return back();
                                                                }
                                                                $feature = AdminFeature::find($id);
                                                                $feature->title = $request->title[array_search('default', $request->lang)];
                                                                $feature->sub_title = $request->sub_title[array_search('default', $request->lang)];
                                                                $feature->image = $request->has('image') ? Helpers::update('admin_feature/', $feature->image, 'png', $request->file('image')) : $feature->image;
                                                                $feature->save();
                                                                $default_lang = str_replace('_', '-', app()->getLocale());
                                                                foreach ($request->lang as $index => $key) {
                                                                    if ($default_lang == $key && !($request->title[$index])) {
                                                                        if ($key != 'default') {
                                                                            Translation::updateOrInsert(
                                                                                [
                                                                                    'translationable_type' => 'App\Models\AdminFeature',
                                                                                    'translationable_id' => $feature->id,
                                                                                    'locale' => $key,
                                                                                    'key' => 'title'
                                                                                ],
                                                                                ['value' => $feature->title]
                                                                            );
                                                                        }
                                                                    } else {
                                                                        
                                                                        if ($request->title[$index] && $key != 'default') {
                                                                            Translation::updateOrInsert(
                                                                                [
                                                                                    'translationable_type' => 'App\Models\AdminFeature',
                                                                                    'translationable_id' => $feature->id,
                                                                                    'locale' => $key,
                                                                                    'key' => 'title'
                                                                                ],
                                                                                ['value' => $request->title[$index]]
                                                                            );
                                                                        }
                                                                    }
                                                                    if ($default_lang == $key && !($request->sub_title[$index])) {
                                                                        if ($key != 'default') {
                                                                            Translation::updateOrInsert(
                                                                                [
                                                                                    'translationable_type' => 'App\Models\AdminFeature',
                                                                                    'translationable_id' => $feature->id,
                                                                                    'locale' => $key,
                                                                                    'key' => 'sub_title'
                                                                                ],
                                                                                ['value' => $feature->sub_title]
                                                                            );
                                                                        }
                                                                    } else {
                                                                        
                                                                        if ($request->sub_title[$index] && $key != 'default') {
                                                                            Translation::updateOrInsert(
                                                                                [
                                                                                    'translationable_type' => 'App\Models\AdminFeature',
                                                                                    'translationable_id' => $feature->id,
                                                                                    'locale' => $key,
                                                                                    'key' => 'sub_title'
                                                                                ],
                                                                                ['value' => $request->sub_title[$index]]
                                                                            );
                                                                        }
                                                                    }
                                                                }
                                                                Toastr::success(translate('messages.feature_updated_successfully'));
                                                                return back();
                                                            }
                                                            
                                                            public function feature_destroy(AdminFeature $feature)
                                                            {
                                                                if (env('APP_MODE') == 'demo' && $feature->id == 1) {
                                                                    Toastr::warning(translate('messages.you_can_not_delete_this_feature_please_add_a_new_feature_to_delete'));
                                                                    return back();
                                                                }
                                                                $feature->delete();
                                                                Toastr::success(translate('messages.feature_deleted_successfully'));
                                                                return back();
                                                            }
                                                            
                                                            public function criteria_status(Request $request)
                                                            {
                                                                if (env('APP_MODE') == 'demo' && $request->id == 1) {
                                                                    Toastr::warning('Sorry!You can not inactive this criteria!');
                                                                    return back();
                                                                }
                                                                $criteria = AdminSpecialCriteria::findOrFail($request->id);
                                                                $criteria->status = $request->status;
                                                                $criteria->save();
                                                                Toastr::success(translate('messages.criteria_status_updated'));
                                                                return back();
                                                            }
                                                            
                                                            public function criteria_edit($id)
                                                            {
                                                                $criteria = AdminSpecialCriteria::withoutGlobalScope('translate')->findOrFail($id);
                                                                return view('admin-views.business-settings.landing-page-settings.admin-landing-why-choose-edit', compact('criteria'));
                                                            }
                                                            
                                                            public function criteria_update(Request $request, $id)
                                                            {
                                                                $request->validate([
                                                                    'title' => 'required|max:100',
                                                                ]);
                                                                
                                                                if ($request->title[array_search('default', $request->lang)] == '') {
                                                                    Toastr::error(translate('default_data_is_required'));
                                                                    return back();
                                                                }
                                                                $criteria = AdminSpecialCriteria::find($id);
                                                                $criteria->title = $request->title[array_search('default', $request->lang)];
                                                                $criteria->image = $request->has('image') ? Helpers::update('special_criteria/', $criteria->image, 'png', $request->file('image')) : $criteria->image;
                                                                $criteria->save();
                                                                $default_lang = str_replace('_', '-', app()->getLocale());
                                                                foreach ($request->lang as $index => $key) {
                                                                    if ($default_lang == $key && !($request->title[$index])) {
                                                                        if ($key != 'default') {
                                                                            Translation::updateOrInsert(
                                                                                [
                                                                                    'translationable_type' => 'App\Models\AdminSpecialCriteria',
                                                                                    'translationable_id' => $criteria->id,
                                                                                    'locale' => $key,
                                                                                    'key' => 'title'
                                                                                ],
                                                                                ['value' => $criteria->title]
                                                                            );
                                                                        }
                                                                    } else {
                                                                        
                                                                        if ($request->title[$index] && $key != 'default') {
                                                                            Translation::updateOrInsert(
                                                                                [
                                                                                    'translationable_type' => 'App\Models\AdminSpecialCriteria',
                                                                                    'translationable_id' => $criteria->id,
                                                                                    'locale' => $key,
                                                                                    'key' => 'title'
                                                                                ],
                                                                                ['value' => $request->title[$index]]
                                                                            );
                                                                        }
                                                                    }
                                                                }
                                                                Toastr::success(translate('messages.criteria_updated_successfully'));
                                                                return back();
                                                            }
                                                            
                                                            public function criteria_destroy(AdminSpecialCriteria $criteria)
                                                            {
                                                                if (env('APP_MODE') == 'demo' && $criteria->id == 1) {
                                                                    Toastr::warning(translate('messages.you_can_not_delete_this_criteria_please_add_a_new_criteria_to_delete'));
                                                                    return back();
                                                                }
                                                                $criteria->delete();
                                                                Toastr::success(translate('messages.criteria_deleted_successfully'));
                                                                return back();
                                                            }
                                                            
                                                            public function review_status(Request $request)
                                                            {
                                                                if (env('APP_MODE') == 'demo' && $request->id == 1) {
                                                                    Toastr::warning('Sorry!You can not inactive this review!');
                                                                    return back();
                                                                }
                                                                $review = AdminTestimonial::findOrFail($request->id);
                                                                $review->status = $request->status;
                                                                $review->save();
                                                                Toastr::success(translate('messages.review_status_updated'));
                                                                return back();
                                                            }
                                                            
                                                            public function review_edit($id)
                                                            {
                                                                $review = AdminTestimonial::withoutGlobalScope('translate')->findOrFail($id);
                                                                return view('admin-views.business-settings.landing-page-settings.admin-landing-testimonial-test', compact('review'));
                                                            }
                                                            
                                                            public function review_update(Request $request, $id)
                                                            {
                                                                $request->validate([
                                                                    'name' => 'required',
                                                                    'designation' => 'required',
                                                                    'review' => 'required',
                                                                ]);
                                                                
                                                                $review = AdminTestimonial::findOrFail($id);
                                                                $review->name = $request->name;
                                                                $review->designation = $request->designation;
                                                                $review->review = $request->review;
                                                                $review->reviewer_image = $request->has('reviewer_image') ? Helpers::update('reviewer_image/', $review->reviewer_image, 'png', $request->file('reviewer_image')) : $review->reviewer_image;
                                                                $review->company_image = $request->has('company_image') ? Helpers::update('reviewer_company_image/', $review->company_image, 'png', $request->file('company_image')) : $review->company_image;
                                                                $review->save();
                                                                
                                                                Toastr::success(translate('messages.review_updated_successfully'));
                                                                return back();
                                                            }
                                                            
                                                            public function review_destroy(AdminTestimonial $review)
                                                            {
                                                                if (env('APP_MODE') == 'demo' && $review->id == 1) {
                                                                    Toastr::warning(translate('messages.you_can_not_delete_this_review_please_add_a_new_review_to_delete'));
                                                                    return back();
                                                                }
                                                                $review->delete();
                                                                Toastr::success(translate('messages.review_deleted_successfully'));
                                                                return back();
                                                            }
                                                            
                                                            public function react_landing_page_settings($tab)
                                                            {
                                                                if ($tab == 'header') {
                                                                    return view('admin-views.business-settings.landing-page-settings.react-landing-page-header');
                                                                } else if ($tab == 'company-intro') {
                                                                    return view('admin-views.business-settings.landing-page-settings.react-landing-page-company');
                                                                } else if ($tab == 'download-user-app') {
                                                                    return view('admin-views.business-settings.landing-page-settings.react-landing-download-apps');
                                                                } else if ($tab == 'promotion-banner') {
                                                                    return view('admin-views.business-settings.landing-page-settings.react-landing-promotion-banners');
                                                                } else if ($tab == 'earn-money') {
                                                                    return view('admin-views.business-settings.landing-page-settings.react-landing-earn-money');
                                                                } else if ($tab == 'available-zone') {
                                                                    return view('admin-views.business-settings.landing-page-settings.react-landing-available-zone');
                                                                } else if ($tab == 'business-section') {
                                                                    return view('admin-views.business-settings.landing-page-settings.react-landing-business');
                                                                } else if ($tab == 'testimonials') {
                                                                    return view('admin-views.business-settings.landing-page-settings.react-landing-testimonial');
                                                                } else if ($tab == 'fixed-data') {
                                                                    return view('admin-views.business-settings.landing-page-settings.react-landing-fixed-data');
                                                                }
                                                            }
                                                            
                                                            public function update_react_landing_page_settings(Request $request, $tab)
                                                            {
                                                                if (env('APP_MODE') == 'demo') {
                                                                    Toastr::info(translate('messages.update_option_is_disable_for_demo'));
                                                                    return back();
                                                                }
                                                                if ($tab == 'download-app-section') {
                                                                    
                                                                    $request->validate([
                                                                        'download_user_app_title.0' => 'required',
                                                                        'download_user_app_sub_title.0' => 'required',
                                                                    ], [
                                                                        'download_user_app_title.0.required' => translate('messages.Default_title_is_required'),
                                                                        'download_user_app_sub_title.0.required' => translate('messages.Default_subtitle_is_required'),
                                                                    ]);
                                                                    
                                                                    $download_user_app_title = DataSetting::where('type', 'react_landing_page')->where('key', 'download_user_app_title')->first();
                                                                    if ($download_user_app_title == null) {
                                                                        $download_user_app_title = new DataSetting();
                                                                    }
                                                                    
                                                                    $download_user_app_title->key = 'download_user_app_title';
                                                                    $download_user_app_title->type = 'react_landing_page';
                                                                    $download_user_app_title->value = $request->download_user_app_title[array_search('default', $request->lang)];
                                                                    $download_user_app_title->save();
                                                                    
                                                                    $download_user_app_sub_title = DataSetting::where('type', 'react_landing_page')->where('key', 'download_user_app_sub_title')->first();
                                                                    if ($download_user_app_sub_title == null) {
                                                                        $download_user_app_sub_title = new DataSetting();
                                                                    }
                                                                    
                                                                    $download_user_app_sub_title->key = 'download_user_app_sub_title';
                                                                    $download_user_app_sub_title->type = 'react_landing_page';
                                                                    $download_user_app_sub_title->value = $request->download_user_app_sub_title[array_search('default', $request->lang)];
                                                                    $download_user_app_sub_title->save();
                                                                    
                                                                    $download_user_app_image = DataSetting::where('type', 'react_landing_page')->where('key', 'download_user_app_image')->first();
                                                                    if ($download_user_app_image == null) {
                                                                        $download_user_app_image = new DataSetting();
                                                                    }
                                                                    $download_user_app_image->key = 'download_user_app_image';
                                                                    $download_user_app_image->type = 'react_landing_page';
                                                                    $download_user_app_image->value = $request->has('image') ? Helpers::update('download_user_app_image/', $download_user_app_image->value, 'png', $request->file('image')) : $download_user_app_image->value;
                                                                    $download_user_app_image->save();
                                                                    
                                                                    $data = [];
                                                                    $default_lang = str_replace('_', '-', app()->getLocale());
                                                                    foreach ($request->lang as $index => $key) {
                                                                        if ($default_lang == $key && !($request->download_user_app_title[$index])) {
                                                                            if ($key != 'default') {
                                                                                Translation::updateOrInsert(
                                                                                    [
                                                                                        'translationable_type' => 'App\Models\DataSetting',
                                                                                        'translationable_id' => $download_user_app_title->id,
                                                                                        'locale' => $key,
                                                                                        'key' => 'download_user_app_title'
                                                                                    ],
                                                                                    ['value' => $download_user_app_title?->getRawOriginal('value')]
                                                                                );
                                                                            }
                                                                        } else {
                                                                            if ($request->download_user_app_title[$index] && $key != 'default') {
                                                                                Translation::updateOrInsert(
                                                                                    [
                                                                                        'translationable_type' => 'App\Models\DataSetting',
                                                                                        'translationable_id' => $download_user_app_title->id,
                                                                                        'locale' => $key,
                                                                                        'key' => 'download_user_app_title'
                                                                                    ],
                                                                                    ['value' => $request->download_user_app_title[$index]]
                                                                                );
                                                                            }
                                                                        }
                                                                        if ($default_lang == $key && !($request->download_user_app_sub_title[$index])) {
                                                                            if ($key != 'default') {
                                                                                Translation::updateOrInsert(
                                                                                    [
                                                                                        'translationable_type' => 'App\Models\DataSetting',
                                                                                        'translationable_id' => $download_user_app_sub_title->id,
                                                                                        'locale' => $key,
                                                                                        'key' => 'download_user_app_sub_title'
                                                                                    ],
                                                                                    ['value' => $download_user_app_sub_title?->getRawOriginal('value')]
                                                                                );
                                                                            }
                                                                        } else {
                                                                            if ($request->download_user_app_sub_title[$index] && $key != 'default') {
                                                                                Translation::updateOrInsert(
                                                                                    [
                                                                                        'translationable_type' => 'App\Models\DataSetting',
                                                                                        'translationable_id' => $download_user_app_sub_title->id,
                                                                                        'locale' => $key,
                                                                                        'key' => 'download_user_app_sub_title'
                                                                                    ],
                                                                                    ['value' => $request->download_user_app_sub_title[$index]]
                                                                                );
                                                                            }
                                                                        }
                                                                    }
                                                                    
                                                                    Helpers::dataUpdateOrInsert(['key' => 'download_user_app_links', 'type' => 'react_landing_page'], [
                                                                        'value' => json_encode([
                                                                            'playstore_url_status' => $request['playstore_url_status'],
                                                                            'playstore_url' => $request['playstore_url'],
                                                                            'apple_store_url_status' => $request['apple_store_url_status'],
                                                                            'apple_store_url' => $request['apple_store_url']
                                                                            ])
                                                                        ]);
                                                                        
                                                                        
                                                                        Toastr::success(translate('messages.download_app_section_updated'));
                                                                    } elseif ($tab == 'available-zone-section') {
                                                                        if($request['available_zone_status']){
                                                                            $request->validate([
                                                                                'available_zone_title.0' => 'required',
                                                                                
                                                                            ], [
                                                                                'available_zone_title.0.required' => translate('default_title_is_required'),
                                                                            ]);
                                                                        }
                                                                        $available_zone_title = DataSetting::where('type', 'react_landing_page')->where('key', 'available_zone_title')->first();
                                                                        if ($available_zone_title == null) {
                                                                            $available_zone_title = new DataSetting();
                                                                        }
                                                                        
                                                                        $available_zone_title->key = 'available_zone_title';
                                                                        $available_zone_title->type = 'react_landing_page';
                                                                        $available_zone_title->value = $request->available_zone_title[array_search('default', $request->lang)];
                                                                        $available_zone_title->save();
                                                                        
                                                                        $available_zone_short_description = DataSetting::where('type', 'react_landing_page')->where('key', 'available_zone_short_description')->first();
                                                                        if ($available_zone_short_description == null) {
                                                                            $available_zone_short_description = new DataSetting();
                                                                        }
                                                                        
                                                                        $available_zone_short_description->key = 'available_zone_short_description';
                                                                        $available_zone_short_description->type = 'react_landing_page';
                                                                        $available_zone_short_description->value = $request->available_zone_short_description[array_search('default', $request->lang)];
                                                                        $available_zone_short_description->save();
                                                                        
                                                                        $available_zone_image = DataSetting::where('type', 'react_landing_page')->where('key', 'available_zone_image')->first();
                                                                        if ($available_zone_image == null) {
                                                                            
                                                                            if($request['available_zone_status']){
                                                                                $request->validate([
                                                                                    'image' => 'required',
                                                                                ]);
                                                                            }
                                                                            
                                                                            $available_zone_image = new DataSetting();
                                                                        }
                                                                        $available_zone_image->key = 'available_zone_image';
                                                                        $available_zone_image->type = 'react_landing_page';
                                                                        $available_zone_image->value = $request->has('image') ? Helpers::update('available_zone_image/', $available_zone_image->value, 'png', $request->file('image')) : $available_zone_image->value;
                                                                        $available_zone_image->save();
                                                                        
                                                                        $data = [];
                                                                        $default_lang = str_replace('_', '-', app()->getLocale());
                                                                        foreach ($request->lang as $index => $key) {
                                                                            if ($default_lang == $key && !($request->available_zone_title[$index])) {
                                                                                if ($key != 'default') {
                                                                                    Translation::updateOrInsert(
                                                                                        [
                                                                                            'translationable_type' => 'App\Models\DataSetting',
                                                                                            'translationable_id' => $available_zone_title->id,
                                                                                            'locale' => $key,
                                                                                            'key' => 'available_zone_title'
                                                                                        ],
                                                                                        ['value' => $available_zone_title?->getRawOriginal('value')]
                                                                                    );
                                                                                }
                                                                            } else {
                                                                                if ($request->available_zone_title[$index] && $key != 'default') {
                                                                                    Translation::updateOrInsert(
                                                                                        [
                                                                                            'translationable_type' => 'App\Models\DataSetting',
                                                                                            'translationable_id' => $available_zone_title->id,
                                                                                            'locale' => $key,
                                                                                            'key' => 'available_zone_title'
                                                                                        ],
                                                                                        ['value' => $request->available_zone_title[$index]]
                                                                                    );
                                                                                }
                                                                            }
                                                                            if ($default_lang == $key && !($request->available_zone_short_description[$index])) {
                                                                                if ($key != 'default') {
                                                                                    Translation::updateOrInsert(
                                                                                        [
                                                                                            'translationable_type' => 'App\Models\DataSetting',
                                                                                            'translationable_id' => $available_zone_short_description->id,
                                                                                            'locale' => $key,
                                                                                            'key' => 'available_zone_short_description'
                                                                                        ],
                                                                                        ['value' => $available_zone_short_description?->getRawOriginal('value')]
                                                                                    );
                                                                                }
                                                                            } else {
                                                                                if ($request->available_zone_short_description[$index] && $key != 'default') {
                                                                                    Translation::updateOrInsert(
                                                                                        [
                                                                                            'translationable_type' => 'App\Models\DataSetting',
                                                                                            'translationable_id' => $available_zone_short_description->id,
                                                                                            'locale' => $key,
                                                                                            'key' => 'available_zone_short_description'
                                                                                        ],
                                                                                        ['value' => $request->available_zone_short_description[$index]]
                                                                                    );
                                                                                }
                                                                            }
                                                                        }
                                                                        
                                                                        Helpers::dataUpdateOrInsert(['type' => 'react_landing_page','key' => 'available_zone_status'], [
                                                                            'value' => $request['available_zone_status']
                                                                        ]);
                                                                        
                                                                        
                                                                        Toastr::success(translate('messages.available_zone_section_updated'));
                                                                    } elseif ($tab == 'earning-title') {
                                                                        $earning_title = DataSetting::where('type', 'react_landing_page')->where('key', 'earning_title')->first();
                                                                        if ($earning_title == null) {
                                                                            $earning_title = new DataSetting();
                                                                        }
                                                                        
                                                                        $earning_title->key = 'earning_title';
                                                                        $earning_title->type = 'react_landing_page';
                                                                        $earning_title->value = $request->earning_title[array_search('default', $request->lang)];
                                                                        $earning_title->save();
                                                                        
                                                                        $earning_sub_title = DataSetting::where('type', 'react_landing_page')->where('key', 'earning_sub_title')->first();
                                                                        if ($earning_sub_title == null) {
                                                                            $earning_sub_title = new DataSetting();
                                                                        }
                                                                        
                                                                        $earning_sub_title->key = 'earning_sub_title';
                                                                        $earning_sub_title->type = 'react_landing_page';
                                                                        $earning_sub_title->value = $request->earning_sub_title[array_search('default', $request->lang)];
                                                                        $earning_sub_title->save();
                                                                        
                                                                        
                                                                        $data = [];
                                                                        $default_lang = str_replace('_', '-', app()->getLocale());
                                                                        foreach ($request->lang as $index => $key) {
                                                                            if ($default_lang == $key && !($request->earning_title[$index])) {
                                                                                if ($key != 'default') {
                                                                                    Translation::updateOrInsert(
                                                                                        [
                                                                                            'translationable_type' => 'App\Models\DataSetting',
                                                                                            'translationable_id' => $earning_title->id,
                                                                                            'locale' => $key,
                                                                                            'key' => 'earning_title'
                                                                                        ],
                                                                                        ['value' => $earning_title?->getRawOriginal('value')]
                                                                                    );
                                                                                }
                                                                            } else {
                                                                                if ($request->earning_title[$index] && $key != 'default') {
                                                                                    Translation::updateOrInsert(
                                                                                        [
                                                                                            'translationable_type' => 'App\Models\DataSetting',
                                                                                            'translationable_id' => $earning_title->id,
                                                                                            'locale' => $key,
                                                                                            'key' => 'earning_title'
                                                                                        ],
                                                                                        ['value' => $request->earning_title[$index]]
                                                                                    );
                                                                                }
                                                                            }
                                                                            if ($default_lang == $key && !($request->earning_sub_title[$index])) {
                                                                                if ($key != 'default') {
                                                                                    Translation::updateOrInsert(
                                                                                        [
                                                                                            'translationable_type' => 'App\Models\DataSetting',
                                                                                            'translationable_id' => $earning_sub_title->id,
                                                                                            'locale' => $key,
                                                                                            'key' => 'earning_sub_title'
                                                                                        ],
                                                                                        ['value' => $earning_sub_title?->getRawOriginal('value')]
                                                                                    );
                                                                                }
                                                                            } else {
                                                                                if ($request->earning_sub_title[$index] && $key != 'default') {
                                                                                    Translation::updateOrInsert(
                                                                                        [
                                                                                            'translationable_type' => 'App\Models\DataSetting',
                                                                                            'translationable_id' => $earning_sub_title->id,
                                                                                            'locale' => $key,
                                                                                            'key' => 'earning_sub_title'
                                                                                        ],
                                                                                        ['value' => $request->earning_sub_title[$index]]
                                                                                    );
                                                                                }
                                                                            }
                                                                        }
                                                                        
                                                                        Toastr::success(translate('messages.earning_section_updated'));
                                                                    } elseif ($tab == 'earning-seller-link') {
                                                                        
                                                                        if($request->join_seller_react_status !== null ){
                                                                            $join_seller_react_status = DataSetting::where('type', 'react_landing_page')->where('key', 'join_seller_react_status')->first();
                                                                            if ($join_seller_react_status == null) {
                                                                                $join_seller_react_status = new DataSetting();
                                                                            }
                                                                            
                                                                            $join_seller_react_status->key = 'join_seller_react_status';
                                                                            $join_seller_react_status->type = 'react_landing_page';
                                                                            $join_seller_react_status->value = $request->join_seller_react_status  ? 0 : 1 ;
                                                                            $join_seller_react_status->save();
                                                                            
                                                                            Toastr::success(translate('messages.Seller_Section_Content_status_updated'));
                                                                            return back();
                                                                        }
                                                                        
                                                                        
                                                                        
                                                                        $earning_seller_title = DataSetting::where('type', 'react_landing_page')->where('key', 'earning_seller_title')->first();
                                                                        if ($earning_seller_title == null) {
                                                                            $earning_seller_title = new DataSetting();
                                                                        }
                                                                        
                                                                        $earning_seller_title->key = 'earning_seller_title';
                                                                        $earning_seller_title->type = 'react_landing_page';
                                                                        $earning_seller_title->value = $request->earning_seller_title[array_search('default', $request->lang)];
                                                                        $earning_seller_title->save();
                                                                        
                                                                        $earning_seller_sub_title = DataSetting::where('type', 'react_landing_page')->where('key', 'earning_seller_sub_title')->first();
                                                                        if ($earning_seller_sub_title == null) {
                                                                            $earning_seller_sub_title = new DataSetting();
                                                                        }
                                                                        
                                                                        $earning_seller_sub_title->key = 'earning_seller_sub_title';
                                                                        $earning_seller_sub_title->type = 'react_landing_page';
                                                                        $earning_seller_sub_title->value = $request->earning_seller_sub_title[array_search('default', $request->lang)];
                                                                        $earning_seller_sub_title->save();
                                                                        
                                                                        $earning_seller_button_name = DataSetting::where('type', 'react_landing_page')->where('key', 'earning_seller_button_name')->first();
                                                                        if ($earning_seller_button_name == null) {
                                                                            $earning_seller_button_name = new DataSetting();
                                                                        }
                                                                        
                                                                        $earning_seller_button_name->key = 'earning_seller_button_name';
                                                                        $earning_seller_button_name->type = 'react_landing_page';
                                                                        $earning_seller_button_name->value = $request->earning_seller_button_name[array_search('default', $request->lang)];
                                                                        $earning_seller_button_name->save();
                                                                        
                                                                        $earning_seller_button_url = DataSetting::where('type', 'react_landing_page')->where('key', 'earning_seller_button_url')->first();
                                                                        if ($earning_seller_button_url == null) {
                                                                            $earning_seller_button_url = new DataSetting();
                                                                        }
                                                                        
                                                                        $earning_seller_button_url->key = 'earning_seller_button_url';
                                                                        $earning_seller_button_url->type = 'react_landing_page';
                                                                        $earning_seller_button_url->value = $request->earning_seller_button_url;
                                                                        $earning_seller_button_url->save();
                                                                        
                                                                        
                                                                        $data = [];
                                                                        $default_lang = str_replace('_', '-', app()->getLocale());
                                                                        foreach ($request->lang as $index => $key) {
                                                                            if ($default_lang == $key && !($request->earning_seller_title[$index])) {
                                                                                if ($key != 'default') {
                                                                                    Translation::updateOrInsert(
                                                                                        [
                                                                                            'translationable_type' => 'App\Models\DataSetting',
                                                                                            'translationable_id' => $earning_seller_title->id,
                                                                                            'locale' => $key,
                                                                                            'key' => 'earning_seller_title'
                                                                                        ],
                                                                                        ['value' => $earning_seller_title?->getRawOriginal('value')]
                                                                                    );
                                                                                }
                                                                            } else {
                                                                                if ($request->earning_seller_title[$index] && $key != 'default') {
                                                                                    Translation::updateOrInsert(
                                                                                        [
                                                                                            'translationable_type' => 'App\Models\DataSetting',
                                                                                            'translationable_id' => $earning_seller_title->id,
                                                                                            'locale' => $key,
                                                                                            'key' => 'earning_seller_title'
                                                                                        ],
                                                                                        ['value' => $request->earning_seller_title[$index]]
                                                                                    );
                                                                                }
                                                                            }
                                                                            if ($default_lang == $key && !($request->earning_seller_sub_title[$index])) {
                                                                                if ($key != 'default') {
                                                                                    Translation::updateOrInsert(
                                                                                        [
                                                                                            'translationable_type' => 'App\Models\DataSetting',
                                                                                            'translationable_id' => $earning_seller_sub_title->id,
                                                                                            'locale' => $key,
                                                                                            'key' => 'earning_seller_sub_title'
                                                                                        ],
                                                                                        ['value' => $earning_seller_sub_title?->getRawOriginal('value')]
                                                                                    );
                                                                                }
                                                                            } else {
                                                                                if ($request->earning_seller_sub_title[$index] && $key != 'default') {
                                                                                    Translation::updateOrInsert(
                                                                                        [
                                                                                            'translationable_type' => 'App\Models\DataSetting',
                                                                                            'translationable_id' => $earning_seller_sub_title->id,
                                                                                            'locale' => $key,
                                                                                            'key' => 'earning_seller_sub_title'
                                                                                        ],
                                                                                        ['value' => $request->earning_seller_sub_title[$index]]
                                                                                    );
                                                                                }
                                                                            }
                                                                            if ($default_lang == $key && !($request->earning_seller_button_name[$index])) {
                                                                                if ($key != 'default') {
                                                                                    Translation::updateOrInsert(
                                                                                        [
                                                                                            'translationable_type' => 'App\Models\DataSetting',
                                                                                            'translationable_id' => $earning_seller_button_name->id,
                                                                                            'locale' => $key,
                                                                                            'key' => 'earning_seller_button_name'
                                                                                        ],
                                                                                        ['value' => $earning_seller_button_name?->getRawOriginal('value')]
                                                                                    );
                                                                                }
                                                                            } else {
                                                                                if ($request->earning_seller_button_name[$index] && $key != 'default') {
                                                                                    Translation::updateOrInsert(
                                                                                        [
                                                                                            'translationable_type' => 'App\Models\DataSetting',
                                                                                            'translationable_id' => $earning_seller_button_name->id,
                                                                                            'locale' => $key,
                                                                                            'key' => 'earning_seller_button_name'
                                                                                        ],
                                                                                        ['value' => $request->earning_seller_button_name[$index]]
                                                                                    );
                                                                                }
                                                                            }
                                                                        }
                                                                        Toastr::success(translate('messages.seller_links_updated'));
                                                                    } elseif ($tab == 'earning-dm-link') {
                                                                        
                                                                        if($request->join_DM_react_status !== null ){
                                                                            $join_DM_react_status = DataSetting::where('type', 'react_landing_page')->where('key', 'join_DM_react_status')->first();
                                                                            if ($join_DM_react_status == null) {
                                                                                $join_DM_react_status = new DataSetting();
                                                                            }
                                                                            
                                                                            $join_DM_react_status->key = 'join_DM_react_status';
                                                                            $join_DM_react_status->type = 'react_landing_page';
                                                                            $join_DM_react_status->value = $request->join_DM_react_status  ? 0 : 1 ;
                                                                            $join_DM_react_status->save();
                                                                            
                                                                            Toastr::success(translate('messages.Seller_Section_Content_status_updated'));
                                                                            return back();
                                                                        }
                                                                        $earning_dm_title = DataSetting::where('type', 'react_landing_page')->where('key', 'earning_dm_title')->first();
                                                                        if ($earning_dm_title == null) {
                                                                            $earning_dm_title = new DataSetting();
                                                                        }
                                                                        
                                                                        $earning_dm_title->key = 'earning_dm_title';
                                                                        $earning_dm_title->type = 'react_landing_page';
                                                                        $earning_dm_title->value = $request->earning_dm_title[array_search('default', $request->lang)];
                                                                        $earning_dm_title->save();
                                                                        
                                                                        $earning_dm_sub_title = DataSetting::where('type', 'react_landing_page')->where('key', 'earning_dm_sub_title')->first();
                                                                        if ($earning_dm_sub_title == null) {
                                                                            $earning_dm_sub_title = new DataSetting();
                                                                        }
                                                                        
                                                                        $earning_dm_sub_title->key = 'earning_dm_sub_title';
                                                                        $earning_dm_sub_title->type = 'react_landing_page';
                                                                        $earning_dm_sub_title->value = $request->earning_dm_sub_title[array_search('default', $request->lang)];
                                                                        $earning_dm_sub_title->save();
                                                                        
                                                                        $earning_dm_button_name = DataSetting::where('type', 'react_landing_page')->where('key', 'earning_dm_button_name')->first();
                                                                        if ($earning_dm_button_name == null) {
                                                                            $earning_dm_button_name = new DataSetting();
                                                                        }
                                                                        
                                                                        $earning_dm_button_name->key = 'earning_dm_button_name';
                                                                        $earning_dm_button_name->type = 'react_landing_page';
                                                                        $earning_dm_button_name->value = $request->earning_dm_button_name[array_search('default', $request->lang)];
                                                                        $earning_dm_button_name->save();
                                                                        
                                                                        $earning_dm_button_url = DataSetting::where('type', 'react_landing_page')->where('key', 'earning_dm_button_url')->first();
                                                                        if ($earning_dm_button_url == null) {
                                                                            $earning_dm_button_url = new DataSetting();
                                                                        }
                                                                        
                                                                        $earning_dm_button_url->key = 'earning_dm_button_url';
                                                                        $earning_dm_button_url->type = 'react_landing_page';
                                                                        $earning_dm_button_url->value = $request->earning_dm_button_url;
                                                                        $earning_dm_button_url->save();
                                                                        
                                                                        
                                                                        $data = [];
                                                                        $default_lang = str_replace('_', '-', app()->getLocale());
                                                                        foreach ($request->lang as $index => $key) {
                                                                            if ($default_lang == $key && !($request->earning_dm_title[$index])) {
                                                                                if ($key != 'default') {
                                                                                    Translation::updateOrInsert(
                                                                                        [
                                                                                            'translationable_type' => 'App\Models\DataSetting',
                                                                                            'translationable_id' => $earning_dm_title->id,
                                                                                            'locale' => $key,
                                                                                            'key' => 'earning_dm_title'
                                                                                        ],
                                                                                        ['value' => $earning_dm_title?->getRawOriginal('value')]
                                                                                    );
                                                                                }
                                                                            } else {
                                                                                if ($request->earning_dm_title[$index] && $key != 'default') {
                                                                                    Translation::updateOrInsert(
                                                                                        [
                                                                                            'translationable_type' => 'App\Models\DataSetting',
                                                                                            'translationable_id' => $earning_dm_title->id,
                                                                                            'locale' => $key,
                                                                                            'key' => 'earning_dm_title'
                                                                                        ],
                                                                                        ['value' => $request->earning_dm_title[$index]]
                                                                                    );
                                                                                }
                                                                            }
                                                                            if ($default_lang == $key && !($request->earning_dm_sub_title[$index])) {
                                                                                if ($key != 'default') {
                                                                                    Translation::updateOrInsert(
                                                                                        [
                                                                                            'translationable_type' => 'App\Models\DataSetting',
                                                                                            'translationable_id' => $earning_dm_sub_title->id,
                                                                                            'locale' => $key,
                                                                                            'key' => 'earning_dm_sub_title'
                                                                                        ],
                                                                                        ['value' => $earning_dm_sub_title?->getRawOriginal('value')]
                                                                                    );
                                                                                }
                                                                            } else {
                                                                                if ($request->earning_dm_sub_title[$index] && $key != 'default') {
                                                                                    Translation::updateOrInsert(
                                                                                        [
                                                                                            'translationable_type' => 'App\Models\DataSetting',
                                                                                            'translationable_id' => $earning_dm_sub_title->id,
                                                                                            'locale' => $key,
                                                                                            'key' => 'earning_dm_sub_title'
                                                                                        ],
                                                                                        ['value' => $request->earning_dm_sub_title[$index]]
                                                                                    );
                                                                                }
                                                                            }
                                                                            
                                                                            if ($default_lang == $key && !($request->earning_dm_button_name[$index])) {
                                                                                if ($key != 'default') {
                                                                                    Translation::updateOrInsert(
                                                                                        [
                                                                                            'translationable_type' => 'App\Models\DataSetting',
                                                                                            'translationable_id' => $earning_dm_button_name->id,
                                                                                            'locale' => $key,
                                                                                            'key' => 'earning_dm_button_name'
                                                                                        ],
                                                                                        ['value' => $earning_dm_button_name?->getRawOriginal('value')]
                                                                                    );
                                                                                }
                                                                            } else {
                                                                                if ($request->earning_dm_button_name[$index] && $key != 'default') {
                                                                                    Translation::updateOrInsert(
                                                                                        [
                                                                                            'translationable_type' => 'App\Models\DataSetting',
                                                                                            'translationable_id' => $earning_dm_button_name->id,
                                                                                            'locale' => $key,
                                                                                            'key' => 'earning_dm_button_name'
                                                                                        ],
                                                                                        ['value' => $request->earning_dm_button_name[$index]]
                                                                                    );
                                                                                }
                                                                            }
                                                                        }
                                                                        Toastr::success(translate('messages.delivery_man_links_updated'));
                                                                    } elseif ($tab == 'testimonial-title') {
                                                                        $testimonial_title = DataSetting::where('type', 'react_landing_page')->where('key', 'testimonial_title')->first();
                                                                        if ($testimonial_title == null) {
                                                                            $testimonial_title = new DataSetting();
                                                                        }
                                                                        
                                                                        $testimonial_title->key = 'testimonial_title';
                                                                        $testimonial_title->type = 'react_landing_page';
                                                                        $testimonial_title->value = $request->testimonial_title[array_search('default', $request->lang)];
                                                                        $testimonial_title->save();
                                                                        
                                                                        $data = [];
                                                                        $default_lang = str_replace('_', '-', app()->getLocale());
                                                                        foreach ($request->lang as $index => $key) {
                                                                            if ($default_lang == $key && !($request->testimonial_title[$index])) {
                                                                                if ($key != 'default') {
                                                                                    Translation::updateOrInsert(
                                                                                        [
                                                                                            'translationable_type' => 'App\Models\DataSetting',
                                                                                            'translationable_id' => $testimonial_title->id,
                                                                                            'locale' => $key,
                                                                                            'key' => 'testimonial_title'
                                                                                        ],
                                                                                        ['value' => $testimonial_title?->getRawOriginal('value')]
                                                                                    );
                                                                                }
                                                                            } else {
                                                                                if ($request->testimonial_title[$index] && $key != 'default') {
                                                                                    Translation::updateOrInsert(
                                                                                        [
                                                                                            'translationable_type' => 'App\Models\DataSetting',
                                                                                            'translationable_id' => $testimonial_title->id,
                                                                                            'locale' => $key,
                                                                                            'key' => 'testimonial_title'
                                                                                        ],
                                                                                        ['value' => $request->testimonial_title[$index]]
                                                                                    );
                                                                                }
                                                                            }
                                                                        }
                                                                        
                                                                        Toastr::success(translate('messages.testimonial_section_updated'));
                                                                    } elseif ($tab == 'testimonial-list') {
                                                                        $request->validate([
                                                                            'name' => 'required',
                                                                            'designation' => 'required',
                                                                            'review' => 'required',
                                                                            'reviewer_image' => 'required'
                                                                        ]);
                                                                        
                                                                        $testimonial = new ReactTestimonial();
                                                                        $testimonial->name = $request->name;
                                                                        $testimonial->designation = $request->designation;
                                                                        $testimonial->review = $request->review;
                                                                        $testimonial->reviewer_image = Helpers::upload('reviewer_image/', 'png', $request->file('reviewer_image'));
                                                                        $testimonial->company_image = Helpers::upload('reviewer_company_image/', 'png', $request->file('company_image'));
                                                                        $testimonial->save();
                                                                        Toastr::success(translate('messages.testimonial_added_successfully'));
                                                                    } elseif ($tab == 'business-section') {
                                                                        $business_title = DataSetting::where('type', 'react_landing_page')->where('key', 'business_title')->first();
                                                                        if ($business_title == null) {
                                                                            $business_title = new DataSetting();
                                                                        }
                                                                        
                                                                        $business_title->key = 'business_title';
                                                                        $business_title->type = 'react_landing_page';
                                                                        $business_title->value = $request->business_title[array_search('default', $request->lang)];
                                                                        $business_title->save();
                                                                        
                                                                        $business_sub_title = DataSetting::where('type', 'react_landing_page')->where('key', 'business_sub_title')->first();
                                                                        if ($business_sub_title == null) {
                                                                            $business_sub_title = new DataSetting();
                                                                        }
                                                                        
                                                                        $business_sub_title->key = 'business_sub_title';
                                                                        $business_sub_title->type = 'react_landing_page';
                                                                        $business_sub_title->value = $request->business_sub_title[array_search('default', $request->lang)];
                                                                        $business_sub_title->save();
                                                                        
                                                                        $business_image = DataSetting::where('type', 'react_landing_page')->where('key', 'business_image')->first();
                                                                        if ($business_image == null) {
                                                                            $business_image = new DataSetting();
                                                                        }
                                                                        $business_image->key = 'business_image';
                                                                        $business_image->type = 'react_landing_page';
                                                                        $business_image->value = $request->has('image') ? Helpers::update('business_image/', $business_image->value, 'png', $request->file('image')) : $business_image->value;
                                                                        $business_image->save();
                                                                        
                                                                        $data = [];
                                                                        $default_lang = str_replace('_', '-', app()->getLocale());
                                                                        foreach ($request->lang as $index => $key) {
                                                                            if ($default_lang == $key && !($request->business_title[$index])) {
                                                                                if ($key != 'default') {
                                                                                    Translation::updateOrInsert(
                                                                                        [
                                                                                            'translationable_type' => 'App\Models\DataSetting',
                                                                                            'translationable_id' => $business_title->id,
                                                                                            'locale' => $key,
                                                                                            'key' => 'business_title'
                                                                                        ],
                                                                                        ['value' => $business_title?->getRawOriginal('value')]
                                                                                    );
                                                                                }
                                                                            } else {
                                                                                if ($request->business_title[$index] && $key != 'default') {
                                                                                    Translation::updateOrInsert(
                                                                                        [
                                                                                            'translationable_type' => 'App\Models\DataSetting',
                                                                                            'translationable_id' => $business_title->id,
                                                                                            'locale' => $key,
                                                                                            'key' => 'business_title'
                                                                                        ],
                                                                                        ['value' => $request->business_title[$index]]
                                                                                    );
                                                                                }
                                                                            }
                                                                            if ($default_lang == $key && !($request->business_sub_title[$index])) {
                                                                                if ($key != 'default') {
                                                                                    Translation::updateOrInsert(
                                                                                        [
                                                                                            'translationable_type' => 'App\Models\DataSetting',
                                                                                            'translationable_id' => $business_sub_title->id,
                                                                                            'locale' => $key,
                                                                                            'key' => 'business_sub_title'
                                                                                        ],
                                                                                        ['value' => $business_sub_title?->getRawOriginal('value')]
                                                                                    );
                                                                                }
                                                                            } else {
                                                                                if ($request->business_sub_title[$index] && $key != 'default') {
                                                                                    Translation::updateOrInsert(
                                                                                        [
                                                                                            'translationable_type' => 'App\Models\DataSetting',
                                                                                            'translationable_id' => $business_sub_title->id,
                                                                                            'locale' => $key,
                                                                                            'key' => 'business_sub_title'
                                                                                        ],
                                                                                        ['value' => $request->business_sub_title[$index]]
                                                                                    );
                                                                                }
                                                                            }
                                                                        }
                                                                        
                                                                        Helpers::dataUpdateOrInsert(['key' => 'download_business_app_links', 'type' => 'react_landing_page'], [
                                                                            'value' => json_encode([
                                                                                'seller_playstore_url_status' => $request['seller_playstore_url_status'],
                                                                                'seller_playstore_url' => $request['seller_playstore_url'],
                                                                                'seller_appstore_url_status' => $request['seller_appstore_url_status'],
                                                                                'seller_appstore_url' => $request['seller_appstore_url'],
                                                                                'dm_playstore_url_status' => $request['dm_playstore_url_status'],
                                                                                'dm_playstore_url' => $request['dm_playstore_url'],
                                                                                'dm_appstore_url_status' => $request['dm_appstore_url_status'],
                                                                                'dm_appstore_url' => $request['dm_appstore_url'],
                                                                                ])
                                                                            ]);
                                                                            
                                                                            
                                                                            Toastr::success(translate('messages.business_section_updated'));
                                                                        } elseif ($tab == 'header-section') {
                                                                            $request->validate([
                                                                                'header_title.0' => 'required',
                                                                                'header_sub_title.0' => 'required'
                                                                            ], [
                                                                                'header_title.0.required' => translate('messages.Default_title_is_required'),
                                                                                'header_sub_title.0.required' => translate('messages.Default_subtitle_is_required')
                                                                            ]);
                                                                            $header_banner = DataSetting::where('type', 'react_landing_page')->where('key', 'header_banner')->first();
                                                                            if ($header_banner == null) {
                                                                                $header_banner = new DataSetting();
                                                                            }
                                                                            if (!$header_banner->value && !$request->has('banner_image')) {
                                                                                Toastr::error(translate('messages.Banner_image_is_required'));
                                                                                return back();
                                                                            }
                                                                            $header_title = DataSetting::where('type', 'react_landing_page')->where('key', 'header_title')->first();
                                                                            if ($header_title == null) {
                                                                                $header_title = new DataSetting();
                                                                            }
                                                                            
                                                                            $header_title->key = 'header_title';
                                                                            $header_title->type = 'react_landing_page';
                                                                            $header_title->value = $request->header_title[array_search('default', $request->lang)];
                                                                            $header_title->save();
                                                                            
                                                                            $header_sub_title = DataSetting::where('type', 'react_landing_page')->where('key', 'header_sub_title')->first();
                                                                            if ($header_sub_title == null) {
                                                                                $header_sub_title = new DataSetting();
                                                                            }
                                                                            
                                                                            $header_sub_title->key = 'header_sub_title';
                                                                            $header_sub_title->type = 'react_landing_page';
                                                                            $header_sub_title->value = $request->header_sub_title[array_search('default', $request->lang)];
                                                                            $header_sub_title->save();
                                                                            
                                                                            $header_tag_line = DataSetting::where('type', 'react_landing_page')->where('key', 'header_tag_line')->first();
                                                                            if ($header_tag_line == null) {
                                                                                $header_tag_line = new DataSetting();
                                                                            }
                                                                            
                                                                            $header_tag_line->key = 'header_tag_line';
                                                                            $header_tag_line->type = 'react_landing_page';
                                                                            $header_tag_line->value = $request->header_tag_line[array_search('default', $request->lang)];
                                                                            $header_tag_line->save();
                                                                            
                                                                            $header_icon = DataSetting::where('type', 'react_landing_page')->where('key', 'header_icon')->first();
                                                                            if ($header_icon == null) {
                                                                                $header_icon = new DataSetting();
                                                                            }
                                                                            $header_icon->key = 'header_icon';
                                                                            $header_icon->type = 'react_landing_page';
                                                                            $header_icon->value = $request->has('image') ? Helpers::update('header_icon/', $header_icon->value, 'png', $request->file('image')) : $header_icon->value;
                                                                            $header_icon->save();
                                                                            
                                                                            
                                                                            $header_banner->key = 'header_banner';
                                                                            $header_banner->type = 'react_landing_page';
                                                                            $header_banner->value = $request->has('banner_image') ? Helpers::update('header_banner/', $header_banner->value, 'png', $request->file('banner_image')) : $header_banner->value;
                                                                            $header_banner->save();
                                                                            
                                                                            $default_lang = str_replace('_', '-', app()->getLocale());
                                                                            foreach ($request->lang as $index => $key) {
                                                                                if ($default_lang == $key && !($request->header_title[$index])) {
                                                                                    if ($key != 'default') {
                                                                                        Translation::updateOrInsert(
                                                                                            [
                                                                                                'translationable_type' => 'App\Models\DataSetting',
                                                                                                'translationable_id' => $header_title->id,
                                                                                                'locale' => $key,
                                                                                                'key' => 'header_title'
                                                                                            ],
                                                                                            ['value' => $header_title?->getRawOriginal('value')]
                                                                                        );
                                                                                    }
                                                                                } else {
                                                                                    if ($request->header_title[$index] && $key != 'default') {
                                                                                        Translation::updateOrInsert(
                                                                                            [
                                                                                                'translationable_type' => 'App\Models\DataSetting',
                                                                                                'translationable_id' => $header_title->id,
                                                                                                'locale' => $key,
                                                                                                'key' => 'header_title'
                                                                                            ],
                                                                                            ['value' => $request->header_title[$index]]
                                                                                        );
                                                                                    }
                                                                                }
                                                                                if ($default_lang == $key && !($request->header_sub_title[$index])) {
                                                                                    if ($key != 'default') {
                                                                                        Translation::updateOrInsert(
                                                                                            [
                                                                                                'translationable_type' => 'App\Models\DataSetting',
                                                                                                'translationable_id' => $header_sub_title->id,
                                                                                                'locale' => $key,
                                                                                                'key' => 'header_sub_title'
                                                                                            ],
                                                                                            ['value' => $header_sub_title?->getRawOriginal('value')]
                                                                                        );
                                                                                    }
                                                                                } else {
                                                                                    if ($request->header_sub_title[$index] && $key != 'default') {
                                                                                        Translation::updateOrInsert(
                                                                                            [
                                                                                                'translationable_type' => 'App\Models\DataSetting',
                                                                                                'translationable_id' => $header_sub_title->id,
                                                                                                'locale' => $key,
                                                                                                'key' => 'header_sub_title'
                                                                                            ],
                                                                                            ['value' => $request->header_sub_title[$index]]
                                                                                        );
                                                                                    }
                                                                                }
                                                                                if ($default_lang == $key && !($request->header_tag_line[$index])) {
                                                                                    if ($key != 'default') {
                                                                                        Translation::updateOrInsert(
                                                                                            [
                                                                                                'translationable_type' => 'App\Models\DataSetting',
                                                                                                'translationable_id' => $header_tag_line->id,
                                                                                                'locale' => $key,
                                                                                                'key' => 'header_tag_line'
                                                                                            ],
                                                                                            ['value' => $header_tag_line->getRawOriginal('value')]
                                                                                        );
                                                                                    }
                                                                                } else {
                                                                                    if ($request->header_tag_line[$index] && $key != 'default') {
                                                                                        Translation::updateOrInsert(
                                                                                            [
                                                                                                'translationable_type' => 'App\Models\DataSetting',
                                                                                                'translationable_id' => $header_tag_line->id,
                                                                                                'locale' => $key,
                                                                                                'key' => 'header_tag_line'
                                                                                            ],
                                                                                            ['value' => $request->header_tag_line[$index]]
                                                                                        );
                                                                                    }
                                                                                }
                                                                            }
                                                                            
                                                                            Toastr::success(translate('messages.header_section_updated'));
                                                                        } elseif ($tab == 'company-section') {
                                                                            
                                                                            
                                                                            $request->validate([
                                                                                'company_title.0' => 'required',
                                                                                'company_sub_title.0' => 'required',
                                                                                'company_button_url' => 'required_unless:company_button_name.0,!=,null',
                                                                                'company_button_name.0' => 'required_unless:company_button_url,!=,null',
                                                                            ], [
                                                                                'company_title.0.required' => translate('messages.Default_title_is_required'),
                                                                                'company_sub_title.0.required' => translate('messages.Default_subtitle_is_required'),
                                                                                'company_button_name.0.required_unless' => translate('messages.Default_button_name_is_required'),
                                                                                'company_button_url.required_unless' => translate('messages.Button_redirec_url_is_required'),
                                                                            ]);
                                                                            
                                                                            $company_title = DataSetting::where('type', 'react_landing_page')->where('key', 'company_title')->first();
                                                                            if ($company_title == null) {
                                                                                $company_title = new DataSetting();
                                                                            }
                                                                            
                                                                            $company_title->key = 'company_title';
                                                                            $company_title->type = 'react_landing_page';
                                                                            $company_title->value = $request->company_title[array_search('default', $request->lang)];
                                                                            $company_title->save();
                                                                            
                                                                            $company_sub_title = DataSetting::where('type', 'react_landing_page')->where('key', 'company_sub_title')->first();
                                                                            if ($company_sub_title == null) {
                                                                                $company_sub_title = new DataSetting();
                                                                            }
                                                                            
                                                                            $company_sub_title->key = 'company_sub_title';
                                                                            $company_sub_title->type = 'react_landing_page';
                                                                            $company_sub_title->value = $request->company_sub_title[array_search('default', $request->lang)];
                                                                            $company_sub_title->save();
                                                                            
                                                                            $company_description = DataSetting::where('type', 'react_landing_page')->where('key', 'company_description')->first();
                                                                            if ($company_description == null) {
                                                                                $company_description = new DataSetting();
                                                                            }
                                                                            
                                                                            $company_description->key = 'company_description';
                                                                            $company_description->type = 'react_landing_page';
                                                                            $company_description->value = $request->company_description[array_search('default', $request->lang)];
                                                                            $company_description->save();
                                                                            
                                                                            $company_button_name = DataSetting::where('type', 'react_landing_page')->where('key', 'company_button_name')->first();
                                                                            if ($company_button_name == null) {
                                                                                $company_button_name = new DataSetting();
                                                                            }
                                                                            
                                                                            $company_button_name->key = 'company_button_name';
                                                                            $company_button_name->type = 'react_landing_page';
                                                                            $company_button_name->value = $request->company_button_name[array_search('default', $request->lang)];
                                                                            $company_button_name->save();
                                                                            
                                                                            $company_button_url = DataSetting::where('type', 'react_landing_page')->where('key', 'company_button_url')->first();
                                                                            if ($company_button_url == null) {
                                                                                $company_button_url = new DataSetting();
                                                                            }
                                                                            
                                                                            $company_button_url->key = 'company_button_url';
                                                                            $company_button_url->type = 'react_landing_page';
                                                                            $company_button_url->value = $request->company_button_url;
                                                                            $company_button_url->save();
                                                                            
                                                                            $default_lang = str_replace('_', '-', app()->getLocale());
                                                                            foreach ($request->lang as $index => $key) {
                                                                                if ($default_lang == $key && !($request->company_title[$index])) {
                                                                                    if ($key != 'default') {
                                                                                        Translation::updateOrInsert(
                                                                                            [
                                                                                                'translationable_type' => 'App\Models\DataSetting',
                                                                                                'translationable_id' => $company_title->id,
                                                                                                'locale' => $key,
                                                                                                'key' => 'company_title'
                                                                                            ],
                                                                                            ['value' => $company_title?->getRawOriginal('value')]
                                                                                        );
                                                                                    }
                                                                                } else {
                                                                                    if ($request->company_title[$index] && $key != 'default') {
                                                                                        Translation::updateOrInsert(
                                                                                            [
                                                                                                'translationable_type' => 'App\Models\DataSetting',
                                                                                                'translationable_id' => $company_title->id,
                                                                                                'locale' => $key,
                                                                                                'key' => 'company_title'
                                                                                            ],
                                                                                            ['value' => $request->company_title[$index]]
                                                                                        );
                                                                                    }
                                                                                }
                                                                                if ($default_lang == $key && !($request->company_sub_title[$index])) {
                                                                                    if ($key != 'default') {
                                                                                        Translation::updateOrInsert(
                                                                                            [
                                                                                                'translationable_type' => 'App\Models\DataSetting',
                                                                                                'translationable_id' => $company_sub_title->id,
                                                                                                'locale' => $key,
                                                                                                'key' => 'company_sub_title'
                                                                                            ],
                                                                                            ['value' => $company_sub_title?->getRawOriginal('value')]
                                                                                        );
                                                                                    }
                                                                                } else {
                                                                                    if ($request->company_sub_title[$index] && $key != 'default') {
                                                                                        Translation::updateOrInsert(
                                                                                            [
                                                                                                'translationable_type' => 'App\Models\DataSetting',
                                                                                                'translationable_id' => $company_sub_title->id,
                                                                                                'locale' => $key,
                                                                                                'key' => 'company_sub_title'
                                                                                            ],
                                                                                            ['value' => $request->company_sub_title[$index]]
                                                                                        );
                                                                                    }
                                                                                }
                                                                                if ($default_lang == $key && !($request->company_description[$index])) {
                                                                                    if ($key != 'default') {
                                                                                        Translation::updateOrInsert(
                                                                                            [
                                                                                                'translationable_type' => 'App\Models\DataSetting',
                                                                                                'translationable_id' => $company_description->id,
                                                                                                'locale' => $key,
                                                                                                'key' => 'company_description'
                                                                                            ],
                                                                                            ['value' => $company_description->getRawOriginal('value')]
                                                                                        );
                                                                                    }
                                                                                } else {
                                                                                    if ($request->company_description[$index] && $key != 'default') {
                                                                                        Translation::updateOrInsert(
                                                                                            [
                                                                                                'translationable_type' => 'App\Models\DataSetting',
                                                                                                'translationable_id' => $company_description->id,
                                                                                                'locale' => $key,
                                                                                                'key' => 'company_description'
                                                                                            ],
                                                                                            ['value' => $request->company_description[$index]]
                                                                                        );
                                                                                    }
                                                                                }
                                                                                if ($default_lang == $key && !($request->company_button_name[$index])) {
                                                                                    if ($key != 'default') {
                                                                                        Translation::updateOrInsert(
                                                                                            [
                                                                                                'translationable_type' => 'App\Models\DataSetting',
                                                                                                'translationable_id' => $company_button_name->id,
                                                                                                'locale' => $key,
                                                                                                'key' => 'company_button_name'
                                                                                            ],
                                                                                            ['value' => $company_button_name->getRawOriginal('value')]
                                                                                        );
                                                                                    }
                                                                                } else {
                                                                                    if ($request->company_button_name[$index] && $key != 'default') {
                                                                                        Translation::updateOrInsert(
                                                                                            [
                                                                                                'translationable_type' => 'App\Models\DataSetting',
                                                                                                'translationable_id' => $company_button_name->id,
                                                                                                'locale' => $key,
                                                                                                'key' => 'company_button_name'
                                                                                            ],
                                                                                            ['value' => $request->company_button_name[$index]]
                                                                                        );
                                                                                    }
                                                                                }
                                                                            }
                                                                            
                                                                            Toastr::success(translate('messages.company_section_updated'));
                                                                            
                                                                        } else if ($tab == 'promotion-banner') {
                                                                            if (!$request->has('image')) {
                                                                                Toastr::error(translate('messages.Banner_image_is_required'));
                                                                                return back();
                                                                            }
                                                                            $data = [];
                                                                            $imageName = null;
                                                                            $promotion_banner = DataSetting::firstOrNew(['key' => 'promotion_banner', 'type' => 'react_landing_page']);
                                                                            if ($promotion_banner) {
                                                                                $data = json_decode($promotion_banner->value, true);
                                                                            }
                                                                            if (count($data) >= 3) {
                                                                                Toastr::error(translate('messages.you_have_already_added_maximum_banner_image'));
                                                                                return back();
                                                                            }
                                                                            if ($request->has('image')) {
                                                                                $imageName = Helpers::upload('promotional_banner/', 'png', $request->file('image'));
                                                                            }
                                                                            array_push($data, [
                                                                                'img' => $imageName,
                                                                                'storage' => Helpers::getDisk(),
                                                                                // 'sub_title' => $request->sub_title,
                                                                            ]);
                                                                            $promotion_banner->value = json_encode($data);
                                                                            
                                                                            $promotion_banner->save();
                                                                            Toastr::success(translate('messages.landing_page_promotion_banner_updated'));
                                                                        } else if ($tab == 'fixed-banner') {
                                                                            $fixed_promotional_banner = DataSetting::where('type', 'react_landing_page')->where('key', 'fixed_promotional_banner')->first();
                                                                            if ($fixed_promotional_banner == null) {
                                                                                $fixed_promotional_banner = new DataSetting();
                                                                            }
                                                                            $fixed_promotional_banner->key = 'fixed_promotional_banner';
                                                                            $fixed_promotional_banner->type = 'react_landing_page';
                                                                            $fixed_promotional_banner->value = $request->has('fixed_promotional_banner') ? Helpers::update('promotional_banner/', $fixed_promotional_banner->value, 'png', $request->file('fixed_promotional_banner')) : $fixed_promotional_banner->value;
                                                                            $fixed_promotional_banner->save();
                                                                            Toastr::success(translate('messages.landing_page_promotion_banner_updated'));
                                                                        } else if ($tab == 'fixed-newsletter') {
                                                                            
                                                                            
                                                                            $request->validate([
                                                                                'fixed_newsletter_title.0' => 'required',
                                                                                'fixed_newsletter_sub_title.0' => 'required',
                                                                            ], [
                                                                                'fixed_newsletter_title.0.required' => translate('messages.Default_title_is_required'),
                                                                                'fixed_newsletter_sub_title.0.required' => translate('messages.Default_subtitle_is_required'),
                                                                                
                                                                            ]);
                                                                            
                                                                            
                                                                            $fixed_newsletter_title = DataSetting::where('type', 'react_landing_page')->where('key', 'fixed_newsletter_title')->first();
                                                                            if ($fixed_newsletter_title == null) {
                                                                                $fixed_newsletter_title = new DataSetting();
                                                                            }
                                                                            
                                                                            $fixed_newsletter_title->key = 'fixed_newsletter_title';
                                                                            $fixed_newsletter_title->type = 'react_landing_page';
                                                                            $fixed_newsletter_title->value = $request->fixed_newsletter_title[array_search('default', $request->lang)];
                                                                            $fixed_newsletter_title->save();
                                                                            
                                                                            $fixed_newsletter_sub_title = DataSetting::where('type', 'react_landing_page')->where('key', 'fixed_newsletter_sub_title')->first();
                                                                            if ($fixed_newsletter_sub_title == null) {
                                                                                $fixed_newsletter_sub_title = new DataSetting();
                                                                            }
                                                                            
                                                                            $fixed_newsletter_sub_title->key = 'fixed_newsletter_sub_title';
                                                                            $fixed_newsletter_sub_title->type = 'react_landing_page';
                                                                            $fixed_newsletter_sub_title->value = $request->fixed_newsletter_sub_title[array_search('default', $request->lang)];
                                                                            $fixed_newsletter_sub_title->save();
                                                                            
                                                                            $default_lang = str_replace('_', '-', app()->getLocale());
                                                                            foreach ($request->lang as $index => $key) {
                                                                                if ($default_lang == $key && !($request->fixed_newsletter_title[$index])) {
                                                                                    if ($key != 'default') {
                                                                                        Translation::updateOrInsert(
                                                                                            [
                                                                                                'translationable_type' => 'App\Models\DataSetting',
                                                                                                'translationable_id' => $fixed_newsletter_title->id,
                                                                                                'locale' => $key,
                                                                                                'key' => 'fixed_newsletter_title'
                                                                                            ],
                                                                                            ['value' => $fixed_newsletter_title?->getRawOriginal('value')]
                                                                                        );
                                                                                    }
                                                                                } else {
                                                                                    if ($request->fixed_newsletter_title[$index] && $key != 'default') {
                                                                                        Translation::updateOrInsert(
                                                                                            [
                                                                                                'translationable_type' => 'App\Models\DataSetting',
                                                                                                'translationable_id' => $fixed_newsletter_title->id,
                                                                                                'locale' => $key,
                                                                                                'key' => 'fixed_newsletter_title'
                                                                                            ],
                                                                                            ['value' => $request->fixed_newsletter_title[$index]]
                                                                                        );
                                                                                    }
                                                                                }
                                                                                if ($default_lang == $key && !($request->fixed_newsletter_sub_title[$index])) {
                                                                                    if ($key != 'default') {
                                                                                        Translation::updateOrInsert(
                                                                                            [
                                                                                                'translationable_type' => 'App\Models\DataSetting',
                                                                                                'translationable_id' => $fixed_newsletter_sub_title->id,
                                                                                                'locale' => $key,
                                                                                                'key' => 'fixed_newsletter_sub_title'
                                                                                            ],
                                                                                            ['value' => $fixed_newsletter_sub_title?->getRawOriginal('value')]
                                                                                        );
                                                                                    }
                                                                                } else {
                                                                                    if ($request->fixed_newsletter_sub_title[$index] && $key != 'default') {
                                                                                        Translation::updateOrInsert(
                                                                                            [
                                                                                                'translationable_type' => 'App\Models\DataSetting',
                                                                                                'translationable_id' => $fixed_newsletter_sub_title->id,
                                                                                                'locale' => $key,
                                                                                                'key' => 'fixed_newsletter_sub_title'
                                                                                            ],
                                                                                            ['value' => $request->fixed_newsletter_sub_title[$index]]
                                                                                        );
                                                                                    }
                                                                                }
                                                                            }
                                                                            
                                                                            Toastr::success(translate('messages.landing_page_newsletter_content_updated'));
                                                                        } else if ($tab == 'fixed-footer') {
                                                                            $fixed_footer_description = DataSetting::where('type', 'react_landing_page')->where('key', 'fixed_footer_description')->first();
                                                                            if ($fixed_footer_description == null) {
                                                                                $fixed_footer_description = new DataSetting();
                                                                            }
                                                                            
                                                                            $fixed_footer_description->key = 'fixed_footer_description';
                                                                            $fixed_footer_description->type = 'react_landing_page';
                                                                            $fixed_footer_description->value = $request->fixed_footer_description[array_search('default', $request->lang)];
                                                                            $fixed_footer_description->save();
                                                                            
                                                                            $default_lang = str_replace('_', '-', app()->getLocale());
                                                                            foreach ($request->lang as $index => $key) {
                                                                                if ($default_lang == $key && !($request->fixed_footer_description[$index])) {
                                                                                    if ($key != 'default') {
                                                                                        Translation::updateOrInsert(
                                                                                            [
                                                                                                'translationable_type' => 'App\Models\DataSetting',
                                                                                                'translationable_id' => $fixed_footer_description->id,
                                                                                                'locale' => $key,
                                                                                                'key' => 'fixed_footer_description'
                                                                                            ],
                                                                                            ['value' => $fixed_footer_description?->getRawOriginal('value')]
                                                                                        );
                                                                                    }
                                                                                } else {
                                                                                    if ($request->fixed_footer_description[$index] && $key != 'default') {
                                                                                        Translation::updateOrInsert(
                                                                                            [
                                                                                                'translationable_type' => 'App\Models\DataSetting',
                                                                                                'translationable_id' => $fixed_footer_description->id,
                                                                                                'locale' => $key,
                                                                                                'key' => 'fixed_footer_description'
                                                                                            ],
                                                                                            ['value' => $request->fixed_footer_description[$index]]
                                                                                        );
                                                                                    }
                                                                                }
                                                                            }
                                                                            
                                                                            Toastr::success(translate('messages.landing_page_footer_content_updated'));
                                                                        }
                                                                        return back();
                                                                    }
                                                                    
                                                                    public function delete_react_landing_page_settings($tab, $key)
                                                                    {
                                                                        if (env('APP_MODE') == 'demo') {
                                                                            Toastr::info(translate('messages.update_option_is_disable_for_demo'));
                                                                            return back();
                                                                        }
                                                                        
                                                                        $item = DataSetting::where('type', 'react_landing_page')->where('key', $tab)->first();
                                                                        $data = $item ? json_decode($item->value, true) : null;
                                                                        if ($data && array_key_exists($key, $data)) {
                                                                            if (isset($data[$key]['img'])) {
                                                                                Helpers::check_and_delete('promotion_banner/', $data[$key]['img']);
                                                                            }
                                                                            array_splice($data, $key, 1);
                                                                            
                                                                            $item->value = json_encode($data);
                                                                            $item->save();
                                                                            Toastr::success(translate('messages.' . $tab) . ' ' . translate('messages.deleted'));
                                                                            return back();
                                                                        }
                                                                        Toastr::error(translate('messages.not_found'));
                                                                        return back();
                                                                    }
                                                                    
                                                                    public function review_react_status(Request $request)
                                                                    {
                                                                        if (env('APP_MODE') == 'demo' && $request->id == 1) {
                                                                            Toastr::warning('Sorry!You can not inactive this review!');
                                                                            return back();
                                                                        }
                                                                        $review = ReactTestimonial::findOrFail($request->id);
                                                                        $review->status = $request->status;
                                                                        $review->save();
                                                                        Toastr::success(translate('messages.review_status_updated'));
                                                                        return back();
                                                                    }
                                                                    
                                                                    public function review_react_edit($id)
                                                                    {
                                                                        $review = ReactTestimonial::withoutGlobalScope('translate')->findOrFail($id);
                                                                        return view('admin-views.business-settings.landing-page-settings.react-landing-testimonial-edit', compact('review'));
                                                                    }
                                                                    
                                                                    public function review_react_update(Request $request, $id)
                                                                    {
                                                                        $request->validate([
                                                                            'name' => 'required',
                                                                            'designation' => 'required',
                                                                            'review' => 'required',
                                                                        ]);
                                                                        
                                                                        $review = ReactTestimonial::findOrFail($id);
                                                                        $review->name = $request->name;
                                                                        $review->designation = $request->designation;
                                                                        $review->review = $request->review;
                                                                        $review->reviewer_image = $request->has('reviewer_image') ? Helpers::update('reviewer_image/', $review->reviewer_image, 'png', $request->file('reviewer_image')) : $review->reviewer_image;
                                                                        $review->company_image = $request->has('company_image') ? Helpers::update('reviewer_company_image/', $review->company_image, 'png', $request->file('company_image')) : $review->company_image;
                                                                        $review->save();
                                                                        
                                                                        Toastr::success(translate('messages.review_updated_successfully'));
                                                                        return back();
                                                                    }
                                                                    
                                                                    public function review_react_destroy(ReactTestimonial $review)
                                                                    {
                                                                        if (env('APP_MODE') == 'demo' && $review->id == 1) {
                                                                            Toastr::warning(translate('messages.you_can_not_delete_this_review_please_add_a_new_review_to_delete'));
                                                                            return back();
                                                                        }
                                                                        $review->delete();
                                                                        Toastr::success(translate('messages.review_deleted_successfully'));
                                                                        return back();
                                                                    }
                                                                    
                                                                    public function flutter_landing_page_settings($tab)
                                                                    {
                                                                        if ($tab == 'fixed-data') {
                                                                            return view('admin-views.business-settings.landing-page-settings.flutter-fixed-data');
                                                                        } else if ($tab == 'special-criteria') {
                                                                            return view('admin-views.business-settings.landing-page-settings.flutter-landing-page-special-criteria');
                                                                        } else if ($tab == 'join-as') {
                                                                            return view('admin-views.business-settings.landing-page-settings.flutter-landing-page-join-as');
                                                                        } else if ($tab == 'available-zone') {
                                                                            return view('admin-views.business-settings.landing-page-settings.flutter-landing-page-available-zone');
                                                                        } else if ($tab == 'download-apps') {
                                                                            return view('admin-views.business-settings.landing-page-settings.flutter-download-apps');
                                                                        }
                                                                    }
                                                                    
                                                                    public function update_flutter_landing_page_settings(Request $request, $tab)
                                                                    {
                                                                        if (env('APP_MODE') == 'demo') {
                                                                            Toastr::info(translate('messages.update_option_is_disable_for_demo'));
                                                                            return back();
                                                                        }
                                                                        
                                                                        if ($tab == 'special-criteria-list') {
                                                                            $request->validate([
                                                                                'title' => 'required',
                                                                                'image' => 'required',
                                                                            ]);
                                                                            if ($request->title[array_search('default', $request->lang)] == '') {
                                                                                Toastr::error(translate('default_data_is_required'));
                                                                                return back();
                                                                            }
                                                                            $criteria = new FlutterSpecialCriteria();
                                                                            $criteria->title = $request->title[array_search('default', $request->lang)];
                                                                            $criteria->image = Helpers::upload('special_criteria/', 'png', $request->file('image'));
                                                                            $criteria->save();
                                                                            $default_lang = str_replace('_', '-', app()->getLocale());
                                                                            $data = [];
                                                                            foreach ($request->lang as $index => $key) {
                                                                                if ($default_lang == $key && !($request->title[$index])) {
                                                                                    if ($key != 'default') {
                                                                                        Translation::updateOrInsert(
                                                                                            [
                                                                                                'translationable_type' => 'App\Models\FlutterSpecialCriteria',
                                                                                                'translationable_id' => $criteria->id,
                                                                                                'locale' => $key,
                                                                                                'key' => 'title'
                                                                                            ],
                                                                                            ['value' => $criteria->title]
                                                                                        );
                                                                                    }
                                                                                } else {
                                                                                    
                                                                                    if ($request->title[$index] && $key != 'default') {
                                                                                        Translation::updateOrInsert(
                                                                                            [
                                                                                                'translationable_type' => 'App\Models\FlutterSpecialCriteria',
                                                                                                'translationable_id' => $criteria->id,
                                                                                                'locale' => $key,
                                                                                                'key' => 'title'
                                                                                            ],
                                                                                            ['value' => $request->title[$index]]
                                                                                        );
                                                                                    }
                                                                                }
                                                                            }
                                                                            
                                                                            Toastr::success(translate('messages.criteria_added_successfully'));
                                                                        } elseif ($tab == 'available-zone-section') {
                                                                            if($request['available_zone_status']){
                                                                                $request->validate([
                                                                                    'available_zone_title.0' => 'required',
                                                                                    
                                                                                ], [
                                                                                    'available_zone_title.0.required' => translate('default_title_is_required'),
                                                                                ]);
                                                                            }
                                                                            $available_zone_title = DataSetting::where('type', 'flutter_landing_page')->where('key', 'available_zone_title')->first();
                                                                            if ($available_zone_title == null) {
                                                                                $available_zone_title = new DataSetting();
                                                                            }
                                                                            
                                                                            $available_zone_title->key = 'available_zone_title';
                                                                            $available_zone_title->type = 'flutter_landing_page';
                                                                            $available_zone_title->value = $request->available_zone_title[array_search('default', $request->lang)];
                                                                            $available_zone_title->save();
                                                                            
                                                                            $available_zone_short_description = DataSetting::where('type', 'flutter_landing_page')->where('key', 'available_zone_short_description')->first();
                                                                            if ($available_zone_short_description == null) {
                                                                                $available_zone_short_description = new DataSetting();
                                                                            }
                                                                            
                                                                            $available_zone_short_description->key = 'available_zone_short_description';
                                                                            $available_zone_short_description->type = 'flutter_landing_page';
                                                                            $available_zone_short_description->value = $request->available_zone_short_description[array_search('default', $request->lang)];
                                                                            $available_zone_short_description->save();
                                                                            
                                                                            $available_zone_image = DataSetting::where('type', 'flutter_landing_page')->where('key', 'available_zone_image')->first();
                                                                            
                                                                            if ($available_zone_image == null) {
                                                                                if($request['available_zone_status']){
                                                                                    $request->validate([
                                                                                        'image' => 'required',
                                                                                    ]);
                                                                                }
                                                                                
                                                                                $available_zone_image = new DataSetting();
                                                                            }
                                                                            $available_zone_image->key = 'available_zone_image';
                                                                            $available_zone_image->type = 'flutter_landing_page';
                                                                            $available_zone_image->value = $request->has('image') ? Helpers::update('available_zone_image/', $available_zone_image->value, 'png', $request->file('image')) : $available_zone_image->value;
                                                                            $available_zone_image->save();
                                                                            
                                                                            $data = [];
                                                                            $default_lang = str_replace('_', '-', app()->getLocale());
                                                                            foreach ($request->lang as $index => $key) {
                                                                                if ($default_lang == $key && !($request->available_zone_title[$index])) {
                                                                                    if ($key != 'default') {
                                                                                        Translation::updateOrInsert(
                                                                                            [
                                                                                                'translationable_type' => 'App\Models\DataSetting',
                                                                                                'translationable_id' => $available_zone_title->id,
                                                                                                'locale' => $key,
                                                                                                'key' => 'available_zone_title'
                                                                                            ],
                                                                                            ['value' => $available_zone_title?->getRawOriginal('value')]
                                                                                        );
                                                                                    }
                                                                                } else {
                                                                                    if ($request->available_zone_title[$index] && $key != 'default') {
                                                                                        Translation::updateOrInsert(
                                                                                            [
                                                                                                'translationable_type' => 'App\Models\DataSetting',
                                                                                                'translationable_id' => $available_zone_title->id,
                                                                                                'locale' => $key,
                                                                                                'key' => 'available_zone_title'
                                                                                            ],
                                                                                            ['value' => $request->available_zone_title[$index]]
                                                                                        );
                                                                                    }
                                                                                }
                                                                                if ($default_lang == $key && !($request->available_zone_short_description[$index])) {
                                                                                    if ($key != 'default') {
                                                                                        Translation::updateOrInsert(
                                                                                            [
                                                                                                'translationable_type' => 'App\Models\DataSetting',
                                                                                                'translationable_id' => $available_zone_short_description->id,
                                                                                                'locale' => $key,
                                                                                                'key' => 'available_zone_short_description'
                                                                                            ],
                                                                                            ['value' => $available_zone_short_description?->getRawOriginal('value')]
                                                                                        );
                                                                                    }
                                                                                } else {
                                                                                    if ($request->available_zone_short_description[$index] && $key != 'default') {
                                                                                        Translation::updateOrInsert(
                                                                                            [
                                                                                                'translationable_type' => 'App\Models\DataSetting',
                                                                                                'translationable_id' => $available_zone_short_description->id,
                                                                                                'locale' => $key,
                                                                                                'key' => 'available_zone_short_description'
                                                                                            ],
                                                                                            ['value' => $request->available_zone_short_description[$index]]
                                                                                        );
                                                                                    }
                                                                                }
                                                                            }
                                                                            
                                                                            Helpers::dataUpdateOrInsert(['type' => 'flutter_landing_page','key' => 'available_zone_status'], [
                                                                                'value' => $request['available_zone_status']
                                                                            ]);
                                                                            
                                                                            
                                                                            Toastr::success(translate('messages.available_zone_section_updated'));
                                                                        } elseif ($tab == 'download-app-section') {
                                                                            $download_user_app_title = DataSetting::where('type', 'flutter_landing_page')->where('key', 'download_user_app_title')->first();
                                                                            if ($download_user_app_title == null) {
                                                                                $download_user_app_title = new DataSetting();
                                                                            }
                                                                            
                                                                            $download_user_app_title->key = 'download_user_app_title';
                                                                            $download_user_app_title->type = 'flutter_landing_page';
                                                                            $download_user_app_title->value = $request->download_user_app_title[array_search('default', $request->lang)];
                                                                            $download_user_app_title->save();
                                                                            
                                                                            $download_user_app_sub_title = DataSetting::where('type', 'flutter_landing_page')->where('key', 'download_user_app_sub_title')->first();
                                                                            if ($download_user_app_sub_title == null) {
                                                                                $download_user_app_sub_title = new DataSetting();
                                                                            }
                                                                            
                                                                            $download_user_app_sub_title->key = 'download_user_app_sub_title';
                                                                            $download_user_app_sub_title->type = 'flutter_landing_page';
                                                                            $download_user_app_sub_title->value = $request->download_user_app_sub_title[array_search('default', $request->lang)];
                                                                            $download_user_app_sub_title->save();
                                                                            
                                                                            $download_user_app_image = DataSetting::where('type', 'flutter_landing_page')->where('key', 'download_user_app_image')->first();
                                                                            if ($download_user_app_image == null) {
                                                                                $download_user_app_image = new DataSetting();
                                                                            }
                                                                            $download_user_app_image->key = 'download_user_app_image';
                                                                            $download_user_app_image->type = 'flutter_landing_page';
                                                                            $download_user_app_image->value = $request->has('image') ? Helpers::update('download_user_app_image/', $download_user_app_image->value, 'png', $request->file('image')) : $download_user_app_image->value;
                                                                            $download_user_app_image->save();
                                                                            
                                                                            $data = [];
                                                                            $default_lang = str_replace('_', '-', app()->getLocale());
                                                                            foreach ($request->lang as $index => $key) {
                                                                                if ($default_lang == $key && !($request->download_user_app_title[$index])) {
                                                                                    if ($key != 'default') {
                                                                                        Translation::updateOrInsert(
                                                                                            [
                                                                                                'translationable_type' => 'App\Models\DataSetting',
                                                                                                'translationable_id' => $download_user_app_title->id,
                                                                                                'locale' => $key,
                                                                                                'key' => 'download_user_app_title'
                                                                                            ],
                                                                                            ['value' => $download_user_app_title?->getRawOriginal('value')]
                                                                                        );
                                                                                    }
                                                                                } else {
                                                                                    if ($request->download_user_app_title[$index] && $key != 'default') {
                                                                                        Translation::updateOrInsert(
                                                                                            [
                                                                                                'translationable_type' => 'App\Models\DataSetting',
                                                                                                'translationable_id' => $download_user_app_title->id,
                                                                                                'locale' => $key,
                                                                                                'key' => 'download_user_app_title'
                                                                                            ],
                                                                                            ['value' => $request->download_user_app_title[$index]]
                                                                                        );
                                                                                    }
                                                                                }
                                                                                if ($default_lang == $key && !($request->download_user_app_sub_title[$index])) {
                                                                                    if ($key != 'default') {
                                                                                        Translation::updateOrInsert(
                                                                                            [
                                                                                                'translationable_type' => 'App\Models\DataSetting',
                                                                                                'translationable_id' => $download_user_app_sub_title->id,
                                                                                                'locale' => $key,
                                                                                                'key' => 'download_user_app_sub_title'
                                                                                            ],
                                                                                            ['value' => $download_user_app_sub_title?->getRawOriginal('value')]
                                                                                        );
                                                                                    }
                                                                                } else {
                                                                                    if ($request->download_user_app_sub_title[$index] && $key != 'default') {
                                                                                        Translation::updateOrInsert(
                                                                                            [
                                                                                                'translationable_type' => 'App\Models\DataSetting',
                                                                                                'translationable_id' => $download_user_app_sub_title->id,
                                                                                                'locale' => $key,
                                                                                                'key' => 'download_user_app_sub_title'
                                                                                            ],
                                                                                            ['value' => $request->download_user_app_sub_title[$index]]
                                                                                        );
                                                                                    }
                                                                                }
                                                                            }
                                                                            
                                                                            Helpers::dataUpdateOrInsert(['key' => 'download_user_app_links', 'type' => 'flutter_landing_page'], [
                                                                                'value' => json_encode([
                                                                                    'playstore_url_status' => $request['playstore_url_status'],
                                                                                    'playstore_url' => $request['playstore_url'],
                                                                                    'apple_store_url_status' => $request['apple_store_url_status'],
                                                                                    'apple_store_url' => $request['apple_store_url']
                                                                                    ])
                                                                                ]);
                                                                                
                                                                                
                                                                                Toastr::success(translate('messages.download_app_section_updated'));
                                                                            } elseif ($tab == 'fixed-header') {
                                                                                
                                                                                $fixed_header_title = DataSetting::where('type', 'flutter_landing_page')->where('key', 'fixed_header_title')->first();
                                                                                if ($fixed_header_title == null) {
                                                                                    $fixed_header_title = new DataSetting();
                                                                                }
                                                                                
                                                                                $fixed_header_title->key = 'fixed_header_title';
                                                                                $fixed_header_title->type = 'flutter_landing_page';
                                                                                $fixed_header_title->value = $request->fixed_header_title[array_search('default', $request->lang)];
                                                                                $fixed_header_title->save();
                                                                                
                                                                                $fixed_header_sub_title = DataSetting::where('type', 'flutter_landing_page')->where('key', 'fixed_header_sub_title')->first();
                                                                                if ($fixed_header_sub_title == null) {
                                                                                    $fixed_header_sub_title = new DataSetting();
                                                                                }
                                                                                
                                                                                $fixed_header_sub_title->key = 'fixed_header_sub_title';
                                                                                $fixed_header_sub_title->type = 'flutter_landing_page';
                                                                                $fixed_header_sub_title->value = $request->fixed_header_sub_title[array_search('default', $request->lang)];
                                                                                $fixed_header_sub_title->save();
                                                                                
                                                                                $fixed_header_image = DataSetting::where('type', 'flutter_landing_page')->where('key', 'fixed_header_image')->first();
                                                                                if ($fixed_header_image == null) {
                                                                                    $fixed_header_image = new DataSetting();
                                                                                }
                                                                                $fixed_header_image->key = 'fixed_header_image';
                                                                                $fixed_header_image->type = 'flutter_landing_page';
                                                                                $fixed_header_image->value = $request->has('image') ? Helpers::update('fixed_header_image/', $fixed_header_image->value, 'png', $request->file('image')) : $fixed_header_image->value;
                                                                                $fixed_header_image->save();
                                                                                
                                                                                $data = [];
                                                                                $default_lang = str_replace('_', '-', app()->getLocale());
                                                                                foreach ($request->lang as $index => $key) {
                                                                                    if ($default_lang == $key && !($request->fixed_header_title[$index])) {
                                                                                        if ($key != 'default') {
                                                                                            Translation::updateOrInsert(
                                                                                                [
                                                                                                    'translationable_type' => 'App\Models\DataSetting',
                                                                                                    'translationable_id' => $fixed_header_title->id,
                                                                                                    'locale' => $key,
                                                                                                    'key' => 'fixed_header_title'
                                                                                                ],
                                                                                                ['value' => $fixed_header_title?->getRawOriginal('value')]
                                                                                            );
                                                                                        }
                                                                                    } else {
                                                                                        if ($request->fixed_header_title[$index] && $key != 'default') {
                                                                                            Translation::updateOrInsert(
                                                                                                [
                                                                                                    'translationable_type' => 'App\Models\DataSetting',
                                                                                                    'translationable_id' => $fixed_header_title->id,
                                                                                                    'locale' => $key,
                                                                                                    'key' => 'fixed_header_title'
                                                                                                ],
                                                                                                ['value' => $request->fixed_header_title[$index]]
                                                                                            );
                                                                                        }
                                                                                    }
                                                                                    if ($default_lang == $key && !($request->fixed_header_sub_title[$index])) {
                                                                                        if ($key != 'default') {
                                                                                            Translation::updateOrInsert(
                                                                                                [
                                                                                                    'translationable_type' => 'App\Models\DataSetting',
                                                                                                    'translationable_id' => $fixed_header_sub_title->id,
                                                                                                    'locale' => $key,
                                                                                                    'key' => 'fixed_header_sub_title'
                                                                                                ],
                                                                                                ['value' => $fixed_header_sub_title?->getRawOriginal('value')]
                                                                                            );
                                                                                        }
                                                                                    } else {
                                                                                        if ($request->fixed_header_sub_title[$index] && $key != 'default') {
                                                                                            Translation::updateOrInsert(
                                                                                                [
                                                                                                    'translationable_type' => 'App\Models\DataSetting',
                                                                                                    'translationable_id' => $fixed_header_sub_title->id,
                                                                                                    'locale' => $key,
                                                                                                    'key' => 'fixed_header_sub_title'
                                                                                                ],
                                                                                                ['value' => $request->fixed_header_sub_title[$index]]
                                                                                            );
                                                                                        }
                                                                                    }
                                                                                }
                                                                                
                                                                                Toastr::success(translate('messages.landing_page_header_updated'));
                                                                            } elseif ($tab == 'fixed-location') {
                                                                                
                                                                                $fixed_location_title = DataSetting::where('type', 'flutter_landing_page')->where('key', 'fixed_location_title')->first();
                                                                                if ($fixed_location_title == null) {
                                                                                    $fixed_location_title = new DataSetting();
                                                                                }
                                                                                
                                                                                $fixed_location_title->key = 'fixed_location_title';
                                                                                $fixed_location_title->type = 'flutter_landing_page';
                                                                                $fixed_location_title->value = $request->fixed_location_title[array_search('default', $request->lang)];
                                                                                $fixed_location_title->save();
                                                                                
                                                                                $data = [];
                                                                                $default_lang = str_replace('_', '-', app()->getLocale());
                                                                                foreach ($request->lang as $index => $key) {
                                                                                    if ($default_lang == $key && !($request->fixed_location_title[$index])) {
                                                                                        if ($key != 'default') {
                                                                                            Translation::updateOrInsert(
                                                                                                [
                                                                                                    'translationable_type' => 'App\Models\DataSetting',
                                                                                                    'translationable_id' => $fixed_location_title->id,
                                                                                                    'locale' => $key,
                                                                                                    'key' => 'fixed_location_title'
                                                                                                ],
                                                                                                ['value' => $fixed_location_title?->getRawOriginal('value')]
                                                                                            );
                                                                                        }
                                                                                    } else {
                                                                                        if ($request->fixed_location_title[$index] && $key != 'default') {
                                                                                            Translation::updateOrInsert(
                                                                                                [
                                                                                                    'translationable_type' => 'App\Models\DataSetting',
                                                                                                    'translationable_id' => $fixed_location_title->id,
                                                                                                    'locale' => $key,
                                                                                                    'key' => 'fixed_location_title'
                                                                                                ],
                                                                                                ['value' => $request->fixed_location_title[$index]]
                                                                                            );
                                                                                        }
                                                                                    }
                                                                                }
                                                                                
                                                                                Toastr::success(translate('messages.landing_page_location_title_updated'));
                                                                            } elseif ($tab == 'fixed-module') {
                                                                                
                                                                                $fixed_module_title = DataSetting::where('type', 'flutter_landing_page')->where('key', 'fixed_module_title')->first();
                                                                                if ($fixed_module_title == null) {
                                                                                    $fixed_module_title = new DataSetting();
                                                                                }
                                                                                
                                                                                $fixed_module_title->key = 'fixed_module_title';
                                                                                $fixed_module_title->type = 'flutter_landing_page';
                                                                                $fixed_module_title->value = $request->fixed_module_title[array_search('default', $request->lang)];
                                                                                $fixed_module_title->save();
                                                                                
                                                                                $fixed_module_sub_title = DataSetting::where('type', 'flutter_landing_page')->where('key', 'fixed_module_sub_title')->first();
                                                                                if ($fixed_module_sub_title == null) {
                                                                                    $fixed_module_sub_title = new DataSetting();
                                                                                }
                                                                                
                                                                                $fixed_module_sub_title->key = 'fixed_module_sub_title';
                                                                                $fixed_module_sub_title->type = 'flutter_landing_page';
                                                                                $fixed_module_sub_title->value = $request->fixed_module_sub_title[array_search('default', $request->lang)];
                                                                                $fixed_module_sub_title->save();
                                                                                
                                                                                $data = [];
                                                                                $default_lang = str_replace('_', '-', app()->getLocale());
                                                                                foreach ($request->lang as $index => $key) {
                                                                                    if ($default_lang == $key && !($request->fixed_module_title[$index])) {
                                                                                        if ($key != 'default') {
                                                                                            Translation::updateOrInsert(
                                                                                                [
                                                                                                    'translationable_type' => 'App\Models\DataSetting',
                                                                                                    'translationable_id' => $fixed_module_title->id,
                                                                                                    'locale' => $key,
                                                                                                    'key' => 'fixed_module_title'
                                                                                                ],
                                                                                                ['value' => $fixed_module_title?->getRawOriginal('value')]
                                                                                            );
                                                                                        }
                                                                                    } else {
                                                                                        if ($request->fixed_module_title[$index] && $key != 'default') {
                                                                                            Translation::updateOrInsert(
                                                                                                [
                                                                                                    'translationable_type' => 'App\Models\DataSetting',
                                                                                                    'translationable_id' => $fixed_module_title->id,
                                                                                                    'locale' => $key,
                                                                                                    'key' => 'fixed_module_title'
                                                                                                ],
                                                                                                ['value' => $request->fixed_module_title[$index]]
                                                                                            );
                                                                                        }
                                                                                    }
                                                                                    if ($default_lang == $key && !($request->fixed_module_sub_title[$index])) {
                                                                                        if ($key != 'default') {
                                                                                            Translation::updateOrInsert(
                                                                                                [
                                                                                                    'translationable_type' => 'App\Models\DataSetting',
                                                                                                    'translationable_id' => $fixed_module_sub_title->id,
                                                                                                    'locale' => $key,
                                                                                                    'key' => 'fixed_module_sub_title'
                                                                                                ],
                                                                                                ['value' => $fixed_module_sub_title?->getRawOriginal('value')]
                                                                                            );
                                                                                        }
                                                                                    } else {
                                                                                        if ($request->fixed_module_sub_title[$index] && $key != 'default') {
                                                                                            Translation::updateOrInsert(
                                                                                                [
                                                                                                    'translationable_type' => 'App\Models\DataSetting',
                                                                                                    'translationable_id' => $fixed_module_sub_title->id,
                                                                                                    'locale' => $key,
                                                                                                    'key' => 'fixed_module_sub_title'
                                                                                                ],
                                                                                                ['value' => $request->fixed_module_sub_title[$index]]
                                                                                            );
                                                                                        }
                                                                                    }
                                                                                }
                                                                                
                                                                                Toastr::success(translate('messages.landing_page_module_updated'));
                                                                            } elseif ($tab == 'join-seller') {
                                                                                
                                                                                if($request->join_seller_flutter_status !== null ){
                                                                                    $join_seller_flutter_status = DataSetting::where('type', 'flutter_landing_page')->where('key', 'join_seller_flutter_status')->first();
                                                                                    if ($join_seller_flutter_status == null) {
                                                                                        $join_seller_flutter_status = new DataSetting();
                                                                                    }
                                                                                    
                                                                                    $join_seller_flutter_status->key = 'join_seller_flutter_status';
                                                                                    $join_seller_flutter_status->type = 'flutter_landing_page';
                                                                                    $join_seller_flutter_status->value = $request->join_seller_flutter_status  ? 0 : 1 ;
                                                                                    $join_seller_flutter_status->save();
                                                                                    
                                                                                    Toastr::success(translate('messages.join_as_seller_section_status_updated'));
                                                                                    return back();
                                                                                }
                                                                                
                                                                                $join_seller_title = DataSetting::where('type', 'flutter_landing_page')->where('key', 'join_seller_title')->first();
                                                                                if ($join_seller_title == null) {
                                                                                    $join_seller_title = new DataSetting();
                                                                                }
                                                                                
                                                                                $join_seller_title->key = 'join_seller_title';
                                                                                $join_seller_title->type = 'flutter_landing_page';
                                                                                $join_seller_title->value = $request->join_seller_title[array_search('default', $request->lang)];
                                                                                $join_seller_title->save();
                                                                                
                                                                                $join_seller_sub_title = DataSetting::where('type', 'flutter_landing_page')->where('key', 'join_seller_sub_title')->first();
                                                                                if ($join_seller_sub_title == null) {
                                                                                    $join_seller_sub_title = new DataSetting();
                                                                                }
                                                                                
                                                                                $join_seller_sub_title->key = 'join_seller_sub_title';
                                                                                $join_seller_sub_title->type = 'flutter_landing_page';
                                                                                $join_seller_sub_title->value = $request->join_seller_sub_title[array_search('default', $request->lang)];
                                                                                $join_seller_sub_title->save();
                                                                                
                                                                                $join_seller_button_name = DataSetting::where('type', 'flutter_landing_page')->where('key', 'join_seller_button_name')->first();
                                                                                if ($join_seller_button_name == null) {
                                                                                    $join_seller_button_name = new DataSetting();
                                                                                }
                                                                                
                                                                                $join_seller_button_name->key = 'join_seller_button_name';
                                                                                $join_seller_button_name->type = 'flutter_landing_page';
                                                                                $join_seller_button_name->value = $request->join_seller_button_name[array_search('default', $request->lang)];
                                                                                $join_seller_button_name->save();
                                                                                
                                                                                $join_seller_button_url = DataSetting::where('type', 'flutter_landing_page')->where('key', 'join_seller_button_url')->first();
                                                                                if ($join_seller_button_url == null) {
                                                                                    $join_seller_button_url = new DataSetting();
                                                                                }
                                                                                
                                                                                $join_seller_button_url->key = 'join_seller_button_url';
                                                                                $join_seller_button_url->type = 'flutter_landing_page';
                                                                                $join_seller_button_url->value = $request->join_seller_button_url;
                                                                                $join_seller_button_url->save();
                                                                                
                                                                                $data = [];
                                                                                $default_lang = str_replace('_', '-', app()->getLocale());
                                                                                foreach ($request->lang as $index => $key) {
                                                                                    if ($default_lang == $key && !($request->join_seller_title[$index])) {
                                                                                        if ($key != 'default') {
                                                                                            Translation::updateOrInsert(
                                                                                                [
                                                                                                    'translationable_type' => 'App\Models\DataSetting',
                                                                                                    'translationable_id' => $join_seller_title->id,
                                                                                                    'locale' => $key,
                                                                                                    'key' => 'join_seller_title'
                                                                                                ],
                                                                                                ['value' => $join_seller_title?->getRawOriginal('value')]
                                                                                            );
                                                                                        }
                                                                                    } else {
                                                                                        if ($request->join_seller_title[$index] && $key != 'default') {
                                                                                            Translation::updateOrInsert(
                                                                                                [
                                                                                                    'translationable_type' => 'App\Models\DataSetting',
                                                                                                    'translationable_id' => $join_seller_title->id,
                                                                                                    'locale' => $key,
                                                                                                    'key' => 'join_seller_title'
                                                                                                ],
                                                                                                ['value' => $request->join_seller_title[$index]]
                                                                                            );
                                                                                        }
                                                                                    }
                                                                                    if ($default_lang == $key && !($request->join_seller_sub_title[$index])) {
                                                                                        if ($key != 'default') {
                                                                                            Translation::updateOrInsert(
                                                                                                [
                                                                                                    'translationable_type' => 'App\Models\DataSetting',
                                                                                                    'translationable_id' => $join_seller_sub_title->id,
                                                                                                    'locale' => $key,
                                                                                                    'key' => 'join_seller_sub_title'
                                                                                                ],
                                                                                                ['value' => $join_seller_sub_title?->getRawOriginal('value')]
                                                                                            );
                                                                                        }
                                                                                    } else {
                                                                                        if ($request->join_seller_sub_title[$index] && $key != 'default') {
                                                                                            Translation::updateOrInsert(
                                                                                                [
                                                                                                    'translationable_type' => 'App\Models\DataSetting',
                                                                                                    'translationable_id' => $join_seller_sub_title->id,
                                                                                                    'locale' => $key,
                                                                                                    'key' => 'join_seller_sub_title'
                                                                                                ],
                                                                                                ['value' => $request->join_seller_sub_title[$index]]
                                                                                            );
                                                                                        }
                                                                                    }
                                                                                    if ($default_lang == $key && !($request->join_seller_button_name[$index])) {
                                                                                        if ($key != 'default') {
                                                                                            Translation::updateOrInsert(
                                                                                                [
                                                                                                    'translationable_type' => 'App\Models\DataSetting',
                                                                                                    'translationable_id' => $join_seller_button_name->id,
                                                                                                    'locale' => $key,
                                                                                                    'key' => 'join_seller_button_name'
                                                                                                ],
                                                                                                ['value' => $join_seller_button_name->getRawOriginal('value')]
                                                                                            );
                                                                                        }
                                                                                    } else {
                                                                                        if ($request->join_seller_button_name[$index] && $key != 'default') {
                                                                                            Translation::updateOrInsert(
                                                                                                [
                                                                                                    'translationable_type' => 'App\Models\DataSetting',
                                                                                                    'translationable_id' => $join_seller_button_name->id,
                                                                                                    'locale' => $key,
                                                                                                    'key' => 'join_seller_button_name'
                                                                                                ],
                                                                                                ['value' => $request->join_seller_button_name[$index]]
                                                                                            );
                                                                                        }
                                                                                    }
                                                                                }
                                                                                
                                                                                Toastr::success(translate('messages.join_as_seller_data_updated'));
                                                                            } elseif ($tab == 'join-delivery') {
                                                                                
                                                                                if($request->join_DM_flutter_status !== null ){
                                                                                    $join_DM_flutter_status = DataSetting::where('type', 'flutter_landing_page')->where('key', 'join_DM_flutter_status')->first();
                                                                                    if ($join_DM_flutter_status == null) {
                                                                                        $join_DM_flutter_status = new DataSetting();
                                                                                    }
                                                                                    
                                                                                    $join_DM_flutter_status->key = 'join_DM_flutter_status';
                                                                                    $join_DM_flutter_status->type = 'flutter_landing_page';
                                                                                    $join_DM_flutter_status->value = $request->join_DM_flutter_status  ? 0 : 1 ;
                                                                                    $join_DM_flutter_status->save();
                                                                                    
                                                                                    Toastr::success(translate('messages.join_as_seller_section_status_updated'));
                                                                                    return back();
                                                                                }
                                                                                
                                                                                
                                                                                $join_delivery_man_title = DataSetting::where('type', 'flutter_landing_page')->where('key', 'join_delivery_man_title')->first();
                                                                                if ($join_delivery_man_title == null) {
                                                                                    $join_delivery_man_title = new DataSetting();
                                                                                }
                                                                                
                                                                                $join_delivery_man_title->key = 'join_delivery_man_title';
                                                                                $join_delivery_man_title->type = 'flutter_landing_page';
                                                                                $join_delivery_man_title->value = $request->join_delivery_man_title[array_search('default', $request->lang)];
                                                                                $join_delivery_man_title->save();
                                                                                
                                                                                $join_delivery_man_sub_title = DataSetting::where('type', 'flutter_landing_page')->where('key', 'join_delivery_man_sub_title')->first();
                                                                                if ($join_delivery_man_sub_title == null) {
                                                                                    $join_delivery_man_sub_title = new DataSetting();
                                                                                }
                                                                                
                                                                                $join_delivery_man_sub_title->key = 'join_delivery_man_sub_title';
                                                                                $join_delivery_man_sub_title->type = 'flutter_landing_page';
                                                                                $join_delivery_man_sub_title->value = $request->join_delivery_man_sub_title[array_search('default', $request->lang)];
                                                                                $join_delivery_man_sub_title->save();
                                                                                
                                                                                $join_delivery_man_button_name = DataSetting::where('type', 'flutter_landing_page')->where('key', 'join_delivery_man_button_name')->first();
                                                                                if ($join_delivery_man_button_name == null) {
                                                                                    $join_delivery_man_button_name = new DataSetting();
                                                                                }
                                                                                
                                                                                $join_delivery_man_button_name->key = 'join_delivery_man_button_name';
                                                                                $join_delivery_man_button_name->type = 'flutter_landing_page';
                                                                                $join_delivery_man_button_name->value = $request->join_delivery_man_button_name[array_search('default', $request->lang)];
                                                                                $join_delivery_man_button_name->save();
                                                                                
                                                                                $join_delivery_man_button_url = DataSetting::where('type', 'flutter_landing_page')->where('key', 'join_delivery_man_button_url')->first();
                                                                                if ($join_delivery_man_button_url == null) {
                                                                                    $join_delivery_man_button_url = new DataSetting();
                                                                                }
                                                                                
                                                                                $join_delivery_man_button_url->key = 'join_delivery_man_button_url';
                                                                                $join_delivery_man_button_url->type = 'flutter_landing_page';
                                                                                $join_delivery_man_button_url->value = $request->join_delivery_man_button_url;
                                                                                $join_delivery_man_button_url->save();
                                                                                
                                                                                $data = [];
                                                                                $default_lang = str_replace('_', '-', app()->getLocale());
                                                                                foreach ($request->lang as $index => $key) {
                                                                                    if ($default_lang == $key && !($request->join_delivery_man_title[$index])) {
                                                                                        if ($key != 'default') {
                                                                                            Translation::updateOrInsert(
                                                                                                [
                                                                                                    'translationable_type' => 'App\Models\DataSetting',
                                                                                                    'translationable_id' => $join_delivery_man_title->id,
                                                                                                    'locale' => $key,
                                                                                                    'key' => 'join_delivery_man_title'
                                                                                                ],
                                                                                                ['value' => $join_delivery_man_title?->getRawOriginal('value')]
                                                                                            );
                                                                                        }
                                                                                    } else {
                                                                                        if ($request->join_delivery_man_title[$index] && $key != 'default') {
                                                                                            Translation::updateOrInsert(
                                                                                                [
                                                                                                    'translationable_type' => 'App\Models\DataSetting',
                                                                                                    'translationable_id' => $join_delivery_man_title->id,
                                                                                                    'locale' => $key,
                                                                                                    'key' => 'join_delivery_man_title'
                                                                                                ],
                                                                                                ['value' => $request->join_delivery_man_title[$index]]
                                                                                            );
                                                                                        }
                                                                                    }
                                                                                    if ($default_lang == $key && !($request->join_delivery_man_sub_title[$index])) {
                                                                                        if ($key != 'default') {
                                                                                            Translation::updateOrInsert(
                                                                                                [
                                                                                                    'translationable_type' => 'App\Models\DataSetting',
                                                                                                    'translationable_id' => $join_delivery_man_sub_title->id,
                                                                                                    'locale' => $key,
                                                                                                    'key' => 'join_delivery_man_sub_title'
                                                                                                ],
                                                                                                ['value' => $join_delivery_man_sub_title?->getRawOriginal('value')]
                                                                                            );
                                                                                        }
                                                                                    } else {
                                                                                        if ($request->join_delivery_man_sub_title[$index] && $key != 'default') {
                                                                                            Translation::updateOrInsert(
                                                                                                [
                                                                                                    'translationable_type' => 'App\Models\DataSetting',
                                                                                                    'translationable_id' => $join_delivery_man_sub_title->id,
                                                                                                    'locale' => $key,
                                                                                                    'key' => 'join_delivery_man_sub_title'
                                                                                                ],
                                                                                                ['value' => $request->join_delivery_man_sub_title[$index]]
                                                                                            );
                                                                                        }
                                                                                    }
                                                                                    if ($default_lang == $key && !($request->join_delivery_man_button_name[$index])) {
                                                                                        if ($key != 'default') {
                                                                                            Translation::updateOrInsert(
                                                                                                [
                                                                                                    'translationable_type' => 'App\Models\DataSetting',
                                                                                                    'translationable_id' => $join_delivery_man_button_name->id,
                                                                                                    'locale' => $key,
                                                                                                    'key' => 'join_delivery_man_button_name'
                                                                                                ],
                                                                                                ['value' => $join_delivery_man_button_name->getRawOriginal('value')]
                                                                                            );
                                                                                        }
                                                                                    } else {
                                                                                        if ($request->join_delivery_man_button_name[$index] && $key != 'default') {
                                                                                            Translation::updateOrInsert(
                                                                                                [
                                                                                                    'translationable_type' => 'App\Models\DataSetting',
                                                                                                    'translationable_id' => $join_delivery_man_button_name->id,
                                                                                                    'locale' => $key,
                                                                                                    'key' => 'join_delivery_man_button_name'
                                                                                                ],
                                                                                                ['value' => $request->join_delivery_man_button_name[$index]]
                                                                                            );
                                                                                        }
                                                                                    }
                                                                                }
                                                                                
                                                                                Toastr::success(translate('messages.join_as_delivery_man_data_updated'));
                                                                            }
                                                                            return back();
                                                                        }
                                                                        
                                                                        public function flutter_criteria_status(Request $request)
                                                                        {
                                                                            if (env('APP_MODE') == 'demo' && $request->id == 1) {
                                                                                Toastr::warning('Sorry!You can not inactive this criteria!');
                                                                                return back();
                                                                            }
                                                                            $criteria = FlutterSpecialCriteria::findOrFail($request->id);
                                                                            $criteria->status = $request->status;
                                                                            $criteria->save();
                                                                            Toastr::success(translate('messages.criteria_status_updated'));
                                                                            return back();
                                                                        }
                                                                        
                                                                        public function flutter_criteria_edit($id)
                                                                        {
                                                                            $criteria = FlutterSpecialCriteria::withoutGlobalScope('translate')->findOrFail($id);
                                                                            return view('admin-views.business-settings.landing-page-settings.flutter-landing-page-special-criteria-edit', compact('criteria'));
                                                                        }
                                                                        
                                                                        public function flutter_criteria_update(Request $request, $id)
                                                                        {
                                                                            $request->validate([
                                                                                'title' => 'required|max:100',
                                                                            ]);
                                                                            
                                                                            if ($request->title[array_search('default', $request->lang)] == '') {
                                                                                Toastr::error(translate('default_data_is_required'));
                                                                                return back();
                                                                            }
                                                                            $criteria = FlutterSpecialCriteria::find($id);
                                                                            $criteria->title = $request->title[array_search('default', $request->lang)];
                                                                            $criteria->image = $request->has('image') ? Helpers::update('special_criteria/', $criteria->image, 'png', $request->file('image')) : $criteria->image;
                                                                            $criteria->save();
                                                                            $default_lang = str_replace('_', '-', app()->getLocale());
                                                                            foreach ($request->lang as $index => $key) {
                                                                                if ($default_lang == $key && !($request->title[$index])) {
                                                                                    if ($key != 'default') {
                                                                                        Translation::updateOrInsert(
                                                                                            [
                                                                                                'translationable_type' => 'App\Models\FlutterSpecialCriteria',
                                                                                                'translationable_id' => $criteria->id,
                                                                                                'locale' => $key,
                                                                                                'key' => 'title'
                                                                                            ],
                                                                                            ['value' => $criteria->title]
                                                                                        );
                                                                                    }
                                                                                } else {
                                                                                    
                                                                                    if ($request->title[$index] && $key != 'default') {
                                                                                        Translation::updateOrInsert(
                                                                                            [
                                                                                                'translationable_type' => 'App\Models\FlutterSpecialCriteria',
                                                                                                'translationable_id' => $criteria->id,
                                                                                                'locale' => $key,
                                                                                                'key' => 'title'
                                                                                            ],
                                                                                            ['value' => $request->title[$index]]
                                                                                        );
                                                                                    }
                                                                                }
                                                                            }
                                                                            Toastr::success(translate('messages.criteria_updated_successfully'));
                                                                            return back();
                                                                        }
                                                                        
                                                                        public function flutter_criteria_destroy(FlutterSpecialCriteria $criteria)
                                                                        {
                                                                            if (env('APP_MODE') == 'demo' && $criteria->id == 1) {
                                                                                Toastr::warning(translate('messages.you_can_not_delete_this_criteria_please_add_a_new_criteria_to_delete'));
                                                                                return back();
                                                                            }
                                                                            $criteria->delete();
                                                                            Toastr::success(translate('messages.criteria_deleted_successfully'));
                                                                            return back();
                                                                        }
                                                                        
                                                                        public function email_index(Request $request, $type, $tab)
                                                                        {
                                                                            $template = $request->query('template', null);
                                                                            if ($tab == 'new-order') {
                                                                                return view('admin-views.business-settings.email-format-setting.' . $type . '-email-formats.place-order-format', compact('template'));
                                                                            } else if ($tab == 'forgot-password') {
                                                                                return view('admin-views.business-settings.email-format-setting.' . $type . '-email-formats.forgot-pass-format', compact('template'));
                                                                            } else if ($tab == 'store-registration') {
                                                                                return view('admin-views.business-settings.email-format-setting.' . $type . '-email-formats.store-registration-format', compact('template'));
                                                                            } else if ($tab == 'dm-registration') {
                                                                                return view('admin-views.business-settings.email-format-setting.' . $type . '-email-formats.dm-registration-format', compact('template'));
                                                                            } else if ($tab == 'registration') {
                                                                                return view('admin-views.business-settings.email-format-setting.' . $type . '-email-formats.registration-format', compact('template'));
                                                                            } else if ($tab == 'approve') {
                                                                                return view('admin-views.business-settings.email-format-setting.' . $type . '-email-formats.approve-format', compact('template'));
                                                                            } else if ($tab == 'deny') {
                                                                                return view('admin-views.business-settings.email-format-setting.' . $type . '-email-formats.deny-format', compact('template'));
                                                                            } else if ($tab == 'withdraw-request') {
                                                                                return view('admin-views.business-settings.email-format-setting.' . $type . '-email-formats.withdraw-request-format', compact('template'));
                                                                            } else if ($tab == 'withdraw-approve') {
                                                                                return view('admin-views.business-settings.email-format-setting.' . $type . '-email-formats.withdraw-approve-format', compact('template'));
                                                                            } else if ($tab == 'withdraw-deny') {
                                                                                return view('admin-views.business-settings.email-format-setting.' . $type . '-email-formats.withdraw-deny-format', compact('template'));
                                                                            } else if ($tab == 'campaign-request') {
                                                                                return view('admin-views.business-settings.email-format-setting.' . $type . '-email-formats.campaign-request-format', compact('template'));
                                                                            } else if ($tab == 'campaign-approve') {
                                                                                return view('admin-views.business-settings.email-format-setting.' . $type . '-email-formats.campaign-approve-format', compact('template'));
                                                                            } else if ($tab == 'campaign-deny') {
                                                                                return view('admin-views.business-settings.email-format-setting.' . $type . '-email-formats.campaign-deny-format', compact('template'));
                                                                            } else if ($tab == 'refund-request') {
                                                                                return view('admin-views.business-settings.email-format-setting.' . $type . '-email-formats.refund-request-format', compact('template'));
                                                                            } else if ($tab == 'login') {
                                                                                return view('admin-views.business-settings.email-format-setting.' . $type . '-email-formats.login-format', compact('template'));
                                                                            } else if ($tab == 'suspend') {
                                                                                return view('admin-views.business-settings.email-format-setting.' . $type . '-email-formats.suspend-format', compact('template'));
                                                                            } else if ($tab == 'cash-collect') {
                                                                                return view('admin-views.business-settings.email-format-setting.' . $type . '-email-formats.cash-collect-format', compact('template'));
                                                                            } else if ($tab == 'registration-otp') {
                                                                                return view('admin-views.business-settings.email-format-setting.' . $type . '-email-formats.registration-otp-format', compact('template'));
                                                                            } else if ($tab == 'login-otp') {
                                                                                return view('admin-views.business-settings.email-format-setting.' . $type . '-email-formats.login-otp-format', compact('template'));
                                                                            } else if ($tab == 'order-verification') {
                                                                                return view('admin-views.business-settings.email-format-setting.' . $type . '-email-formats.order-verification-format', compact('template'));
                                                                            } else if ($tab == 'refund-request-deny') {
                                                                                return view('admin-views.business-settings.email-format-setting.' . $type . '-email-formats.refund-request-deny-format', compact('template'));
                                                                            } else if ($tab == 'add-fund') {
                                                                                return view('admin-views.business-settings.email-format-setting.' . $type . '-email-formats.add-fund-format', compact('template'));
                                                                            } else if ($tab == 'refund-order') {
                                                                                return view('admin-views.business-settings.email-format-setting.' . $type . '-email-formats.refund-order-format', compact('template'));
                                                                            } else if ($tab == 'product-approved') {
                                                                                return view('admin-views.business-settings.email-format-setting.' . $type . '-email-formats.product-approved-format', compact('template'));
                                                                            } else if ($tab == 'product-deny') {
                                                                                return view('admin-views.business-settings.email-format-setting.' . $type . '-email-formats.product-deny-format', compact('template'));
                                                                            } else if ($tab == 'offline-payment-approve') {
                                                                                return view('admin-views.business-settings.email-format-setting.' . $type . '-email-formats.offline-approved-format', compact('template'));
                                                                            } else if ($tab == 'offline-payment-deny') {
                                                                                return view('admin-views.business-settings.email-format-setting.' . $type . '-email-formats.offline-deny-format', compact('template'));
                                                                            } else if ($tab == 'pos-registration') {
                                                                                return view('admin-views.business-settings.email-format-setting.' . $type . '-email-formats.pos-registration-format', compact('template'));
                                                                            } else if ($tab == 'unsuspend') {
                                                                                return view('admin-views.business-settings.email-format-setting.' . $type . '-email-formats.unsuspend-format', compact('template'));
                                                                                
                                                                            } else if ($tab == 'subscription-successful') {
                                                                                return view('admin-views.business-settings.email-format-setting.' . $type . '-email-formats.subscription-successful-format', compact('template'));
                                                                            } else if ($tab == 'subscription-renew') {
                                                                                return view('admin-views.business-settings.email-format-setting.' . $type . '-email-formats.subscription-renew-format', compact('template'));
                                                                            } else if ($tab == 'subscription-shift') {
                                                                                return view('admin-views.business-settings.email-format-setting.' . $type . '-email-formats.subscription-shift-format', compact('template'));
                                                                            } else if ($tab == 'subscription-cancel') {
                                                                                return view('admin-views.business-settings.email-format-setting.' . $type . '-email-formats.subscription-cancel-format', compact('template'));
                                                                            } else if ($tab == 'subscription-deadline') {
                                                                                return view('admin-views.business-settings.email-format-setting.' . $type . '-email-formats.subscription-deadline-format', compact('template'));
                                                                            } else if ($tab == 'subscription-plan_upadte') {
                                                                                return view('admin-views.business-settings.email-format-setting.' . $type . '-email-formats.subscription-plan_upadte-format', compact('template'));
                                                                            } else if ($tab == 'new-advertisement') {
                                                                                return view('admin-views.business-settings.email-format-setting.' . $type . '-email-formats.new-advertisement-format', compact('template'));
                                                                            } else if ($tab == 'update-advertisement') {
                                                                                return view('admin-views.business-settings.email-format-setting.' . $type . '-email-formats.update-advertisement-format', compact('template'));
                                                                            } else if ($tab == 'advertisement-create') {
                                                                                return view('admin-views.business-settings.email-format-setting.' . $type . '-email-formats.advertisement-create-format', compact('template'));
                                                                            } else if ($tab == 'advertisement-approved') {
                                                                                return view('admin-views.business-settings.email-format-setting.' . $type . '-email-formats.advertisement-approved-format', compact('template'));
                                                                            } else if ($tab == 'advertisement-deny') {
                                                                                return view('admin-views.business-settings.email-format-setting.' . $type . '-email-formats.advertisement-deny-format', compact('template'));
                                                                            } else if ($tab == 'advertisement-resume') {
                                                                                return view('admin-views.business-settings.email-format-setting.' . $type . '-email-formats.advertisement-resume-format', compact('template'));
                                                                            } else if ($tab == 'advertisement-pause') {
                                                                                return view('admin-views.business-settings.email-format-setting.' . $type . '-email-formats.advertisement-pause-format', compact('template'));
                                                                            }
                                                                            
                                                                        }
                                                                        
                                                                        public function update_email_index(Request $request, $type, $tab)
                                                                        {
                                                                            if (env('APP_MODE') == 'demo') {
                                                                                Toastr::info(translate('messages.update_option_is_disable_for_demo'));
                                                                                return back();
                                                                            }
                                                                            
                                                                            $request->validate([
                                                                                'title.*' => 'nullable|max:255',
                                                                                'button_name.*' => 'nullable|max:255',
                                                                                'footer_text.*' => 'nullable|max:255',
                                                                                'copyright_text.*' => 'nullable|max:255',
                                                                            ], [
                                                                                'title.*.max' => 'The title may not be greater than 255 characters.',
                                                                                'button_name.*.max' => 'The button_name may not be greater than 255 characters.',
                                                                                'footer_text.*.max' => 'The footer_text may not be greater than 255 characters.',
                                                                                'copyright_text.*.max' => 'The copyright_text may not be greater than 255 characters.',
                                                                            ]);
                                                                            
                                                                            if ($tab == 'new-order') {
                                                                                $email_type = 'new_order';
                                                                                $template = EmailTemplate::where('type', $type)->where('email_type', 'new_order')->first();
                                                                            } elseif ($tab == 'forget-password') {
                                                                                $email_type = 'forget_password';
                                                                                $template = EmailTemplate::where('type', $type)->where('email_type', 'forget_password')->first();
                                                                            } elseif ($tab == 'store-registration') {
                                                                                $email_type = 'store_registration';
                                                                                $template = EmailTemplate::where('type', $type)->where('email_type', 'store_registration')->first();
                                                                            } elseif ($tab == 'dm-registration') {
                                                                                $email_type = 'dm_registration';
                                                                                $template = EmailTemplate::where('type', $type)->where('email_type', 'dm_registration')->first();
                                                                            } elseif ($tab == 'registration') {
                                                                                $email_type = 'registration';
                                                                                $template = EmailTemplate::where('type', $type)->where('email_type', 'registration')->first();
                                                                            } elseif ($tab == 'approve') {
                                                                                $email_type = 'approve';
                                                                                $template = EmailTemplate::where('type', $type)->where('email_type', 'approve')->first();
                                                                            } elseif ($tab == 'deny') {
                                                                                $email_type = 'deny';
                                                                                $template = EmailTemplate::where('type', $type)->where('email_type', 'deny')->first();
                                                                            } elseif ($tab == 'withdraw-request') {
                                                                                $email_type = 'withdraw_request';
                                                                                $template = EmailTemplate::where('type', $type)->where('email_type', 'withdraw_request')->first();
                                                                            } elseif ($tab == 'withdraw-approve') {
                                                                                $email_type = 'withdraw_approve';
                                                                                $template = EmailTemplate::where('type', $type)->where('email_type', 'withdraw_approve')->first();
                                                                            } elseif ($tab == 'withdraw-deny') {
                                                                                $email_type = 'withdraw_deny';
                                                                                $template = EmailTemplate::where('type', $type)->where('email_type', 'withdraw_deny')->first();
                                                                            } elseif ($tab == 'campaign-request') {
                                                                                $email_type = 'campaign_request';
                                                                                $template = EmailTemplate::where('type', $type)->where('email_type', 'campaign_request')->first();
                                                                            } elseif ($tab == 'campaign-approve') {
                                                                                $email_type = 'campaign_approve';
                                                                                $template = EmailTemplate::where('type', $type)->where('email_type', 'campaign_approve')->first();
                                                                            } elseif ($tab == 'campaign-deny') {
                                                                                $email_type = 'campaign_deny';
                                                                                $template = EmailTemplate::where('type', $type)->where('email_type', 'campaign_deny')->first();
                                                                            } elseif ($tab == 'refund-request') {
                                                                                $email_type = 'refund_request';
                                                                                $template = EmailTemplate::where('type', $type)->where('email_type', 'refund_request')->first();
                                                                            } elseif ($tab == 'login') {
                                                                                $email_type = 'login';
                                                                                $template = EmailTemplate::where('type', $type)->where('email_type', 'login')->first();
                                                                            } elseif ($tab == 'suspend') {
                                                                                $email_type = 'suspend';
                                                                                $template = EmailTemplate::where('type', $type)->where('email_type', 'suspend')->first();
                                                                            } elseif ($tab == 'cash-collect') {
                                                                                $email_type = 'cash_collect';
                                                                                $template = EmailTemplate::where('type', $type)->where('email_type', 'cash_collect')->first();
                                                                            } elseif ($tab == 'registration-otp') {
                                                                                $email_type = 'registration_otp';
                                                                                $template = EmailTemplate::where('type', $type)->where('email_type', 'registration_otp')->first();
                                                                            } elseif ($tab == 'login-otp') {
                                                                                $email_type = 'login_otp';
                                                                                $template = EmailTemplate::where('type', $type)->where('email_type', 'login_otp')->first();
                                                                            } elseif ($tab == 'order-verification') {
                                                                                $email_type = 'order_verification';
                                                                                $template = EmailTemplate::where('type', $type)->where('email_type', 'order_verification')->first();
                                                                            } elseif ($tab == 'refund-request-deny') {
                                                                                $email_type = 'refund_request_deny';
                                                                                $template = EmailTemplate::where('type', $type)->where('email_type', 'refund_request_deny')->first();
                                                                            } elseif ($tab == 'add-fund') {
                                                                                $email_type = 'add_fund';
                                                                                $template = EmailTemplate::where('type', $type)->where('email_type', 'add_fund')->first();
                                                                            } elseif ($tab == 'refund-order') {
                                                                                $email_type = 'refund_order';
                                                                                $template = EmailTemplate::where('type', $type)->where('email_type', 'refund_order')->first();
                                                                            } elseif ($tab == 'product-deny') {
                                                                                $email_type = 'product_deny';
                                                                                $template = EmailTemplate::where('type', $type)->where('email_type', 'product_deny')->first();
                                                                            } elseif ($tab == 'product-approved') {
                                                                                $email_type = 'product_approved';
                                                                                $template = EmailTemplate::where('type', $type)->where('email_type', 'product_approved')->first();
                                                                                
                                                                            } elseif ($tab == 'offline-payment-deny') {
                                                                                $email_type = 'offline_payment_deny';
                                                                                $template = EmailTemplate::where('type', $type)->where('email_type', 'offline_payment_deny')->first();
                                                                            } elseif ($tab == 'offline-payment-approve') {
                                                                                $email_type = 'offline_payment_approve';
                                                                                $template = EmailTemplate::where('type', $type)->where('email_type', 'offline_payment_approve')->first();
                                                                            } elseif ($tab == 'pos-registration') {
                                                                                $email_type = 'pos_registration';
                                                                                $template = EmailTemplate::where('type', $type)->where('email_type', 'pos_registration')->first();
                                                                            } elseif ($tab == 'unsuspend') {
                                                                                $email_type = 'unsuspend';
                                                                                $template = EmailTemplate::where('type', $type)->where('email_type', 'unsuspend')->first();
                                                                            } elseif ($tab == 'subscription-successful') {
                                                                                $email_type = 'subscription-successful';
                                                                                $template = EmailTemplate::where('type', $type)->where('email_type', 'subscription-successful')->first();
                                                                            } elseif ($tab == 'subscription-renew') {
                                                                                $email_type = 'subscription-renew';
                                                                                $template = EmailTemplate::where('type', $type)->where('email_type', 'subscription-renew')->first();
                                                                            } elseif ($tab == 'subscription-shift') {
                                                                                $email_type = 'subscription-shift';
                                                                                $template = EmailTemplate::where('type', $type)->where('email_type', 'subscription-shift')->first();
                                                                            } elseif ($tab == 'subscription-cancel') {
                                                                                $email_type = 'subscription-cancel';
                                                                                $template = EmailTemplate::where('type', $type)->where('email_type', 'subscription-cancel')->first();
                                                                            } elseif ($tab == 'subscription-deadline') {
                                                                                $email_type = 'subscription-deadline';
                                                                                $template = EmailTemplate::where('type', $type)->where('email_type', 'subscription-deadline')->first();
                                                                            } elseif ($tab == 'subscription-plan_upadte') {
                                                                                $email_type = 'subscription-plan_upadte';
                                                                                $template = EmailTemplate::where('type', $type)->where('email_type', 'subscription-plan_upadte')->first();
                                                                            } elseif ($tab == 'new-advertisement') {
                                                                                $email_type = 'new_advertisement';
                                                                                $template = EmailTemplate::where('type', $type)->where('email_type', 'new_advertisement')->first();
                                                                            } elseif ($tab == 'update-advertisement') {
                                                                                $email_type = 'update_advertisement';
                                                                                $template = EmailTemplate::where('type', $type)->where('email_type', 'update_advertisement')->first();
                                                                            } elseif ($tab == 'advertisement-pause') {
                                                                                $email_type = 'advertisement_pause';
                                                                                $template = EmailTemplate::where('type', $type)->where('email_type', 'advertisement_pause')->first();
                                                                            } elseif ($tab == 'advertisement-approved') {
                                                                                $email_type = 'advertisement_approved';
                                                                                $template = EmailTemplate::where('type', $type)->where('email_type', 'advertisement_approved')->first();
                                                                            } elseif ($tab == 'advertisement-create') {
                                                                                $email_type = 'advertisement_create';
                                                                                $template = EmailTemplate::where('type', $type)->where('email_type', 'advertisement_create')->first();
                                                                            } elseif ($tab == 'advertisement-deny') {
                                                                                $email_type = 'advertisement_deny';
                                                                                $template = EmailTemplate::where('type', $type)->where('email_type', 'advertisement_deny')->first();
                                                                            } elseif ($tab == 'advertisement-resume') {
                                                                                $email_type = 'advertisement_resume';
                                                                                $template = EmailTemplate::where('type', $type)->where('email_type', 'advertisement_resume')->first();
                                                                            }
                                                                            
                                                                            if ($template == null) {
                                                                                $template = new EmailTemplate();
                                                                            }
                                                                            if ($request->title[array_search('default', $request->lang)] == '') {
                                                                                Toastr::error(translate('default_data_is_required'));
                                                                                return back();
                                                                            }
                                                                            $template->title = $request->title[array_search('default', $request->lang)];
                                                                            $template->body = $request->body[array_search('default', $request->lang)];
                                                                            $template->body_2 = $request?->body_2 ? $request->body_2[array_search('default', $request->lang)] : null;
                                                                            $template->button_name = $request->button_name ? $request->button_name[array_search('default', $request->lang)] : '';
                                                                            $template->footer_text = $request->footer_text[array_search('default', $request->lang)];
                                                                            $template->copyright_text = $request->copyright_text[array_search('default', $request->lang)];
                                                                            $template->background_image = $request->has('background_image') ? Helpers::update('email_template/', $template->background_image, 'png', $request->file('background_image')) : $template->background_image;
                                                                            $template->image = $request->has('image') ? Helpers::update('email_template/', $template->image, 'png', $request->file('image')) : $template->image;
                                                                            $template->logo = $request->has('logo') ? Helpers::update('email_template/', $template->logo, 'png', $request->file('logo')) : $template->logo;
                                                                            $template->icon = $request->has('icon') ? Helpers::update('email_template/', $template->icon, 'png', $request->file('icon')) : $template->icon;
                                                                            $template->email_type = $email_type;
                                                                            $template->type = $type;
                                                                            $template->button_url = $request->button_url ?? '';
                                                                            $template->email_template = $request->email_template;
                                                                            $template->privacy = $request->privacy ? '1' : 0;
                                                                            $template->refund = $request->refund ? '1' : 0;
                                                                            $template->cancelation = $request->cancelation ? '1' : 0;
                                                                            $template->contact = $request->contact ? '1' : 0;
                                                                            $template->facebook = $request->facebook ? '1' : 0;
                                                                            $template->instagram = $request->instagram ? '1' : 0;
                                                                            $template->twitter = $request->twitter ? '1' : 0;
                                                                            $template->linkedin = $request->linkedin ? '1' : 0;
                                                                            $template->pinterest = $request->pinterest ? '1' : 0;
                                                                            $template->save();
                                                                            $default_lang = str_replace('_', '-', app()->getLocale());
                                                                            foreach ($request->lang as $index => $key) {
                                                                                if ($default_lang == $key && !($request->title[$index])) {
                                                                                    if ($key != 'default') {
                                                                                        Translation::updateOrInsert(
                                                                                            [
                                                                                                'translationable_type' => 'App\Models\EmailTemplate',
                                                                                                'translationable_id' => $template->id,
                                                                                                'locale' => $key,
                                                                                                'key' => 'title'
                                                                                            ],
                                                                                            ['value' => $request->title[array_search('default', $request->lang)] ?? '']
                                                                                        );
                                                                                    }
                                                                                } else {
                                                                                    
                                                                                    if ($request->title[$index] && $key != 'default') {
                                                                                        Translation::updateOrInsert(
                                                                                            [
                                                                                                'translationable_type' => 'App\Models\EmailTemplate',
                                                                                                'translationable_id' => $template->id,
                                                                                                'locale' => $key,
                                                                                                'key' => 'title'
                                                                                            ],
                                                                                            ['value' => $request->title[$index]]
                                                                                        );
                                                                                    }
                                                                                }
                                                                                if ($default_lang == $key && !($request->body[$index])) {
                                                                                    if ($key != 'default') {
                                                                                        Translation::updateOrInsert(
                                                                                            [
                                                                                                'translationable_type' => 'App\Models\EmailTemplate',
                                                                                                'translationable_id' => $template->id,
                                                                                                'locale' => $key,
                                                                                                'key' => 'body'
                                                                                            ],
                                                                                            ['value' => $request->body[array_search('default', $request->lang)] ?? '']
                                                                                        );
                                                                                    }
                                                                                } else {
                                                                                    if ($request->body[$index] && $key != 'default') {
                                                                                        Translation::updateOrInsert(
                                                                                            [
                                                                                                'translationable_type' => 'App\Models\EmailTemplate',
                                                                                                'translationable_id' => $template->id,
                                                                                                'locale' => $key,
                                                                                                'key' => 'body'
                                                                                            ],
                                                                                            ['value' => $request->body[$index]]
                                                                                        );
                                                                                    }
                                                                                }
                                                                                if ($request?->body_2 && $default_lang == $key && !($request->body_2[$index])) {
                                                                                    if ($key != 'default') {
                                                                                        Translation::updateOrInsert(
                                                                                            [
                                                                                                'translationable_type' => 'App\Models\EmailTemplate',
                                                                                                'translationable_id' => $template->id,
                                                                                                'locale' => $key,
                                                                                                'key' => 'body_2'
                                                                                            ],
                                                                                            ['value' => $template->body_2]
                                                                                        );
                                                                                    }
                                                                                } else {
                                                                                    
                                                                                    if ($request?->body_2 && $request->body_2[$index] && $key != 'default') {
                                                                                        Translation::updateOrInsert(
                                                                                            [
                                                                                                'translationable_type' => 'App\Models\EmailTemplate',
                                                                                                'translationable_id' => $template->id,
                                                                                                'locale' => $key,
                                                                                                'key' => 'body_2'
                                                                                            ],
                                                                                            ['value' => $request->body_2[$index]]
                                                                                        );
                                                                                    }
                                                                                }
                                                                                if ($default_lang == $key && !($request->button_name && $request->button_name[$index])) {
                                                                                    if ($key != 'default') {
                                                                                        Translation::updateOrInsert(
                                                                                            [
                                                                                                'translationable_type' => 'App\Models\EmailTemplate',
                                                                                                'translationable_id' => $template->id,
                                                                                                'locale' => $key,
                                                                                                'key' => 'button_name'
                                                                                            ],
                                                                                            ['value' => $request->button_name[array_search('default', $request->lang)] ?? '']
                                                                                        );
                                                                                    }
                                                                                } else {
                                                                                    
                                                                                    if ($request->button_name && $request->button_name[$index] && $key != 'default') {
                                                                                        Translation::updateOrInsert(
                                                                                            [
                                                                                                'translationable_type' => 'App\Models\EmailTemplate',
                                                                                                'translationable_id' => $template->id,
                                                                                                'locale' => $key,
                                                                                                'key' => 'button_name'
                                                                                            ],
                                                                                            ['value' => $request->button_name[$index]]
                                                                                        );
                                                                                    }
                                                                                }
                                                                                if ($default_lang == $key && !($request->footer_text[$index])) {
                                                                                    if ($key != 'default') {
                                                                                        Translation::updateOrInsert(
                                                                                            [
                                                                                                'translationable_type' => 'App\Models\EmailTemplate',
                                                                                                'translationable_id' => $template->id,
                                                                                                'locale' => $key,
                                                                                                'key' => 'footer_text'
                                                                                            ],
                                                                                            ['value' => $request->footer_text[array_search('default', $request->lang)] ?? '']
                                                                                        );
                                                                                    }
                                                                                } else {
                                                                                    
                                                                                    if ($request->footer_text[$index] && $key != 'default') {
                                                                                        Translation::updateOrInsert(
                                                                                            [
                                                                                                'translationable_type' => 'App\Models\EmailTemplate',
                                                                                                'translationable_id' => $template->id,
                                                                                                'locale' => $key,
                                                                                                'key' => 'footer_text'
                                                                                            ],
                                                                                            ['value' => $request->footer_text[$index]]
                                                                                        );
                                                                                    }
                                                                                }
                                                                                if ($default_lang == $key && !($request->copyright_text[$index])) {
                                                                                    if ($key != 'default') {
                                                                                        Translation::updateOrInsert(
                                                                                            [
                                                                                                'translationable_type' => 'App\Models\EmailTemplate',
                                                                                                'translationable_id' => $template->id,
                                                                                                'locale' => $key,
                                                                                                'key' => 'copyright_text'
                                                                                            ],
                                                                                            ['value' => $request->copyright_text[array_search('default', $request->lang)] ?? '']
                                                                                        );
                                                                                    }
                                                                                } else {
                                                                                    
                                                                                    if ($request->copyright_text[$index] && $key != 'default') {
                                                                                        Translation::updateOrInsert(
                                                                                            [
                                                                                                'translationable_type' => 'App\Models\EmailTemplate',
                                                                                                'translationable_id' => $template->id,
                                                                                                'locale' => $key,
                                                                                                'key' => 'copyright_text'
                                                                                            ],
                                                                                            ['value' => $request->copyright_text[$index]]
                                                                                        );
                                                                                    }
                                                                                }
                                                                            }
                                                                            
                                                                            Toastr::success(translate('messages.template_added_successfully'));
                                                                            return back();
                                                                        }
                                                                        
                                                                        public function update_email_status(Request $request, $type, $tab, $status)
                                                                        {
                                                                            
                                                                            if (env('APP_MODE') == 'demo') {
                                                                                Toastr::info(translate('messages.update_option_is_disable_for_demo'));
                                                                                return back();
                                                                            }
                                                                            
                                                                            if ($tab == 'place-order') {
                                                                                Helpers::businessUpdateOrInsert(['key' => 'place_order_mail_status_' . $type], [
                                                                                    'value' => $status
                                                                                ]);
                                                                            } else if ($tab == 'forgot-password') {
                                                                                Helpers::businessUpdateOrInsert(['key' => 'forget_password_mail_status_' . $type], [
                                                                                    'value' => $status
                                                                                ]);
                                                                            } else if ($tab == 'store-registration') {
                                                                                Helpers::businessUpdateOrInsert(['key' => 'store_registration_mail_status_' . $type], [
                                                                                    'value' => $status
                                                                                ]);
                                                                            } else if ($tab == 'dm-registration') {
                                                                                Helpers::businessUpdateOrInsert(['key' => 'dm_registration_mail_status_' . $type], [
                                                                                    'value' => $status
                                                                                ]);
                                                                            } else if ($tab == 'registration') {
                                                                                Helpers::businessUpdateOrInsert(['key' => 'registration_mail_status_' . $type], [
                                                                                    'value' => $status
                                                                                ]);
                                                                            } else if ($tab == 'approve') {
                                                                                Helpers::businessUpdateOrInsert(['key' => 'approve_mail_status_' . $type], [
                                                                                    'value' => $status
                                                                                ]);
                                                                            } else if ($tab == 'deny') {
                                                                                Helpers::businessUpdateOrInsert(['key' => 'deny_mail_status_' . $type], [
                                                                                    'value' => $status
                                                                                ]);
                                                                            } else if ($tab == 'withdraw-request') {
                                                                                Helpers::businessUpdateOrInsert(['key' => 'withdraw_request_mail_status_' . $type], [
                                                                                    'value' => $status
                                                                                ]);
                                                                            } else if ($tab == 'withdraw-approve') {
                                                                                Helpers::businessUpdateOrInsert(['key' => 'withdraw_approve_mail_status_' . $type], [
                                                                                    'value' => $status
                                                                                ]);
                                                                            } else if ($tab == 'withdraw-deny') {
                                                                                Helpers::businessUpdateOrInsert(['key' => 'withdraw_deny_mail_status_' . $type], [
                                                                                    'value' => $status
                                                                                ]);
                                                                            } else if ($tab == 'campaign-request') {
                                                                                Helpers::businessUpdateOrInsert(['key' => 'campaign_request_mail_status_' . $type], [
                                                                                    'value' => $status
                                                                                ]);
                                                                            } else if ($tab == 'campaign-approve') {
                                                                                Helpers::businessUpdateOrInsert(['key' => 'campaign_approve_mail_status_' . $type], [
                                                                                    'value' => $status
                                                                                ]);
                                                                            } else if ($tab == 'campaign-deny') {
                                                                                Helpers::businessUpdateOrInsert(['key' => 'campaign_deny_mail_status_' . $type], [
                                                                                    'value' => $status
                                                                                ]);
                                                                            } else if ($tab == 'refund-request') {
                                                                                Helpers::businessUpdateOrInsert(['key' => 'refund_request_mail_status_' . $type], [
                                                                                    'value' => $status
                                                                                ]);
                                                                            } else if ($tab == 'login') {
                                                                                Helpers::businessUpdateOrInsert(['key' => 'login_mail_status_' . $type], [
                                                                                    'value' => $status
                                                                                ]);
                                                                            } else if ($tab == 'suspend') {
                                                                                Helpers::businessUpdateOrInsert(['key' => 'suspend_mail_status_' . $type], [
                                                                                    'value' => $status
                                                                                ]);
                                                                            } else if ($tab == 'cash-collect') {
                                                                                Helpers::businessUpdateOrInsert(['key' => 'cash_collect_mail_status_' . $type], [
                                                                                    'value' => $status
                                                                                ]);
                                                                            } else if ($tab == 'registration-otp') {
                                                                                Helpers::businessUpdateOrInsert(['key' => 'registration_otp_mail_status_' . $type], [
                                                                                    'value' => $status
                                                                                ]);
                                                                            } else if ($tab == 'login-otp') {
                                                                                Helpers::businessUpdateOrInsert(['key' => 'login_otp_mail_status_' . $type], [
                                                                                    'value' => $status
                                                                                ]);
                                                                            } else if ($tab == 'order-verification') {
                                                                                Helpers::businessUpdateOrInsert(['key' => 'order_verification_mail_status_' . $type], [
                                                                                    'value' => $status
                                                                                ]);
                                                                            } else if ($tab == 'refund-request-deny') {
                                                                                Helpers::businessUpdateOrInsert(['key' => 'refund_request_deny_mail_status_' . $type], [
                                                                                    'value' => $status
                                                                                ]);
                                                                            } else if ($tab == 'add-fund') {
                                                                                Helpers::businessUpdateOrInsert(['key' => 'add_fund_mail_status_' . $type], [
                                                                                    'value' => $status
                                                                                ]);
                                                                            } else if ($tab == 'refund-order') {
                                                                                Helpers::businessUpdateOrInsert(['key' => 'refund_order_mail_status_' . $type], [
                                                                                    'value' => $status
                                                                                ]);
                                                                            } else if ($tab == 'product-deny') {
                                                                                Helpers::businessUpdateOrInsert(['key' => 'product_deny_mail_status_' . $type], [
                                                                                    'value' => $status
                                                                                ]);
                                                                            } else if ($tab == 'product-approved') {
                                                                                Helpers::businessUpdateOrInsert(['key' => 'product_approve_mail_status_' . $type], [
                                                                                    'value' => $status
                                                                                ]);
                                                                                
                                                                            } else if ($tab == 'offline-payment-deny') {
                                                                                Helpers::businessUpdateOrInsert(['key' => 'offline_payment_deny_mail_status_' . $type], [
                                                                                    'value' => $status
                                                                                ]);
                                                                            } else if ($tab == 'offline-payment-approve') {
                                                                                Helpers::businessUpdateOrInsert(['key' => 'offline_payment_approve_mail_status_' . $type], [
                                                                                    'value' => $status
                                                                                ]);
                                                                            } else if ($tab == 'pos-registration') {
                                                                                Helpers::businessUpdateOrInsert(['key' => 'pos_registration_mail_status_' . $type], [
                                                                                    'value' => $status
                                                                                ]);
                                                                            } else if ($tab == 'unsuspend') {
                                                                                Helpers::businessUpdateOrInsert(['key' => 'unsuspend_mail_status_' . $type], [
                                                                                    'value' => $status
                                                                                ]);
                                                                            } else if ($tab == 'subscription-successful') {
                                                                                Helpers::businessUpdateOrInsert(['key' => 'subscription_successful_mail_status_' . $type], [
                                                                                    'value' => $status
                                                                                ]);
                                                                            } else if ($tab == 'subscription-renew') {
                                                                                Helpers::businessUpdateOrInsert(['key' => 'subscription_renew_mail_status_' . $type], [
                                                                                    'value' => $status
                                                                                ]);
                                                                            } else if ($tab == 'subscription-shift') {
                                                                                Helpers::businessUpdateOrInsert(['key' => 'subscription_shift_mail_status_' . $type], [
                                                                                    'value' => $status
                                                                                ]);
                                                                            } else if ($tab == 'subscription-cancel') {
                                                                                Helpers::businessUpdateOrInsert(['key' => 'subscription_cancel_mail_status_' . $type], [
                                                                                    'value' => $status
                                                                                ]);
                                                                            } else if ($tab == 'subscription-deadline') {
                                                                                Helpers::businessUpdateOrInsert(['key' => 'subscription_deadline_mail_status_' . $type], [
                                                                                    'value' => $status
                                                                                ]);
                                                                            } else if ($tab == 'subscription-plan_upadte') {
                                                                                Helpers::businessUpdateOrInsert(['key' => 'subscription_plan_upadte_mail_status_' . $type], [
                                                                                    'value' => $status
                                                                                ]);
                                                                            } else if ($tab == 'new-advertisement') {
                                                                                Helpers::businessUpdateOrInsert(['key' => 'new_advertisement_mail_status_' . $type], [
                                                                                    'value' => $status
                                                                                ]);
                                                                            } else if ($tab == 'update-advertisement') {
                                                                                Helpers::businessUpdateOrInsert(['key' => 'update_advertisement_mail_status_' . $type], [
                                                                                    'value' => $status
                                                                                ]);
                                                                            } else if ($tab == 'advertisement-resume') {
                                                                                Helpers::businessUpdateOrInsert(['key' => 'advertisement_resume_mail_status_' . $type], [
                                                                                    'value' => $status
                                                                                ]);
                                                                            } else if ($tab == 'advertisement-approved') {
                                                                                Helpers::businessUpdateOrInsert(['key' => 'advertisement_approved_mail_status_' . $type], [
                                                                                    'value' => $status
                                                                                ]);
                                                                            } else if ($tab == 'advertisement-create') {
                                                                                Helpers::businessUpdateOrInsert(['key' => 'advertisement_create_mail_status_' . $type], [
                                                                                    'value' => $status
                                                                                ]);
                                                                            } else if ($tab == 'advertisement-pause') {
                                                                                Helpers::businessUpdateOrInsert(['key' => 'advertisement_pause_mail_status_' . $type], [
                                                                                    'value' => $status
                                                                                ]);
                                                                            } else if ($tab == 'advertisement-deny') {
                                                                                Helpers::businessUpdateOrInsert(['key' => 'advertisement_deny_mail_status_' . $type], [
                                                                                    'value' => $status
                                                                                ]);
                                                                            }
                                                                            
                                                                            Toastr::success(translate('messages.email_status_updated'));
                                                                            return back();
                                                                            
                                                                        }
                                                                        
                                                                        public function login_url_page()
                                                                        {
                                                                            $data = array_column(DataSetting::whereIn('key', ['store_employee_login_url', 'store_login_url', 'admin_employee_login_url', 'admin_login_url'
                                                                            ])->get(['key', 'value'])->toArray(), 'value', 'key');
                                                                            
                                                                            return view('admin-views.login-setup.login_setup', compact('data'));
                                                                        }
                                                                        
                                                                        
                                                                        public function login_page()
                                                                        {
                                                                            
                                                                            abort(404);
                                                                            return view('admin-views.login-setup.login_page');
                                                                        }
                                                                        
                                                                        public function login_url_page_update(Request $request)
                                                                        {
                                                                            
                                                                            $request->validate([
                                                                                'type' => 'required',
                                                                                'admin_login_url' => 'nullable|regex:/^[a-zA-Z0-9\-\_]+$/u|unique:data_settings,value',
                                                                                'admin_employee_login_url' => 'nullable|regex:/^[a-zA-Z0-9\-\_]+$/u|unique:data_settings,value',
                                                                                'store_login_url' => 'nullable|regex:/^[a-zA-Z0-9\-\_]+$/u|unique:data_settings,value',
                                                                                'store_employee_login_url' => 'nullable|regex:/^[a-zA-Z0-9\-\_]+$/u|unique:data_settings,value',
                                                                            ]);
                                                                            
                                                                            if ($request->type == 'admin') {
                                                                                DataSetting::query()->updateOrInsert(['key' => 'admin_login_url', 'type' => 'login_admin'], [
                                                                                    'value' => $request->admin_login_url
                                                                                ]);
                                                                            } elseif ($request->type == 'admin_employee') {
                                                                                DataSetting::query()->updateOrInsert(['key' => 'admin_employee_login_url', 'type' => 'login_admin_employee'], [
                                                                                    'value' => $request->admin_employee_login_url
                                                                                ]);
                                                                            } elseif ($request->type == 'store') {
                                                                                DataSetting::query()->updateOrInsert(['key' => 'store_login_url', 'type' => 'login_store'], [
                                                                                    'value' => $request->store_login_url
                                                                                ]);
                                                                            } elseif ($request->type == 'store_employee') {
                                                                                DataSetting::query()->updateOrInsert(['key' => 'store_employee_login_url', 'type' => 'login_store_employee'], [
                                                                                    'value' => $request->store_employee_login_url
                                                                                ]);
                                                                            }
                                                                            Toastr::success(translate('messages.update_successfull'));
                                                                            return back();
                                                                        }
                                                                        
                                                                        public function remove_image(Request $request)
                                                                        {
                                                                            
                                                                            $request->validate([
                                                                                'model_name' => 'required',
                                                                                'id' => 'required',
                                                                                'image_path' => 'required',
                                                                                'field_name' => 'required',
                                                                            ]);
                                                                            try {
                                                                                
                                                                                $model_name = $request->model_name;
                                                                                $model = app("\\App\\Models\\{$model_name}");
                                                                                $data = $model->where('id', $request->id)->first();
                                                                                
                                                                                $data_value = $data?->{$request->field_name};
                                                                                
                                                                                if ($request?->json == 1) {
                                                                                    $data_value = json_decode($data?->value, true);
                                                                                    
                                                                                    Helpers::check_and_delete($request->image_path . '/', $data_value[$request->field_name]);
                                                                                    
                                                                                    $data_value[$request->field_name] = null;
                                                                                    $data->value = json_encode($data_value);
                                                                                } else {
                                                                                    
                                                                                    Helpers::check_and_delete($request->image_path . '/', $data_value);
                                                                                    
                                                                                    $data->{$request->field_name} = null;
                                                                                }
                                                                                
                                                                                $data?->save();
                                                                                
                                                                            } catch (\Throwable $th) {
                                                                                Toastr::error($th->getMessage() . 'Line....' . $th->getLine());
                                                                                return back();
                                                                            }
                                                                            Toastr::success(translate('messages.Image_removed_successfully'));
                                                                            return back();
                                                                        }
                                                                        
                                                                        public function react_setup()
                                                                        {
                                                                            Helpers::react_domain_status_check();
                                                                            return view('admin-views.business-settings.react-setup');
                                                                        }
                                                                        
                                                                        public function react_update(Request $request)
                                                                        {
                                                                            $request->validate([
                                                                                'react_license_code' => 'required',
                                                                                'react_domain' => 'required'
                                                                            ], [
                                                                                'react_license_code.required' => translate('messages.license_code_is_required'),
                                                                                'react_domain.required' => translate('messages.doamain_is_required'),
                                                                            ]);
                                                                            if (Helpers::activation_submit($request['react_license_code'])) {
                                                                                Helpers::businessUpdateOrInsert(['key' => 'react_setup'], [
                                                                                    'value' => json_encode([
                                                                                        'status' => 1,
                                                                                        'react_license_code' => $request['react_license_code'],
                                                                                        'react_domain' => $request['react_domain'],
                                                                                        'react_platform' => 'codecanyon'
                                                                                        ])
                                                                                    ]);
                                                                                    
                                                                                    Toastr::success(translate('messages.react_data_updated'));
                                                                                    return back();
                                                                                } elseif (Helpers::react_activation_check($request->react_domain, $request->react_license_code)) {
                                                                                    
                                                                                    Helpers::businessUpdateOrInsert(['key' => 'react_setup'], [
                                                                                        'value' => json_encode([
                                                                                            'status' => 1,
                                                                                            'react_license_code' => $request['react_license_code'],
                                                                                            'react_domain' => $request['react_domain'],
                                                                                            'react_platform' => 'iss'
                                                                                            ])
                                                                                        ]);
                                                                                        
                                                                                        Toastr::success(translate('messages.react_data_updated'));
                                                                                        return back();
                                                                                    }
                                                                                    Toastr::error(translate('messages.Invalid_license_code_or_unregistered_domain'));
                                                                                    return back()->withInput(['invalid-data' => true]);
                                                                                }
                                                                                
                                                                                public function landing_page_settings_update(Request $request)
                                                                                {
                                                                                    // dd($request->all());
                                                                                    $validator = Validator::make($request->all(), [
                                                                                        'landing_integration_via' => 'required',
                                                                                        'redirect_url' => 'required_if:landing_integration_via,url',
                                                                                        'file_upload' => 'mimes:zip'
                                                                                    ]);
                                                                                    
                                                                                    if (!File::exists('resources/views/layouts/landing/custom/index.blade.php') && ($request->landing_integration_via == 'file_upload') && (!$request->file('file_upload'))) {
                                                                                        $validator->getMessageBag()->add('file_upload', translate('messages.zip_file_is_required'));
                                                                                    }
                                                                                    
                                                                                    if ($validator->errors()->count() > 0) {
                                                                                        $error = Helpers::error_processor($validator);
                                                                                        return response()->json(['status' => 'error', 'message' => $error[0]['message']]);
                                                                                    }
                                                                                    
                                                                                    Helpers::businessUpdateOrInsert(['key' => 'landing_integration_type'], [
                                                                                        'value' => $request['landing_integration_via']
                                                                                    ]);
                                                                                    $status = 'success';
                                                                                    $message = translate('updated_successfully!');
                                                                                    
                                                                                    if ($request->landing_integration_via == 'file_upload') {
                                                                                        
                                                                                        $file = $request->file('file_upload');
                                                                                        if ($file) {
                                                                                            
                                                                                            $filename = $file->getClientOriginalName();
                                                                                            $tempPath = $file->storeAs('temp', $filename);
                                                                                            $zip = new \ZipArchive();
                                                                                            if ($zip->open(storage_path('app/' . $tempPath)) === TRUE) {
                                                                                                // Extract the contents to a directory
                                                                                                $extractPath = base_path('resources/views/layouts/landing/custom');
                                                                                                $zip->extractTo($extractPath);
                                                                                                $zip->close();
                                                                                                // dd(File::exists($extractPath.'/index.blade.php'));
                                                                                                if (File::exists($extractPath . '/index.blade.php')) {
                                                                                                    Toastr::success(translate('file_upload_successfully!'));
                                                                                                    $status = 'success';
                                                                                                    $message = translate('file_upload_successfully!');
                                                                                                } else {
                                                                                                    File::deleteDirectory($extractPath);
                                                                                                    $status = 'error';
                                                                                                    $message = translate('invalid_file!');
                                                                                                }
                                                                                            } else {
                                                                                                $status = 'error';
                                                                                                $message = translate('file_upload_fail!');
                                                                                            }
                                                                                            
                                                                                            Storage::delete($tempPath);
                                                                                        }
                                                                                    }
                                                                                    
                                                                                    if ($request->landing_integration_via == 'url') {
                                                                                        Helpers::businessUpdateOrInsert(['key' => 'landing_page_custom_url'], [
                                                                                            'value' => $request['redirect_url']
                                                                                        ]);
                                                                                        
                                                                                        $status = 'success';
                                                                                        $message = translate('url_saved_successfully!');
                                                                                    }
                                                                                    
                                                                                    return response()->json([
                                                                                        'status' => $status,
                                                                                        'message' => $message
                                                                                    ]);
                                                                                }
                                                                                
                                                                                public function delete_custom_landing_page()
                                                                                {
                                                                                    $filePath = 'resources/views/layouts/landing/custom/index.blade.php';
                                                                                    
                                                                                    if (File::exists($filePath)) {
                                                                                        File::delete($filePath);
                                                                                        Toastr::success(translate('messages.File_deleted_successfully'));
                                                                                        return back();
                                                                                    } else {
                                                                                        Toastr::error(translate('messages.File_not_found'));
                                                                                        return back();
                                                                                    }
                                                                                }
                                                                                
                                                                                
                                                                                public static function product_approval_all()
                                                                                {
                                                                                    $temp_data = TempProduct::where('is_rejected', 0)->get();
                                                                                    
                                                                                    foreach ($temp_data as $data) {
                                                                                        $item = Item::withoutGlobalScope('translate')->with('translations')->findOrfail($data->item_id);
                                                                                        
                                                                                        $item->name = $data->name;
                                                                                        $item->description = $data->description;
                                                                                        $item->image = $data->image;
                                                                                        $item->images = $data->images;
                                                                                        
                                                                                        $item->store_id = $data->store_id;
                                                                                        $item->module_id = $data->module_id;
                                                                                        $item->unit_id = $data->unit_id;
                                                                                        
                                                                                        $item->category_id = $data->category_id;
                                                                                        $item->category_ids = $data->category_ids;
                                                                                        
                                                                                        $item->choice_options = $data->choice_options;
                                                                                        $item->food_variations = $data->food_variations;
                                                                                        $item->variations = $data->variations;
                                                                                        $item->add_ons = $data->add_ons;
                                                                                        $item->attributes = $data->attributes;
                                                                                        
                                                                                        $item->price = $data->price;
                                                                                        $item->discount = $data->discount;
                                                                                        $item->discount_type = $data->discount_type;
                                                                                        
                                                                                        $item->available_time_starts = $data->available_time_starts;
                                                                                        $item->available_time_ends = $data->available_time_ends;
                                                                                        $item->maximum_cart_quantity = $data->maximum_cart_quantity;
                                                                                        $item->veg = $data->veg;
                                                                                        
                                                                                        $item->organic = $data->organic;
                                                                                        $item->stock = $data->stock;
                                                                                        $item->is_approved = 1;
                                                                                        
                                                                                        $item->save();
                                                                                        $item->tags()->sync(json_decode($data->tag_ids));
                                                                                        if ($item->module->module_type == 'pharmacy') {
                                                                                            PharmacyItemDetails::updateOrInsert(
                                                                                                ['item_id' => $item->id],
                                                                                                [
                                                                                                    'common_condition_id' => $data->condition_id,
                                                                                                    'is_basic' => $data->basic ?? 0,
                                                                                                    'is_prescription_required' => $data->is_prescription_required ?? 0,
                                                                                                    ]
                                                                                                );
                                                                                            }
                                                                                            if ($item->module->module_type == 'ecommerce') {
                                                                                                EcommerceItemDetails::updateOrInsert(
                                                                                                    ['item_id' => $item->id],
                                                                                                    [
                                                                                                        'brand_id' => $data->brand_id,
                                                                                                        ]
                                                                                                    );
                                                                                                }
                                                                                                $item?->translations()?->delete();
                                                                                                Translation::where('translationable_type', 'App\Models\TempProduct')->where('translationable_id', $data->id)->update([
                                                                                                    'translationable_type' => 'App\Models\Item',
                                                                                                    'translationable_id' => $item->id
                                                                                                ]);
                                                                                                
                                                                                                $data->delete();
                                                                                            }
                                                                                            
                                                                                            return true;
                                                                                        }
                                                                                        
                                                                                        
                                                                                        public function notification_setup(Request $request)
                                                                                        {
                                                                                            
                                                                                            abort_if(!addon_published_status('Rental') && $request?->module == 'rental',404 );
                                                                                            
                                                                                            if (NotificationSetting::count() == 0) {
                                                                                                Helpers::notificationDataSetup();
                                                                                            }
                                                                                            if(addon_published_status('Rental') && $request?->module == 'rental'){
                                                                                                Helpers::getRentalAdminNotificationSetupDatasetup();
                                                                                            }
                                                                                            
                                                                                            Helpers::addNewAdminNotificationSetupDataSetup();
                                                                                            
                                                                                            $data = NotificationSetting::where('module_type', $request?->module == 'rental' ? 'rental'  : 'all')
                                                                                            ->when($request?->type == null || $request?->type == 'admin', function ($query) {
                                                                                                $query->where('type', 'admin');
                                                                                            })
                                                                                            ->when($request?->type == 'store', function ($query) {
                                                                                                $query->where('type', 'store');
                                                                                            })
                                                                                            ->when($request?->type == 'provider', function ($query) {
                                                                                                $query->where('type', 'provider');
                                                                                            })
                                                                                            ->when($request?->type == 'customers', function ($query) {
                                                                                                $query->where('type', 'customer');
                                                                                            })
                                                                                            ->when($request?->type == 'deliveryman', function ($query) {
                                                                                                $query->where('type', 'deliveryman');
                                                                                            })->get();
                                                                                            
                                                                                            
                                                                                            $business_name = BusinessSetting::where('key', 'business_name')->first()?->value;
                                                                                            
                                                                                            return view(  $request?->module == 'rental' ? 'admin-views.business-settings.notification_setup_rental' : 'admin-views.business-settings.notification_setup', compact('business_name', 'data'));
                                                                                            
                                                                                        }
                                                                                        
                                                                                        public function notification_status_change($key, $user_type, $type)
                                                                                        {
                                                                                            $data = NotificationSetting::where('type', $user_type)->where('key', $key)->first();
                                                                                            if (!$data) {
                                                                                                Toastr::error(translate('messages.Notification_settings_not_found'));
                                                                                                return back();
                                                                                            }
                                                                                            if ($type == 'Mail') {
                                                                                                $data->mail_status = $data->mail_status == 'active' ? 'inactive' : 'active';
                                                                                            } elseif ($type == 'push_notification') {
                                                                                                $data->push_notification_status = $data->push_notification_status == 'active' ? 'inactive' : 'active';
                                                                                            } elseif ($type == 'SMS') {
                                                                                                $data->sms_status = $data->sms_status == 'active' ? 'inactive' : 'active';
                                                                                            }
                                                                                            $data?->save();
                                                                                            
                                                                                            Toastr::success(translate('messages.Notification_settings_updated'));
                                                                                            return back();
                                                                                        }
                                                                                        
                                                                                    }
                                                                                    