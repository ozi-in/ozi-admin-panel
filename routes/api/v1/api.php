<?php

use App\WebSockets\Handler\DMLocationSocketHandler;
use Illuminate\Support\Facades\Route;
use BeyondCode\LaravelWebSockets\Facades\WebSocketsRouter;
use App\Http\Controllers\TATController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::group(['namespace' => 'Api\V1', 'middleware'=>'localization'], function () {
    Route::get('/tat', [TATController::class, 'getDeliveryTAT']);
    Route::group(['prefix' => 'configurations'], function () {
        Route::get('/', 'ExternalConfigurationController@getConfiguration');
        Route::get('/get-external', 'ExternalConfigurationController@getExternalConfiguration');
        Route::post('/store', 'ExternalConfigurationController@updateConfiguration');
    });
    
    Route::get('/terms-and-conditions', 'HomeController@terms_and_conditions');
    Route::get('/about-us', 'HomeController@about_us');
    Route::get('/privacy-policy', 'HomeController@privacy_policy');
    Route::get('/refund-policy', 'HomeController@refund_policy');
    Route::get('/shipping-policy', 'HomeController@shipping_policy');
    Route::get('/cancelation', 'HomeController@cancelation');
    
    
    Route::get('zone/list', 'ZoneController@get_zones');
    Route::get('zone/check', 'ZoneController@zonesCheck');
    
    Route::get('offline_payment_method_list', 'ConfigController@offline_payment_method_list');
    Route::group(['prefix' => 'auth', 'namespace' => 'Auth'], function () {
        Route::post('sign-up', 'CustomerAuthController@register');
        Route::post('login', 'CustomerAuthController@login');
        Route::post('external-login', 'CustomerAuthController@customerLoginFromDrivemond');
        Route::post('verify-phone', 'CustomerAuthController@verify_phone_or_email');
        Route::post('update-info', 'CustomerAuthController@update_info');
        Route::post('firebase-verify-token', 'CustomerAuthController@firebase_auth_verify');
        
        Route::post('forgot-password', 'PasswordResetController@reset_password_request');
        Route::post('verify-token', 'PasswordResetController@verify_token');
        Route::put('reset-password', 'PasswordResetController@reset_password_submit');
        Route::put('firebase-reset-password', 'PasswordResetController@firebase_auth_verify');
        
        Route::post('guest/request','CustomerAuthController@guest_request');
        
        Route::group(['prefix' => 'delivery-man'], function () {
            Route::post('login', 'DeliveryManLoginController@login');
            Route::post('store', 'DeliveryManLoginController@store');
            
            Route::post('forgot-password', 'DMPasswordResetController@reset_password_request');
            Route::post('verify-token', 'DMPasswordResetController@verify_token');
            Route::post('firebase-verify-token', 'DMPasswordResetController@firebase_auth_verify');
            Route::put('reset-password', 'DMPasswordResetController@reset_password_submit');
        });
        Route::group(['prefix' => 'vendor'], function () {
            Route::post('login', 'VendorLoginController@login');
            Route::post('forgot-password', 'VendorPasswordResetController@reset_password_request');
            Route::post('verify-token', 'VendorPasswordResetController@verify_token');
            Route::put('reset-password', 'VendorPasswordResetController@reset_password_submit');
            Route::post('register','VendorLoginController@register');
        });
        
        Route::post('social-login', 'SocialAuthController@social_login');
        Route::post('social-register', 'SocialAuthController@social_register');
    });
    
    //Store Subscription
    Route::group(['prefix' => 'vendor','namespace' => 'Vendor'], function () {
        Route::get('package-view', 'SubscriptionController@package_view');
        Route::post('business_plan', 'SubscriptionController@business_plan');
        Route::post('subscription/payment/api', 'SubscriptionController@subscription_payment_api')->name('subscription_payment_api');
        Route::post('package-renew', 'SubscriptionController@package_renew_change_update_api');
        Route::post('cancel-subscription', 'SubscriptionController@cancelSubscription');
        Route::get('check-product-limits', 'SubscriptionController@checkProductLimits');
    });
    
    // Module
    Route::get('module', 'ModuleController@index');
    
    Route::post('newsletter/subscribe','NewsletterController@index');
    Route::get('landing-page', 'ConfigController@landing_page');
    Route::get('react-landing-page', 'ConfigController@react_landing_page');
    Route::get('flutter-landing-page', 'ConfigController@flutter_landing_page');
    
    Route::group(['prefix' => 'delivery-man'], function () {
        Route::get('last-location', 'DeliverymanController@get_last_location');
        
        
        Route::group(['prefix' => 'reviews','middleware'=>['auth:api']], function () {
            Route::get('/{delivery_man_id}', 'DeliveryManReviewController@get_reviews');
            Route::get('rating/{delivery_man_id}', 'DeliveryManReviewController@get_rating');
            Route::post('/submit', 'DeliveryManReviewController@submit_review');
        });
        Route::group(['middleware'=>['dm.api']], function () {
            Route::get('profile', 'DeliverymanController@get_profile');
            Route::get('notifications', 'DeliverymanController@get_notifications');
            Route::put('update-profile', 'DeliverymanController@update_profile');
            Route::post('update-active-status', 'DeliverymanController@activeStatus');
            Route::get('current-orders', 'DeliverymanController@get_current_orders');
            Route::get('latest-orders', 'DeliverymanController@get_latest_orders');
            Route::post('record-location-data', 'DeliverymanController@record_location_data');
            Route::get('all-orders', 'DeliverymanController@get_all_orders');
            Route::get('order-delivery-history', 'DeliverymanController@get_order_history');
            Route::put('accept-order', 'DeliverymanController@accept_order');
            Route::put('update-order-status', 'DeliverymanController@update_order_status');
            Route::put('update-payment-status', 'DeliverymanController@order_payment_status_update');
            Route::get('order-details', 'DeliverymanController@get_order_details');
            Route::get('order', 'DeliverymanController@get_order');
            Route::put('send-order-otp', 'DeliverymanController@send_order_otp');
            Route::put('update-fcm-token', 'DeliverymanController@update_fcm_token');
            //Remove account
            Route::delete('remove-account', 'DeliverymanController@remove_account');
            
            
            
            Route::get('get-withdraw-method-list', 'DeliverymanController@withdraw_method_list');
            Route::get('get-disbursement-report', 'DeliverymanController@disbursement_report');
            
            Route::group(['prefix' => 'withdraw-method'], function () {
                Route::get('list', 'DeliverymanController@get_disbursement_withdrawal_methods');
                Route::post('store', 'DeliverymanController@disbursement_withdrawal_method_store');
                Route::post('make-default', 'DeliverymanController@disbursement_withdrawal_method_default');
                Route::delete('delete', 'DeliverymanController@disbursement_withdrawal_method_delete');
            });
            
            
            Route::post('make-collected-cash-payment', 'DeliverymanController@make_payment')->name('make_payment');
            Route::post('make-wallet-adjustment', 'DeliverymanController@make_wallet_adjustment')->name('make_wallet_adjustment');
            Route::get('wallet-payment-list', 'DeliverymanController@wallet_payment_list')->name('wallet_payment_list');
            Route::get('wallet-provided-earning-list', 'DeliverymanController@wallet_provided_earning_list')->name('wallet_provided_earning_list');
            
            
            // Chatting
            Route::group(['prefix' => 'message'], function () {
                Route::get('list', 'ConversationController@dm_conversations');
                Route::get('search-list', 'ConversationController@dm_search_conversations');
                Route::get('details', 'ConversationController@dm_messages');
                Route::post('send', 'ConversationController@dm_messages_store');
            });
        });
    });
    
    Route::group(['prefix' => 'vendor', 'namespace' => 'Vendor', 'middleware'=>['vendor.api']], function () {
        Route::get('notifications', 'VendorController@get_notifications');
        Route::get('profile', 'VendorController@get_profile');
        Route::post('update-active-status', 'VendorController@active_status');
        Route::get('earning-info', 'VendorController@get_earning_data');
        Route::put('update-profile', 'VendorController@update_profile');
        Route::put('update-announcment', 'VendorController@update_announcment');
        Route::get('current-orders', 'VendorController@get_current_orders');
        Route::get('schedule-orders', 'VendorController@get_scheduled_orders');
        Route::get('completed-orders', 'VendorController@get_completed_orders');
        Route::get('canceled-orders', 'VendorController@get_canceled_orders');
        Route::get('all-orders', 'VendorController@get_all_orders');
        Route::put('update-order-status', 'VendorController@update_order_status');
        Route::put('update-order-amount', 'VendorController@edit_order_amount');
        Route::get('order-details', 'VendorController@get_order_details');
        Route::get('order', 'VendorController@get_order');
        Route::put('update-fcm-token', 'VendorController@update_fcm_token');
        Route::get('get-basic-campaigns', 'VendorController@get_basic_campaigns');
        Route::put('campaign-leave', 'VendorController@remove_store');
        Route::put('campaign-join', 'VendorController@addstore');
        Route::get('get-withdraw-list', 'VendorController@withdraw_list');
        Route::get('get-items-list', 'VendorController@get_items');
        Route::put('update-bank-info', 'VendorController@update_bank_info');
        Route::post('request-withdraw', 'VendorController@request_withdraw');
        
        Route::put('send-order-otp', 'VendorController@send_order_otp');
        
        Route::post('make-collected-cash-payment', 'VendorController@make_payment')->name('make_payment');
        Route::post('make-wallet-adjustment', 'VendorController@make_wallet_adjustment')->name('make_wallet_adjustment');
        Route::get('wallet-payment-list', 'VendorController@wallet_payment_list')->name('wallet_payment_list');
        
        
        Route::get('get-withdraw-method-list', 'WithdrawMethodController@withdraw_method_list');
        
        Route::group(['prefix' => 'withdraw-method'], function () {
            Route::get('list', 'WithdrawMethodController@get_disbursement_withdrawal_methods');
            Route::post('store', 'WithdrawMethodController@disbursement_withdrawal_method_store');
            Route::post('make-default', 'WithdrawMethodController@disbursement_withdrawal_method_default');
            Route::delete('delete', 'WithdrawMethodController@disbursement_withdrawal_method_delete');
        });
        
        Route::get('get-expense', 'ReportController@expense_report');
        Route::get('get-disbursement-report', 'ReportController@disbursement_report');
        Route::get('subscription-transaction', 'SubscriptionController@transaction');
        
        
        
        //remove account
        Route::delete('remove-account', 'VendorController@remove_account');
        
        Route::get('unit','UnitController@index');
        // Business setup
        Route::put('update-business-setup', 'BusinessSettingsController@update_store_setup');
        
        // Reataurant schedule
        Route::post('schedule/store', 'BusinessSettingsController@add_schedule');
        Route::delete('schedule/{store_schedule}', 'BusinessSettingsController@remove_schedule');
        
        // Attributes
        Route::get('attributes', 'AttributeController@list');
        
        // Addon
        Route::group(['prefix'=>'coupon'], function(){
            Route::get('list', 'CouponController@list');
            Route::get('view', 'CouponController@view');
            Route::get('view-without-translate', 'CouponController@view_without_translate');
            Route::post('store', 'CouponController@store')->name('store');
            Route::post('update', 'CouponController@update');
            Route::post('status', 'CouponController@status')->name('status');
            Route::post('delete', 'CouponController@delete')->name('delete');
            Route::post('search', 'CouponController@search')->name('search');
        });
        // advertisement
        Route::group([ 'prefix' => 'advertisement', 'as' => 'advertisement.'], function () {
            Route::get('/', 'AdvertisementController@index');
            Route::get('details/{id}', 'AdvertisementController@show');
            Route::delete('delete/{id}', 'AdvertisementController@destroy');
            Route::post('store', 'AdvertisementController@store');
            Route::post('update/{id}', 'AdvertisementController@update');
            Route::put('/status', 'AdvertisementController@status')->name('status');
            Route::post('copy-add-post', 'AdvertisementController@copyAddPost');
            
        });
        
        // Addon
        Route::group(['prefix'=>'addon'], function(){
            Route::get('/', 'AddOnController@list');
            Route::post('store', 'AddOnController@store');
            Route::put('update', 'AddOnController@update');
            Route::get('status', 'AddOnController@status');
            Route::delete('delete', 'AddOnController@delete');
        });
        // Banner
        Route::group(['prefix'=>'banner'], function(){
            Route::get('/', 'BannerController@list');
            Route::post('store', 'BannerController@store');
            Route::put('update', 'BannerController@update');
            Route::get('status', 'BannerController@status');
            Route::delete('delete', 'BannerController@delete');
            Route::get('edit/{id}', 'BannerController@edit');
        });
        //category
        Route::group(['prefix' => 'categories'], function () {
            Route::get('/', 'CategoryController@get_categories');
            Route::get('childes/{category_id}', 'CategoryController@get_childes');
        });
        
        Route::group(['prefix' => 'delivery-man'], function () {
            Route::post('store', 'DeliveryManController@store');
            Route::get('list', 'DeliveryManController@list');
            Route::get('preview', 'DeliveryManController@preview');
            Route::get('status', 'DeliveryManController@status');
            Route::post('update/{id}', 'DeliveryManController@update');
            Route::delete('delete', 'DeliveryManController@delete');
            Route::post('search', 'DeliveryManController@search');
        });
        // Food
        Route::group(['prefix'=>'item'], function(){
            Route::post('store', 'ItemController@store');
            Route::put('update', 'ItemController@update');
            Route::delete('delete', 'ItemController@delete');
            Route::get('status', 'ItemController@status');
            Route::get('details/{id}', 'ItemController@get_item');
            Route::POST('search', 'ItemController@search');
            Route::get('reviews', 'ItemController@reviews');
            Route::put('reply-update', 'ItemController@update_reply');
            Route::get('recommended', 'ItemController@recommended');
            Route::get('organic', 'ItemController@organic');
            Route::get('pending/item/list', 'ItemController@pending_item_list');
            Route::get('requested/item/view/{id}', 'ItemController@requested_item_view');
            Route::put('stock-update', 'ItemController@stock_update');
            Route::get('stock-limit-list', 'ItemController@stock_limit_list');
        });
        
        // POS
        Route::group(['prefix'=>'pos'], function(){
            Route::get('orders', 'POSController@order_list');
            Route::post('place-order', 'POSController@place_order');
            Route::get('customers', 'POSController@get_customers');
        });
        
        // Chatting
        Route::group(['prefix' => 'message'], function () {
            Route::get('list', 'ConversationController@conversations');
            Route::get('search-list', 'ConversationController@search_conversations');
            Route::get('details', 'ConversationController@messages');
            Route::post('send', 'ConversationController@messages_store');
        });
    });
    
    Route::group(['prefix' => 'config'], function () {
        Route::get('/', 'ConfigController@configuration');
        Route::get('/get-zone-id', 'ConfigController@get_zone');
        Route::get('place-api-autocomplete', 'ConfigController@place_api_autocomplete');
        Route::get('distance-api', 'ConfigController@distance_api');
        Route::get('direction-api', 'ConfigController@direction_api');
        Route::get('place-api-details', 'ConfigController@place_api_details');
        Route::get('geocode-api', 'ConfigController@geocode_api');
        Route::get('get-PaymentMethods', 'ConfigController@getPaymentMethods');
    });
    
    Route::group(['prefix' => 'testimonial'], function () {
        Route::get('/', 'TestimonialController@get_tetimonial_lists');
        
    });
    
    Route::get('customer/order/cancellation-reasons', 'OrderController@cancellation_reason');
    Route::get('customer/automated-message', 'OrderController@automatedMessage');
    
    Route::get('item/get-generic-name-list', 'ItemController@getGenericNameList');
    Route::get('item/get-allergy-name-list', 'ItemController@getAllergyNameList');
    Route::get('item/get-nutrition-name-list', 'ItemController@getNutritionNameList');
    
    Route::get('customer/order/parcel-instructions', 'OrderController@parcel_instructions');
    Route::get('most-tips', 'OrderController@most_tips');
    Route::get('stores/details/{id}', 'StoreController@get_details');
    
    Route::group(['middleware'=>['module-check']], function(){
        Route::group(['prefix' => 'customer', 'middleware' => 'auth:api'], function () {
            Route::post('get-data', 'CustomerController@getCustomer');
            Route::post('external-update-data', 'CustomerController@externalUpdateCustomer')->withoutMiddleware(['auth:api','module-check']);
            Route::get('notifications', 'NotificationController@get_notifications');
            Route::get('info', 'CustomerController@info');
            Route::get('update-zone', 'CustomerController@update_zone');
            Route::post('update-profile', 'CustomerController@update_profile');
            Route::post('update-interest', 'CustomerController@update_interest');
            Route::put('cm-firebase-token', 'CustomerController@update_cm_firebase_token');
            Route::get('suggested-items', 'CustomerController@get_suggested_item');
            Route::get('trending-products', 'ItemController@get_trending_products');
            //Remove account
            Route::delete('remove-account', 'CustomerController@remove_account');
            
            Route::group(['prefix' => 'address'], function () {
                Route::get('list', 'CustomerController@address_list');
                Route::post('add', 'CustomerController@add_new_address');
                Route::put('update/{id}', 'CustomerController@update_address');
                Route::delete('delete', 'CustomerController@delete_address');
            });
            
            
            // Chatting
            Route::group(['prefix' => 'message'], function () {
                Route::get('list', 'ConversationController@conversations');
                Route::get('search-list', 'ConversationController@search_conversations');
                Route::get('details', 'ConversationController@messages');
                Route::post('send', 'ConversationController@messages_store');
            });
            
            Route::group(['prefix' => 'wish-list'], function () {
                Route::get('/', 'WishlistController@wish_list');
                Route::post('add', 'WishlistController@add_to_wishlist');
                Route::delete('remove', 'WishlistController@remove_from_wishlist');
            });
            
            //Loyalty
            Route::group(['prefix'=>'loyalty-point'], function() {
                Route::post('point-transfer', 'LoyaltyPointController@point_transfer');
                Route::get('transactions', 'LoyaltyPointController@transactions');
            });
            
            Route::group(['prefix'=>'wallet'], function() {
                Route::get('transactions', 'WalletController@transactions');
                Route::get('bonuses', 'WalletController@get_bonus');
                Route::post('add-fund', 'WalletController@add_fund');
                #handshake
                Route::post('transfer-mart-to-drivemond', 'WalletController@transferMartToDrivemondWallet');
                Route::post('transfer-mart-from-drivemond', 'WalletController@transferMartFromDrivemondWallet')->withoutMiddleware('auth:api');
            });
            
            Route::get('visit-again', 'OrderController@order_again');
            
            Route::get('review-reminder', 'CustomerController@review_reminder');
            Route::get('review-reminder-cancel', 'CustomerController@review_reminder_cancel');
            
        });
        Route::group(['prefix' => 'customer', 'middleware' => 'apiGuestCheck'], function () {
            Route::group(['prefix' => 'order'], function () {
                Route::get('list', 'OrderController@get_order_list');
                Route::get('running-orders', 'OrderController@get_running_orders');
                Route::get('details', 'OrderController@get_order_details');
                Route::post('place', 'OrderController@place_order');
                Route::post('prescription/place', 'OrderController@prescription_place_order');
                Route::put('cancel', 'OrderController@cancel_order');
                Route::post('refund-request', 'OrderController@refund_request');
                Route::get('refund-reasons', 'OrderController@refund_reasons');
                Route::get('track', 'OrderController@track_order');
                Route::put('payment-method', 'OrderController@update_payment_method');
                Route::put('offline-payment', 'OrderController@offline_payment');
                Route::put('offline-payment-update', 'OrderController@update_offline_payment_info');
                
            });
            
            Route::group(['prefix'=>'cart'], function() {
                Route::get('list', 'CartController@get_carts');
                Route::post('add', 'CartController@add_to_cart');
                Route::post('cart_item/{id}/gift', 'CartController@updateGiftStatus');
                Route::post('update', 'CartController@update_cart');
                Route::delete('remove-item', 'CartController@remove_cart_item');
                Route::delete('remove', 'CartController@remove_cart');
            });
            
        });
        
        Route::group(['prefix' => 'items'], function () {
            Route::get('latest', 'ItemController@get_latest_products');
            Route::get('new-arrival', 'ItemController@get_new_products');
            Route::get('popular', 'ItemController@get_popular_products');
            Route::get('most-reviewed', 'ItemController@get_most_reviewed_products');
            Route::get('discounted', 'ItemController@get_discounted_products');
            Route::get('set-menu', 'ItemController@get_set_menus');
            Route::get('search', 'ItemController@get_searched_products');
            Route::get('search-suggestion', 'ItemController@get_searched_products_suggestion');
            Route::get('details/{id}', 'ItemController@get_product');
            Route::get('related-items/{item_id}', 'ItemController@get_related_products');
            Route::get('related-store-items/{item_id}', 'ItemController@get_related_store_products');
            Route::get('reviews/{item_id}', 'ItemController@get_product_reviews');
            Route::get('rating/{item_id}', 'ItemController@get_product_rating');
            Route::get('recommended', 'ItemController@get_recommended');
            Route::get('basic', 'ItemController@get_popular_basic_products');
            Route::get('suggested', 'ItemController@get_cart_suggest_products');
            Route::get('item-or-store-search', 'ItemController@item_or_store_search');
            Route::post('reviews/submit', 'ItemController@submit_product_review')->middleware('auth:api');
            Route::get('common-conditions', 'ItemController@get_store_condition_products');
            Route::get('get-products', 'ItemController@get_products');
        });
        
        Route::group(['prefix' => 'stores'], function () {
            Route::get('get-stores/{filter_data}', 'StoreController@get_stores');
            Route::get('latest', 'StoreController@get_latest_stores');
            Route::get('popular', 'StoreController@get_popular_stores');
            Route::get('recommended', 'StoreController@get_recommended_stores');
            Route::get('discounted', 'StoreController@get_discounted_stores');
            Route::get('top-rated', 'StoreController@get_top_rated_stores');
            Route::get('popular-items/{id}', 'StoreController@get_popular_store_items');
            Route::get('reviews', 'StoreController@reviews');
            Route::get('search', 'StoreController@get_searched_stores');
            Route::get('get-data', 'StoreController@get_combined_data');
            Route::get('top-offer-near-me', 'StoreController@get_top_offer_near_me');
        });
        Route::get('get-combined-data', 'SearchController@get_combined_data');
        
        Route::group(['prefix' => 'banners'], function () {
            Route::get('/', 'BannerController@get_banners');
            Route::get('{store_id}/', 'BannerController@get_store_banners');
        });
        
        Route::group(['prefix' => 'other-banners'], function () {
            Route::get('/', 'OtherBannerController@get_banners');
            Route::get('video-content', 'OtherBannerController@get_video_content');
            Route::get('why-choose', 'OtherBannerController@get_why_choose');
        });
        
        Route::group(['prefix' => 'categories'], function () {
            Route::get('/', 'CategoryController@get_categories');
            Route::get('childes/{category_id}', 'CategoryController@get_childes');
            Route::get('items/list', 'CategoryController@get_category_products');
            Route::get('stores/list', 'CategoryController@get_category_stores');
            Route::get('items/{category_id}', 'CategoryController@get_products');
            Route::get('items/{category_id}/all', 'CategoryController@get_all_products');
            Route::get('stores/{category_id}', 'CategoryController@get_stores');
            Route::get('featured/items', 'CategoryController@get_featured_category_products');
            Route::get('popular', 'CategoryController@get_popular_category_list');
        });
        
        Route::group(['prefix' => 'common-condition'], function () {
            Route::get('/', 'CommonConditionController@get_conditions');
            Route::get('/list', 'CommonConditionController@getCommonConditionList');
            Route::get('items/{condition_id}', 'CommonConditionController@get_products');
        });
        
        Route::group(['prefix' => 'brand'], function () {
            Route::get('/', 'BrandController@get_brands');
            Route::get('items/{brand_id}', 'BrandController@get_products');
        });
        
        Route::group(['prefix' => 'campaigns'], function () {
            Route::get('basic', 'CampaignController@get_basic_campaigns');
            Route::get('basic-campaign-details', 'CampaignController@basic_campaign_details');
            Route::get('item', 'CampaignController@get_item_campaigns');
        });
        
        Route::group(['prefix' => 'flash-sales'], function () {
            Route::get('/', 'FlashSaleController@get_flash_sales');
            Route::get('/items', 'FlashSaleController@get_flash_sale_items');
        });
        
        Route::get('coupon/list/all', 'CouponController@list');
        Route::group(['prefix' => 'coupon', 'middleware' => 'auth:api'], function () {
            Route::get('list', 'CouponController@list');
            Route::get('apply', 'CouponController@apply');
        });
        Route::group(['prefix' => 'cashback', 'middleware' => 'auth:api'], function () {
            Route::get('list', 'CashBackController@list');
            Route::get('getCashback', 'CashBackController@getCashback');
        });
        
        Route::get('parcel-category','ParcelCategoryController@index');
        Route::get('advertisement/list', 'AdvertisementController@get_adds');
        
    });
    Route::get('vehicle/extra_charge', 'ConfigController@extra_charge');
    Route::get('get-vehicles', 'ConfigController@get_vehicles');
});

WebSocketsRouter::webSocket('/delivery-man/live-location', DMLocationSocketHandler::class);
