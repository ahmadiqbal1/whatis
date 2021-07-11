<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/
Auth::routes();

Route::get('/login/{lang?}', 'Auth\LoginController@showLoginForm')->name('login');
Route::get('/register/{lang?}', 'Auth\RegisterController@showRegistrationForm')->name('register');

Route::get('/password/resets/{lang?}', 'Auth\LoginController@showLinkRequestForm')->name('change.langPass');

Route::get(
    '/', [
           'as' => 'dashboard',
           'uses' => 'DashboardController@index',
       ]
)->middleware(
    [
        'XSS',
    ]
);
Route::get(
    '/dashboard', [
                    'as' => 'dashboard',
                    'uses' => 'DashboardController@index',
                ]
)->middleware(
    [
        'XSS',
        'auth',
    ]
);
Route::group(
    [
        'middleware' => [
            'auth',
        ],
    ], function (){
    Route::resource('stores', 'StoreController');
    Route::post('store-setting/{id}', 'StoreController@savestoresetting')->name('settings.store');
}
);
Route::group(['middleware' => ['auth', 'XSS']], function (){
    Route::get('change-language/{lang}', 'LanguageController@changeLanquage')->name('change.language')->middleware(['auth', 'XSS']);
    Route::get('manage-language/{lang}', 'LanguageController@manageLanguage')->name('manage.language')->middleware(['auth', 'XSS']);
    Route::post('store-language-data/{lang}', 'LanguageController@storeLanguageData')->name('store.language.data')->middleware(['auth', 'XSS']);
    Route::get('create-language', 'LanguageController@createLanguage')->name('create.language')->middleware(['auth', 'XSS']);
    Route::post('store-language', 'LanguageController@storeLanguage')->name('store.language')->middleware(['auth', 'XSS']);

    Route::delete('/lang/{lang}', 'LanguageController@destroyLang')->name('lang.destroy')->middleware(['auth', 'XSS']);
});
Route::group(
    [
        'middleware' => [
            'auth',
            'XSS',
        ],
    ], function (){
    Route::get('store-grid/grid', 'StoreController@grid')->name('store.grid');
    Route::get('store-customDomain/customDomain', 'StoreController@customDomain')->name('store.customDomain');
    Route::get('store-subDomain/subDomain', 'StoreController@subDomain')->name('store.subDomain');
    Route::get('store-plan/{id}/plan', 'StoreController@upgradePlan')->name('plan.upgrade');
    Route::get('store-plan-active/{id}/plan/{pid}', 'StoreController@activePlan')->name('plan.active');
    Route::DELETE('store-delete/{id}', 'StoreController@storedestroy')->name('user.destroy');
    Route::DELETE('ownerstore-delete/{id}', 'StoreController@ownerstoredestroy')->name('ownerstore.destroy');
    Route::get('store-edit/{id}', 'StoreController@storedit')->name('user.edit');;
    Route::Put('store-update/{id}', 'StoreController@storeupdate')->name('user.update');;
}
);

Route::get('/store-change/{id}', 'StoreController@changeCurrantStore')->name('change_store')->middleware(
    [
        'auth',
        'XSS',
    ]
);

Route::group(
    [
        'middleware' => [
            'auth',
            'XSS',
        ],
    ],

    function (){
        Route::get('change-language/{lang}', 'LanguageController@changeLanquage')->name('change.language')->middleware(['auth','XSS',]);
        Route::get('manage-language/{lang}', 'LanguageController@manageLanguage')->name('manage.language')->middleware(['auth','XSS',]);
        Route::post('store-language-data/{lang}', 'LanguageController@storeLanguageData')->name('store.language.data')->middleware(['auth','XSS',]);
    Route::get('create-language', 'LanguageController@createLanguage')->name('create.language')->middleware(
        [
            'auth',
            'XSS',
        ]
    );
    Route::post('store-language', 'LanguageController@storeLanguage')->name('store.language')->middleware(
        [
            'auth',
            'XSS',
        ]
    );

    Route::delete('/lang/{lang}', 'LanguageController@destroyLang')->name('lang.destroy')->middleware(
        [
            'auth',
            'XSS',
        ]
    );
}
);
Route::get(
    '/change/mode', [
                      'as' => 'change.mode',
                      'uses' => 'DashboardController@changeMode',
                  ]
);

