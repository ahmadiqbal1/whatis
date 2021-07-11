<?php

namespace App\Http\Controllers;

use App\Coupon;
use App\Order;
use App\Plan;
use App\PlanOrder;
use App\Product;
use App\ProductCoupon;
use App\ProductVariantOption;
use App\Shipping;
use App\Store;
use App\UserCoupon;
use App\Utility;
use CoinGate\CoinGate;
use http\Env\Response;
use PaytmWallet;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use LivePixel\MercadoPago\MP;
use Obydul\LaraSkrill\SkrillClient;
use Obydul\LaraSkrill\SkrillRequest;

class PaymentController extends Controller
{
    //Plan

    // Plan purchase  Payments methods
    public function paystackPlanGetPayment($code, $plan_id, Request $request)
    {
        $user                  = Auth::user();
        $store_id              = Auth::user()->current_store;
        $admin_payment_setting = Utility::getAdminPaymentSetting();
        $plan_id               = Plan::find(\Illuminate\Support\Facades\Crypt::decrypt($plan_id));
        $plan                  = Plan::find($plan_id)->first();

        if($plan)
        {

            try
            {
                $orderID = strtoupper(str_replace('.', '', uniqid('', true)));

                $result = array();
                //The parameter after verify/ is the transaction reference to be verified
                $url = "https://api.paystack.co/transaction/verify/$code";
                $ch  = curl_init();
                curl_setopt($ch, CURLOPT_URL, $url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                curl_setopt(
                    $ch, CURLOPT_HTTPHEADER, [
                           'Authorization: Bearer ' . $admin_payment_setting['paystack_secret_key'],
                       ]
                );
                $responce = curl_exec($ch);
                curl_close($ch);
                if($responce)
                {
                    $result = json_decode($responce, true);
                }
                if(array_key_exists('data', $result) && array_key_exists('status', $result['data']) && ($result['data']['status'] === 'success'))
                {
                    $status = $result['data']['status'];
                    if($request->has('coupon_id') && $request->coupon_id != '')
                    {
                        $coupons = Coupon::find($request->coupon_id);
                        if(!empty($coupons))
                        {
                            $userCoupon         = new UserCoupon();
                            $userCoupon->user   = $user->id;
                            $userCoupon->coupon = $coupons->id;
                            $userCoupon->order  = $orderID;
                            $userCoupon->save();
                            $usedCoupun = $coupons->used_coupon();
                            if($coupons->limit <= $usedCoupun)
                            {
                                $coupons->is_active = 0;
                                $coupons->save();
                            }
                        }
                    }
                    $planorder                 = new PlanOrder();
                    $planorder->order_id       = $orderID;
                    $planorder->name           = $user->name;
                    $planorder->card_number    = '';
                    $planorder->card_exp_month = '';
                    $planorder->card_exp_year  = '';
                    $planorder->plan_name      = $plan->name;
                    $planorder->plan_id        = $plan->id;
                    $planorder->price          = $result['data']['amount'] / 100;
                    $planorder->price_currency = env('CURRENCY');
                    $planorder->txn_id         = $code;
                    $planorder->payment_type   = __('Paystack');
                    $planorder->payment_status = $result['data']['status'];
                    $planorder->receipt        = '';
                    $planorder->user_id        = $user->id;
                    $planorder->store_id       = $store_id;
                    $planorder->save();

                    $assignPlan = $user->assignPlan($plan->id);

                    if($assignPlan['is_success'])
                    {


                        return redirect()->route('plans.index')->with('success', __('Plan activated Successfully.'));
                    }
                    else
                    {


                        return redirect()->route('plans.index')->with('error', $assignPlan['error']);
                    }

                }
                else
                {
                    return redirect()->back()->with('error', __('Transaction Unsuccesfull'));
                }

            }
            catch(\Exception $e)
            {
                return redirect()->route('plans.index')->with('error', __('Transaction has been failed.'));
            }
        }
        else
        {
            return redirect()->route('plans.index')->with('error', __('Plan is deleted.'));
        }
    }

    // Plan flutterwave  Payments methods
    public function flutterwavePlanGetPayment($code, $plan_id, Request $request)
    {
        $user                  = Auth::user();
        $store_id              = Auth::user()->current_store;
        $admin_payment_setting = Utility::getAdminPaymentSetting();
        $plan_id               = Plan::find(\Illuminate\Support\Facades\Crypt::decrypt($plan_id));
        $plan                  = Plan::find($plan_id)->first();

        if($plan)
        {
            $orderID = strtoupper(str_replace('.', '', uniqid('', true)));

            $data = array(
                'txref' => $code,
                'SECKEY' => $admin_payment_setting['flutterwave_secret_key'],
                //secret key from pay button generated on rave dashboard
            );

            // make request to endpoint using unirest.
            $headers = array('Content-Type' => 'application/json');
            $body    = \Unirest\Request\Body::json($data);
            $url     = "https://api.ravepay.co/flwv3-pug/getpaidx/api/v2/verify"; //please make sure to change this to production url when you go live

            // Make `POST` request and handle response with unirest
            $response = \Unirest\Request::post($url, $headers, $body);


            if($response->body->data->status === "successful" && $response->body->data->chargecode === "00")
            {

                if($request->has('coupon_id') && $request->coupon_id != '')
                {
                    $coupons = Coupon::find($request->coupon_id);
                    if(!empty($coupons))
                    {
                        $userCoupon         = new UserCoupon();
                        $userCoupon->user   = $user->id;
                        $userCoupon->coupon = $coupons->id;
                        $userCoupon->order  = $orderID;
                        $userCoupon->save();
                        $usedCoupun = $coupons->used_coupon();
                        if($coupons->limit <= $usedCoupun)
                        {
                            $coupons->is_active = 0;
                            $coupons->save();
                        }
                    }
                }
                $planorder                 = new PlanOrder();
                $planorder->order_id       = $orderID;
                $planorder->name           = $user->name;
                $planorder->card_number    = '';
                $planorder->card_exp_month = '';
                $planorder->card_exp_year  = '';
                $planorder->plan_name      = $plan->name;
                $planorder->plan_id        = $plan->id;
                $planorder->price          = $response->body->data->amount;
                $planorder->price_currency = env('CURRENCY');
                $planorder->txn_id         = $response->body->data->txid;
                $planorder->payment_type   = __('Flutterwave ');
                $planorder->payment_status = $response->body->data->status;
                $planorder->receipt        = '';
                $planorder->user_id        = $user->id;
                $planorder->store_id       = $store_id;
                $planorder->save();

                $assignPlan = $user->assignPlan($plan->id);

                if($assignPlan['is_success'])
                {
                    return redirect()->route('plans.index')->with('success', __('Plan activated Successfully.'));
                }
                else
                {


                    return redirect()->route('plans.index')->with('error', $assignPlan['error']);
                }

            }
            else
            {
                return redirect()->back()->with('error', __('Transaction Unsuccesfull'));
            }
        }
        else
        {
            return redirect()->route('plans.index')->with('error', __('Plan is deleted.'));
        }
    }

    // Plan razorpay  Payments methods
    public function razorpayPlanGetPayment($pay_id, $plan_id, Request $request)
    {
        $user                  = Auth::user();
        $store_id              = Auth::user()->current_store;
        $admin_payment_setting = Utility::getAdminPaymentSetting();
        $plan_id               = Plan::find(\Illuminate\Support\Facades\Crypt::decrypt($plan_id));
        $plan                  = Plan::find($plan_id)->first();

        if($plan)
        {

            try
            {
                $orderID = strtoupper(str_replace('.', '', uniqid('', true)));

                $result = array();
                //The parameter after verify/ is the transaction reference to be verified
                $ch = curl_init('https://api.razorpay.com/v1/payments/' . $pay_id . '');
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
                curl_setopt($ch, CURLOPT_USERPWD, $admin_payment_setting['razorpay_public_key'] . ':' . $admin_payment_setting['razorpay_secret_key']); // Input your Razorpay Key Id and Secret Id here
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                $response = json_decode(curl_exec($ch));
                // check that payment is authorized by razorpay or not

                if($response->status == 'authorized')
                {

                    if($request->has('coupon_id') && $request->coupon_id != '')
                    {
                        $coupons = Coupon::find($request->coupon_id);
                        if(!empty($coupons))
                        {
                            $userCoupon         = new UserCoupon();
                            $userCoupon->user   = $user->id;
                            $userCoupon->coupon = $coupons->id;
                            $userCoupon->order  = $orderID;
                            $userCoupon->save();
                            $usedCoupun = $coupons->used_coupon();
                            if($coupons->limit <= $usedCoupun)
                            {
                                $coupons->is_active = 0;
                                $coupons->save();
                            }
                        }
                    }
                    $planorder                 = new PlanOrder();
                    $planorder->order_id       = $orderID;
                    $planorder->name           = $user->name;
                    $planorder->card_number    = '';
                    $planorder->card_exp_month = '';
                    $planorder->card_exp_year  = '';
                    $planorder->plan_name      = $plan->name;
                    $planorder->plan_id        = $plan->id;
                    $planorder->price          = $response->amount / 100;
                    $planorder->price_currency = env('CURRENCY');
                    $planorder->txn_id         = $pay_id;
                    $planorder->payment_type   = __('Razorpay');
                    $planorder->payment_status = $response->status == 'authorized' ? 'success' : 'failed';
                    $planorder->receipt        = '';
                    $planorder->user_id        = $user->id;
                    $planorder->store_id       = $store_id;
                    $planorder->save();

                    $assignPlan = $user->assignPlan($plan->id);

                    if($assignPlan['is_success'])
                    {
                        return redirect()->route('plans.index')->with('success', __('Plan activated Successfully.'));
                    }
                    else
                    {


                        return redirect()->route('plans.index')->with('error', $assignPlan['error']);
                    }

                }
                else
                {
                    return redirect()->back()->with('error', __('Transaction Unsuccesfull'));
                }

            }
            catch(\Exception $e)
            {
                return redirect()->route('plans.index')->with('error', __('Transaction has been failed.'));
            }
        }
        else
        {
            return redirect()->route('plans.index')->with('error', __('Plan is deleted.'));
        }
    }

    // Mercado Plan PreparePayment
    public function mercadopagoPaymentPrepare(Request $request)
    {
        $validator = \Validator::make(
            $request->all(), [
                               'plan' => 'required',
                               'total_price' => 'required',
                           ]
        );
        if($validator->fails())
        {
            $messages = $validator->getMessageBag();

            return response()->json(
                [
                    'status' => 'error',
                    'error' => $messages->first(),
                ]
            );
        }
        $plan = Plan::find($request->plan)->first();
        if($plan)
        {
            $admin_payment_setting = Utility::getAdminPaymentSetting();
            $preference_data       = array(
                "items" => array(
                    array(
                        "title" => "Plan : " . $plan->name,
                        "quantity" => 1,
                        "currency_id" => env('CURRENCY'),
                        "unit_price" => (float)$request->total_price,
                    ),
                ),
            );
            try
            {
                $mp         = new MP($admin_payment_setting['mercado_app_id'], $admin_payment_setting['mercado_secret_key']);
                $preference = $mp->create_preference($preference_data);

                return response()->json(
                    [
                        'status' => 'success',
                        'url' => $preference['response']['init_point'],
                    ]
                );
            }
            catch(Exception $e)
            {
                return response()->json(
                    [
                        'status' => 'error',
                        'error' => $e->getMessage(),
                    ]
                );
            }
        }

    }

    // Paytm Plan PreparePayment
    public function paytmPaymentPrepare(Request $request)
    {
        $validator = \Validator::make(
            $request->all(), [
                               'plan_id' => 'required',
                               'total_price' => 'required',
                               'mobile_number' => 'required|numeric',
                           ]
        );
        if($validator->fails())
        {
            $messages = $validator->getMessageBag();

            return redirect()->back()->with('error', $messages->first());
        }
        $user    = Auth::user()->current_store;
        $store   = Store::where('id', $user)->first();
        $plan_id = decrypt($request->plan_id);
        $plan    = Plan::find($plan_id)->first();

        if($plan)
        {
            $admin_payment_setting = Utility::getAdminPaymentSetting();
            $order                 = $request->all();
            config(
                [
                    'services.paytm-wallet.env' => $admin_payment_setting['paytm_mode'],
                    'services.paytm-wallet.merchant_id' => $admin_payment_setting['paytm_merchant_id'],
                    'services.paytm-wallet.merchant_key' => $admin_payment_setting['paytm_merchant_key'],
                    'services.paytm-wallet.merchant_website' => 'WEBSTAGING',
                    'services.paytm-wallet.channel' => 'WEB',
                    'services.paytm-wallet.industry_type' => $admin_payment_setting['paytm_industry_type'],
                ]
            );

            $payment = PaytmWallet::with('receive');

            $payment->prepare(
                [
                    'order' => $plan_id,
                    'user' => Auth::user()->id,
                    'mobile_number' => $request->mobile_number,
                    'email' => Auth::user()->email,
                    'amount' => $request->total_price,
                    'callback_url' => route('plan.paytm.callback', 'store=' . $store->slug),
                ]
            );

            return $payment->receive();

        }

    }

    public function paytmPlanGetPayment(Request $request)
    {
        $user                  = Auth::user();
        $store_id              = Auth::user()->current_store;
        $admin_payment_setting = Utility::getAdminPaymentSetting();
        $plan_id               = $request->ORDERID;
        $plan                  = Plan::find($plan_id);

        if($plan)
        {
            $orderID = strtoupper(str_replace('.', '', uniqid('', true)));
            config(
                [
                    'services.paytm-wallet.env' => $admin_payment_setting['paytm_mode'],
                    'services.paytm-wallet.merchant_id' => $admin_payment_setting['paytm_merchant_id'],
                    'services.paytm-wallet.merchant_key' => $admin_payment_setting['paytm_merchant_key'],
                    'services.paytm-wallet.merchant_website' => 'WEBSTAGING',
                    'services.paytm-wallet.channel' => 'WEB',
                    'services.paytm-wallet.industry_type' => $admin_payment_setting['paytm_industry_type'],
                ]
            );
            $transaction = PaytmWallet::with('receive');

            // To get raw response as array
            $response = $transaction->response();

            if($transaction->isSuccessful())
            {
                if($request->has('coupon_id') && $request->coupon_id != '')
                {
                    $coupons = Coupon::find($request->coupon_id);
                    if(!empty($coupons))
                    {
                        $userCoupon         = new UserCoupon();
                        $userCoupon->user   = $user->id;
                        $userCoupon->coupon = $coupons->id;
                        $userCoupon->order  = $orderID;
                        $userCoupon->save();
                        $usedCoupun = $coupons->used_coupon();
                        if($coupons->limit <= $usedCoupun)
                        {
                            $coupons->is_active = 0;
                            $coupons->save();
                        }
                    }
                }
                $planorder                 = new PlanOrder();
                $planorder->order_id       = $orderID;
                $planorder->name           = $user->name;
                $planorder->card_number    = '';
                $planorder->card_exp_month = '';
                $planorder->card_exp_year  = '';
                $planorder->plan_name      = $plan->name;
                $planorder->plan_id        = $plan->id;
                $planorder->price          = $response['TXNAMOUNT'];
                $planorder->price_currency = env('CURRENCY');
                $planorder->txn_id         = $response['MID'];
                $planorder->payment_type   = __('Razorpay');
                $planorder->payment_status = 'success';
                $planorder->receipt        = '';
                $planorder->user_id        = $user->id;
                $planorder->store_id       = $store_id;
                $planorder->save();

                $assignPlan = $user->assignPlan($plan->id);

                if($assignPlan['is_success'])
                {
                    return redirect()->route('plans.index')->with('success', __('Plan activated Successfully.'));
                }
                else
                {
                    return redirect()->route('plans.index')->with('error', $assignPlan['error']);
                }

            }
            else
            {
                return redirect()->back()->with('error', __('Transaction Unsuccesfull'));
            }

            session()->forget('mollie_payment_id');
        }
        else
        {
            return redirect()->route('plans.index')->with('error', __('Plan is deleted.'));
        }
    }

    // Mollie Plan PreparePayment
    public function molliePaymentPrepare(Request $request)
    {
        $validator = \Validator::make(
            $request->all(), [
                               'plan_id' => 'required',
                               'total_price' => 'required',
                           ]
        );
        if($validator->fails())
        {
            $messages = $validator->getMessageBag();

            return response()->json(
                [
                    'status' => 'error',
                    'error' => $messages->first(),
                ]
            );
        }
        $user    = Auth::user()->current_store;
        $store   = Store::where('id', $user)->first();
        $plan_id = decrypt($request->plan_id);
        $plan    = Plan::find($plan_id)->first();

        if($plan)
        {
            $admin_payment_setting = Utility::getAdminPaymentSetting();

            $mollie = new \Mollie\Api\MollieApiClient();
            $mollie->setApiKey($admin_payment_setting['mollie_api_key']);

            $payment = $mollie->payments->create(
                [
                    "amount" => [
                        "currency" => env('CURRENCY'),
                        "value" => number_format($request->total_price, 2),
                    ],
                    "description" => $plan->name,
                    "redirectUrl" => route(
                        'plan.mollie.callback', [
                                                  $store->slug,
                                                  $request->plan_id,
                                              ]
                    ),

                ]
            );
            session()->put('mollie_payment_id', $payment->id);

            return redirect($payment->getCheckoutUrl())->with('payment_id', $payment->id);

        }

    }

    public function molliePlanGetPayment(Request $request, $slug, $plan_id)
    {
        $user                  = Auth::user();
        $store_id              = Auth::user()->current_store;
        $admin_payment_setting = Utility::getAdminPaymentSetting();
        $plan_id               = Plan::find(\Illuminate\Support\Facades\Crypt::decrypt($plan_id));
        $plan                  = Plan::find($plan_id)->first();

        if($plan)
        {
            try
            {
                $orderID = strtoupper(str_replace('.', '', uniqid('', true)));

                $mollie = new \Mollie\Api\MollieApiClient();
                $mollie->setApiKey($admin_payment_setting['mollie_api_key']);

                if(session()->has('mollie_payment_id'))
                {
                    $payment = $mollie->payments->get(session()->get('mollie_payment_id'));

                    if($payment->isPaid())
                    {
                        if($request->has('coupon_id') && $request->coupon_id != '')
                        {
                            $coupons = Coupon::find($request->coupon_id);
                            if(!empty($coupons))
                            {
                                $userCoupon         = new UserCoupon();
                                $userCoupon->user   = $user->id;
                                $userCoupon->coupon = $coupons->id;
                                $userCoupon->order  = $orderID;
                                $userCoupon->save();
                                $usedCoupun = $coupons->used_coupon();
                                if($coupons->limit <= $usedCoupun)
                                {
                                    $coupons->is_active = 0;
                                    $coupons->save();
                                }
                            }
                        }
                        $planorder                 = new PlanOrder();
                        $planorder->order_id       = $orderID;
                        $planorder->name           = $user->name;
                        $planorder->card_number    = '';
                        $planorder->card_exp_month = '';
                        $planorder->card_exp_year  = '';
                        $planorder->plan_name      = $plan->name;
                        $planorder->plan_id        = $plan->id;
                        $planorder->price          = $payment->amount->value;
                        $planorder->price_currency = env('CURRENCY');
                        $planorder->txn_id         = $payment->id;
                        $planorder->payment_type   = __('Razorpay');
                        $planorder->payment_status = $payment->status == 'authorized' ? 'success' : 'failed';
                        $planorder->receipt        = '';
                        $planorder->user_id        = $user->id;
                        $planorder->store_id       = $store_id;
                        $planorder->save();

                        $assignPlan = $user->assignPlan($plan->id);

                        if($assignPlan['is_success'])
                        {
                            return redirect()->route('plans.index')->with('success', __('Plan activated Successfully.'));
                        }
                        else
                        {


                            return redirect()->route('plans.index')->with('error', $assignPlan['error']);
                        }

                    }
                    else
                    {
                        return redirect()->back()->with('error', __('Transaction Unsuccesfull'));
                    }

                    session()->forget('mollie_payment_id');


                }
                else
                {
                    session()->flash('error', 'Transaction Error');

                    return redirect('/');
                }
            }
            catch(\Exception $e)
            {
                return redirect()->route('plans.index')->with('error', __('Transaction has been failed.'));
            }
        }
        else
        {
            return redirect()->route('plans.index')->with('error', __('Plan is deleted.'));
        }
    }

    // skrill Plan PreparePayment
    public function skrillPaymentPrepare(Request $request)
    {
        $validator = \Validator::make(
            $request->all(), [
                               'plan_id' => 'required',
                               'total_price' => 'required',
                           ]
        );
        if($validator->fails())
        {
            $messages = $validator->getMessageBag();

            return redirect()->back()->with('error', $messages->first());
        }
        $user    = Auth::user()->current_store;
        $store   = Store::where('id', $user)->first();
        $plan_id = decrypt($request->plan_id);
        $plan    = Plan::find($plan_id)->first();
        $price   = $request->total_price;

        if($plan)
        {
            $admin_payment_setting = Utility::getAdminPaymentSetting();
            $order                 = $request->all();
            if(!empty($store->logo))
            {
                $logo = asset(Storage::url('uploads/store_logo/' . $store->logo));
            }
            else
            {
                $logo = asset(Storage::url('uploads/store_logo/logo.png'));
            }

            $skill               = new SkrillRequest();
            $skill->pay_to_email = $admin_payment_setting['skrill_email'];
            $skill->return_url   = route('plan.skrill.callback') . '?transaction_id=' . MD5($request['transaction_id']);
            $skill->cancel_url   = route('plan.skrill.callback');
            $skill->logo_url     = $logo;

            // create object instance of SkrillRequest
            $skill->transaction_id  = MD5($request['transaction_id']); // generate transaction id
            $skill->amount          = $price;
            $skill->currency        = env('CURRENCY');
            $skill->language        = 'EN';
            $skill->prepare_only    = '1';
            $skill->merchant_fields = 'site_name, customer_email';
            $skill->site_name       = $store->name;
            $skill->customer_email  = Auth::user()->email;

            // create object instance of SkrillClient
            $client = new SkrillClient($skill);
            $sid    = $client->generateSID(); //return SESSION ID

            // handle error
            $jsonSID = json_decode($sid);
            if($jsonSID != null && $jsonSID->code == "BAD_REQUEST")
            {
                return redirect()->back()->with('error', $jsonSID->message);
            }


            // do the payment
            $redirectUrl = $client->paymentRedirectUrl($sid); //return redirect url
            if($request['transaction_id'])
            {
                $data = [
                    'amount' => $price,
                    'trans_id' => MD5($request['transaction_id']),
                    'currency' => $store->currency_code,
                    'slug' => $store->slug,
                ];
                session()->put('skrill_data', $data);

            }

            return redirect($redirectUrl);


        }

    }

    public function skrillPlanGetPayment(Request $request)
    {
        $user                  = Auth::user();
        $store_id              = Auth::user()->current_store;
        $admin_payment_setting = Utility::getAdminPaymentSetting();
        $plan_id               = $request->ORDERID;
        $plan                  = Plan::find($plan_id);

        if($plan)
        {

            if(session()->has('skrill_data'))
            {
                $get_data = session()->get('skrill_data');
                $orderID  = time();

                if($request->has('coupon_id') && $request->coupon_id != '')
                {
                    $coupons = Coupon::find($request->coupon_id);
                    if(!empty($coupons))
                    {
                        $userCoupon         = new UserCoupon();
                        $userCoupon->user   = $user->id;
                        $userCoupon->coupon = $coupons->id;
                        $userCoupon->order  = $orderID;
                        $userCoupon->save();
                        $usedCoupun = $coupons->used_coupon();
                        if($coupons->limit <= $usedCoupun)
                        {
                            $coupons->is_active = 0;
                            $coupons->save();
                        }
                    }
                }
                $planorder                 = new PlanOrder();
                $planorder->order_id       = $orderID;
                $planorder->name           = $user->name;
                $planorder->card_number    = '';
                $planorder->card_exp_month = '';
                $planorder->card_exp_year  = '';
                $planorder->plan_name      = $plan->name;
                $planorder->plan_id        = $plan->id;
                $planorder->price          = isset($get_data['amount']) ? $get_data['amount'] : 0;
                $planorder->price_currency = env('CURRENCY');
                $planorder->txn_id         = $request->has('transaction_id') ? $request->transaction_id : '';;
                $planorder->payment_type   = __('Skrill');
                $planorder->payment_status = 'success';
                $planorder->receipt        = '';
                $planorder->user_id        = $user->id;
                $planorder->store_id       = $store_id;
                $planorder->save();

                $assignPlan = $user->assignPlan($plan->id);

                if($assignPlan['is_success'])
                {
                    return redirect()->route('plans.index')->with('success', __('Plan activated Successfully.'));
                }
                else
                {


                    return redirect()->route('plans.index')->with('error', $assignPlan['error']);
                }

            }
            else
            {
                return redirect()->back()->with('error', __('Transaction Unsuccesfull'));
            }

            session()->forget('mollie_payment_id');

        }
        else
        {
            return redirect()->route('plans.index')->with('error', __('Plan is deleted.'));
        }
    }

    //CoinGate
    public function coingatePaymentPrepare(Request $request)
    {

        $validator = \Validator::make(
            $request->all(), [
                               'plan_id' => 'required',
                               'total_price' => 'required',
                           ]
        );
        if($validator->fails())
        {
            $messages = $validator->getMessageBag();

            return redirect()->back()->with('error', $messages->first());
        }
        $user    = Auth::user()->current_store;
        $store   = Store::where('id', $user)->first();
        $plan_id = decrypt($request->plan_id);
        $plan    = Plan::find($plan_id);
        $price   = $request->total_price;

        if($plan)
        {
            $admin_payment_setting = Utility::getAdminPaymentSetting();
            $order                 = $request->all();
            CoinGate::config(
                array(
                    'environment' => $admin_payment_setting['coingate_mode'],
                    // sandbox OR live
                    'auth_token' => $admin_payment_setting['coingate_auth_token'],
                    'curlopt_ssl_verifypeer' => FALSE
                    // default is false
                )
            );
            $post_params = array(
                'order_id' => time(),
                'price_amount' => $price,
                'price_currency' => env('CURRENCY'),
                'receive_currency' => env('CURRENCY'),
                'callback_url' => url('coingate-payment-plan') . '?plan_id=' . $plan->id . '&user_id=' . Auth::user()->id,
                'cancel_url' => route('plans.index'),
                'success_url' => url('coingate-payment-plan') . '?plan_id=' . $plan->id . '&user_id=' . Auth::user()->id,
                'title' => 'Order #' . time(),
            );

            $order = \CoinGate\Merchant\Order::create($post_params);
            if($order)
            {
                return redirect($order->payment_url);
            }
            else
            {
                return redirect()->back()->with('error', __('opps something wren wrong.'));
            }
        }
    }

    public function coingatePlanGetPayment(Request $request)
    {
        $user                  = Auth::user();
        $plan_id               = $request->plan_id;
        $store_id              = Auth::user()->current_store;
        $admin_payment_setting = Utility::getAdminPaymentSetting();
        $plan                  = Plan::find($plan_id);

        if($plan)
        {
            try
            {
                $orderID = time();
                if($request->has('coupon_id') && $request->coupon_id != '')
                {
                    $coupons = Coupon::find($request->coupon_id);
                    if(!empty($coupons))
                    {
                        $userCoupon         = new UserCoupon();
                        $userCoupon->user   = $user->id;
                        $userCoupon->coupon = $coupons->id;
                        $userCoupon->order  = $orderID;
                        $userCoupon->save();
                        $usedCoupun = $coupons->used_coupon();
                        if($coupons->limit <= $usedCoupun)
                        {
                            $coupons->is_active = 0;
                            $coupons->save();
                        }
                    }
                }

                $planorder                 = new PlanOrder();
                $planorder->order_id       = $orderID;
                $planorder->name           = $user->name;
                $planorder->card_number    = '';
                $planorder->card_exp_month = '';
                $planorder->card_exp_year  = '';
                $planorder->plan_name      = $plan->name;
                $planorder->plan_id        = $plan->id;
                $planorder->price          = $plan->price;
                $planorder->price_currency = env('CURRENCY');
                $planorder->txn_id         = '-';
                $planorder->payment_type   = __('CoinGAte');
                $planorder->payment_status = 'success';
                $planorder->receipt        = '';
                $planorder->user_id        = $user->id;
                $planorder->store_id       = $store_id;
                $planorder->save();

                $assignPlan = $user->assignPlan($plan->id);

                if($assignPlan['is_success'])
                {
                    return redirect()->route('plans.index')->with('success', __('Plan activated Successfully.'));
                }
                else
                {


                    return redirect()->route('plans.index')->with('error', $assignPlan['error']);
                }
            }
            catch(\Exception $e)
            {
                return redirect()->route('plans.index')->with('error', __('Transaction has been failed.'));
            }
        }
        else
        {
            return redirect()->route('plans.index')->with('error', __('Plan is deleted.'));
        }
    }
}