Route::get('profile', 'DashboardController@profile')->name('profile')->middleware(
    [
        'auth',
        'XSS',
    ]
);
Route::put('change-password', 'DashboardController@updatePassword')->name('update.password');
Route::put('edit-profile', 'DashboardController@editprofile')->name('update.account')->middleware(
    [
        'auth',
        'XSS',
    ]
);

Route::get('storeanalytic', 'StoreAnalytic@index')->middleware('auth')->name('storeanalytic')->middleware(['XSS']);

Route::group(
    [
        'middleware' => [
            'auth',
            'XSS',
        ],
    ], function (){
    Route::post('business-setting', 'SettingController@saveBusinessSettings')->name('business.setting');
    Route::post('company-setting', 'SettingController@saveCompanySettings')->name('company.setting');
    Route::post('email-setting', 'SettingController@saveEmailSettings')->name('email.setting');
    Route::post('system-setting', 'SettingController@saveSystemSettings')->name('system.setting');
    Route::post('pusher-setting', 'SettingController@savePusherSettings')->name('pusher.setting');
    Route::get('test-mail', 'SettingController@testMail')->name('test.mail');
    Route::post('test-mail', 'SettingController@testSendMail')->name('test.send.mail');
    Route::get('settings', 'SettingController@index')->name('settings');
    Route::post('payment-setting', 'SettingController@savePaymentSettings')->name('payment.setting');
    Route::post('owner-payment-setting/{slug?}', 'SettingController@saveOwnerPaymentSettings')->name('owner.payment.setting');
    Route::post('owner-email-setting/{slug?}', 'SettingController@saveOwneremailSettings')->name('owner.email.setting');
}
);
Route::group(
    [
        'middleware' => [
            'auth',
            'XSS',
        ],
    ], function (){
    Route::resource('product_categorie', 'ProductCategorieController');
}
);
Route::group(
    [
        'middleware' => [
            'auth',
            'XSS',
        ],
    ], function (){
    Route::resource('product_tax', 'ProductTaxController');
}
);
Route::group(
    [
        'middleware' => [
            'auth',
            'XSS',
        ],
    ], function (){
    Route::resource('shipping', 'ShippingController');
}
);
Route::resource('location', 'LocationController')->middleware(['auth', 'XSS']);
Route::resource('page_options', 'PageOptionController')->middleware(['auth','XSS']);
Route::resource('blog', 'BlogController')->middleware(['auth','XSS']);
Route::get('blog-social', 'BlogController@socialBlog')->name('blog.social')->middleware(['auth','XSS']);
Route::post('store-social-blog', 'BlogController@storeSocialblog')->name('store.socialblog')->middleware(['auth','XSS']);

Route::group(
    [
        'middleware' => [
            'auth',
            'XSS',
        ],
    ], function (){
    Route::resource('shipping', 'ShippingController');
}
);

Route::get(
    '/plans', [
                'as' => 'plans.index',
                'uses' => 'PlanController@index',
            ]
)->middleware(
    [
        'auth',
        'XSS',
    ]
);
Route::get(
    '/plans/create', [
                       'as' => 'plans.create',
                       'uses' => 'PlanController@create',
                   ]
)->middleware(
    [
        'auth',
        'XSS',
    ]
);
Route::post(
    '/plans', [
                'as' => 'plans.store',
                'uses' => 'PlanController@store',
            ]
)->middleware(
    [
        'auth',
        'XSS',
    ]
);
Route::get(
    '/plans/edit/{id}', [
                          'as' => 'plans.edit',
                          'uses' => 'PlanController@edit',
                      ]
)->middleware(
    [
        'auth',
        'XSS',
    ]
);
Route::put(
    '/plans/{id}', [
                     'as' => 'plans.update',
                     'uses' => 'PlanController@update',
                 ]
)->middleware(
    [
        'auth',
        'XSS',
    ]
);
Route::post(
    '/user-plans/', [
                      'as' => 'update.user.plan',
                      'uses' => 'PlanController@userPlan',
                  ]
)->middleware(
    [
        'auth',
        'XSS',
    ]
);

Route::group(
    [
        'middleware' => [
            'auth',
            'XSS',
        ],
    ], function (){
    Route::resource('orders', 'OrderController');
    Route::get('order-receipt/{id}',  'OrderController@receipt')->name('order.receipt');
}
);
Route::group(
    [
        'middleware' => [
            'XSS',
        ],
    ], function (){
    Route::resource('rating', 'RattingController');
    Route::post('rating_view', 'RattingController@rating_view')->name('rating.rating_view');
    Route::get('rating/{slug?}/product/{id}', 'RattingController@rating')->name('rating');
    Route::post('stor_rating/{slug?}/product/{id}', 'RattingController@stor_rating')->name('stor_rating');
    Route::post('edit_rating/product/{id}', 'RattingController@edit_rating')->name('edit_rating');
}
);
Route::group(
    [
        'middleware' => [
            'XSS',
        ],
    ], function (){
    Route::resource('subscriptions', 'SubscriptionController');
    Route::POST('subscriptions/{id}', 'SubscriptionController@store_email')->name('subscriptions.store_email');
}
);
Route::group(
    [
        'middleware' => [
            'auth',
            'XSS'
        ],
    ], function (){
    Route::get('/product-variants/create',['as' => 'product.variants.create','uses' =>'ProductController@productVariantsCreate']);
    Route::get('/get-product-variants-possibilities',['as' => 'get.product.variants.possibilities','uses' =>'ProductController@getProductVariantsPossibilities']);
    Route::get('product/grid', 'ProductController@grid')->name('product.grid');
    Route::delete('product/{id}/delete', 'ProductController@fileDelete')->name('products.file.delete');
}
);
    Route::post('product/{id}/update', 'ProductController@productUpdate')->name('products.update')->middleware(['auth']);
    Route::resource('product', 'ProductController')->middleware(['auth']);
Route::get('get-products-variant-quantity',['as' => 'get.products.variant.quantity','uses' =>'ProductController@getProductsVariantQuantity']);
Route::group(
    [
        'middleware' => [
            'auth',
            'XSS',
        ],
    ], function (){
    //    Route::get('store-grid/grid', 'StoreController@grid')->name('store-grid.grid');
    Route::resource('store-resource', 'StoreController');
}
);

Route::get('store/remove-session/{slug}', 'StoreController@removeSession')->name('remove.session');

Route::get('store/{slug?}/{view?}', 'StoreController@storeSlug')->name('store.slug');
Route::get('store-variant/{slug?}/product/{id}', 'StoreController@storeVariant')->name('store-variant.variant');
Route::post('user-product_qty/{slug?}/product/{id}/{variant_id?}', 'StoreController@productqty')->name('user-product_qty.product_qty');
Route::post('user-location/{slug}/location/{id}', 'StoreController@UserLocation')->name('user.location');
Route::post('user-shipping/{slug}/shipping/{id}', 'StoreController@UserShipping')->name('user.shipping');
Route::delete('delete_cart_item/{slug?}/product/{id}/{variant_id?}', 'StoreController@delete_cart_item')->name('delete.cart_item');

Route::get('store/{slug?}/product/{id}', 'StoreController@productView')->name('store.product.product_view');
Route::get('store-complete/{slug?}/{id}', 'StoreController@complete')->name('store-complete.complete');

Route::post('add-to-cart/{slug?}/{id}/{variant_id?}', 'StoreController@addToCart')->name('user.addToCart');

Route::group(
    ['middleware' => ['XSS']], function (){
    Route::get('order', 'StripePaymentController@index')->name('order.index');
    Route::get('/stripe/{code}', 'StripePaymentController@stripe')->name('stripe');
    Route::post('/stripe/{slug?}', 'StripePaymentController@stripePost')->name('stripe.post');
    Route::post('stripe-payment', 'StripePaymentController@addpayment')->name('stripe.payment');
}
);

Route::post('pay-with-paypal/{slug?}', 'PaypalController@PayWithPaypal')->name('pay.with.paypal')->middleware(['XSS']);

Route::get('{id}/get-payment-status{slug?}', 'PaypalController@GetPaymentStatus')->name('get.payment.status')->middleware(['XSS']);

Route::get('{slug?}/order/{id}', 'StoreController@userorder')->name('user.order');

Route::post('{slug?}/whatsapp', 'StoreController@whatsapp')->name('user.whatsapp');

Route::get('/apply-coupon', ['as' => 'apply.coupon','uses' => 'CouponController@applyCoupon',])->middleware(['auth','XSS',]);
Route::get('/apply-productcoupon', ['as' => 'apply.productcoupon','uses' => 'ProductCouponController@applyProductCoupon',]);

Route::resource('coupons', 'CouponController')->middleware(
    [
        'auth',
        'XSS',
    ]
);
Route::post(
    'prepare-payment', [
                         'as' => 'prepare.payment',
                         'uses' => 'PlanController@preparePayment',
                     ]
)->middleware(
    [
        'auth',
        'XSS',
    ]
);
Route::get(
    '/payment/{code}', [
                         'as' => 'payment',
                         'uses' => 'PlanController@payment',
                     ]
)->middleware(
    [
        'auth',
        'XSS',
    ]
);

Route::post('plan-pay-with-paypal', 'PaypalController@planPayWithPaypal')->name('plan.pay.with.paypal')->middleware(
    [
        'auth',
        'XSS',
    ]
);
Route::get('{id}/get-store-payment-status', 'PaypalController@storeGetPaymentStatus')->name('get.store.payment.status')->middleware(
    [
        'auth',
        'XSS',
    ]
);
Route::get('qr-code', function (){return QrCode::generate();});

Route::get('change-language-store/{slug?}/{lang}', 'LanguageController@changeLanquageStore')->name('change.languagestore')->middleware(['XSS']);

Route::resource('product-coupon', 'ProductCouponController')->middleware(['auth', 'XSS']);

Route::post('store-product', 'StoreController@filterproductview')->name('filter.product.view');
Route::post('store/{slug?}', 'StoreController@changeTheme')->name('store.changetheme');


// Plan Purchase Payments methods

Route::get('plan/prepare-amount', 'PlanController@planPrepareAmount')->name('plan.prepare.amount');
Route::get('paystack-plan/{code}/{plan_id}', 'PaymentController@paystackPlanGetPayment')->name('paystack.plan.callback')->middleware(['auth']);
Route::get('flutterwave-plan/{code}/{plan_id}', 'PaymentController@flutterwavePlanGetPayment')->name('flutterwave.plan.callback')->middleware(['auth']);
Route::get('razorpay-plan/{code}/{plan_id}', 'PaymentController@razorpayPlanGetPayment')->name('razorpay.plan.callback')->middleware(['auth']);
Route::post('mercadopago-prepare-plan', 'PaymentController@mercadopagoPaymentPrepare')->name('mercadopago.prepare.plan')->middleware(['auth']);

Route::post('paytm-prepare-plan', 'PaymentController@paytmPaymentPrepare')->name('paytm.prepare.plan')->middleware(['auth']);
Route::post('paytm-payment-plan', 'PaymentController@paytmPlanGetPayment')->name('plan.paytm.callback')->middleware(['auth']);

Route::post('mollie-prepare-plan', 'PaymentController@molliePaymentPrepare')->name('mollie.prepare.plan')->middleware(['auth']);
Route::get('mollie-payment-plan/{slug}/{plan_id}', 'PaymentController@molliePlanGetPayment')->name('plan.mollie.callback')->middleware(['auth']);

Route::post('coingate-prepare-plan', 'PaymentController@coingatePaymentPrepare')->name('coingate.prepare.plan')->middleware(['auth']);
Route::get('coingate-payment-plan', 'PaymentController@coingatePlanGetPayment')->name('coingate.mollie.callback')->middleware(['auth']);

Route::post('skrill-prepare-plan', 'PaymentController@skrillPaymentPrepare')->name('skrill.prepare.plan')->middleware(['auth']);
Route::get('skrill-payment-plan', 'PaymentController@skrillPlanGetPayment')->name('plan.skrill.callback')->middleware(['auth']);

//================================= Custom Landing Page ====================================//

Route::get('/landingpage', 'LandingPageSectionsController@index')->name('custom_landing_page.index')->middleware(['auth','XSS']);
Route::get('/LandingPage/show/{id}', 'LandingPageSectionsController@show');
Route::post('/LandingPage/setConetent', 'LandingPageSectionsController@setConetent')->middleware(['auth','XSS']);
Route::get('/get_landing_page_section/{name}', function($name) {return view('custom_landing_page.'.$name);});
Route::post('/LandingPage/removeSection/{id}', 'LandingPageSectionsController@removeSection')->middleware(['auth','XSS']);
Route::post('/LandingPage/setOrder', 'LandingPageSectionsController@setOrder')->middleware(['auth','XSS']);
Route::post('/LandingPage/copySection', 'LandingPageSectionsController@copySection')->middleware(['auth','XSS']);

//================================= Custom Massage Page ====================================//
Route::post('/store/custom-msg/{slug}', 'StoreController@customMassage')->name('customMassage');
Route::post('store/get-massage/{slug}', 'StoreController@getWhatsappUrl')->name('get.whatsappurl');

