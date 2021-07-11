<?php

namespace App\Http\Controllers;

use App\Coupon;
use App\Order;
use App\Plan;
use App\ProductCoupon;
use App\Store;
use App\UserCoupon;
use App\Utility;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use MongoDB\Driver\Session;

class ProductCouponController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $user = \Auth::user()->current_store;
        $productcoupons = ProductCoupon::where('store_id', $user)->where('created_by', \Auth::user()->creatorId())->get();

        return view('product-coupon.index', compact('productcoupons'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        return view('product-coupon.create');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $arrValidate = [
            'name' => 'required',
            'limit' => 'required|numeric',
            'code' => 'required',
        ];

        if($request->enable_flat == 'on')
        {
            $arrValidate['pro_flat_discount'] = 'required';
        }
        else
        {
            $arrValidate['discount'] = 'required';
        }
        $validator = \Validator::make(
            $request->all(), $arrValidate
        );
        if($validator->fails())
        {
            $messages = $validator->getMessageBag();

            return redirect()->back()->with('error', $messages->first());
        }

        $productcoupon              = new ProductCoupon();
        $productcoupon->name        = $request->name;
        $productcoupon->enable_flat = !empty($request->enable_flat) ? $request->enable_flat : 'off';
        if($request->enable_flat == 'on')
        {
            $productcoupon->flat_discount = $request->pro_flat_discount;
        }
        if(empty($request->enable_flat))
        {
            $productcoupon->discount = $request->discount;
        }
        $productcoupon->limit      = $request->limit;
        $productcoupon->code       = strtoupper($request->code);
        $productcoupon->store_id   = \Auth::user()->current_store;
        $productcoupon->created_by = \Auth::user()->creatorId();

        $productcoupon->save();

        return redirect()->route('product-coupon.index')->with('success', __('Coupon successfully created!'));
    }

    /**
     * Display the specified resource.
     *
     * @param \App\ProductCoupon $productCoupon
     *
     * @return \Illuminate\Http\Response
     */
    public function show(ProductCoupon $productCoupon)
    {
        $productCoupons = Order::where('coupon', $productCoupon->id)->get();

        return view('product-coupon.view', compact('productCoupons', 'productCoupon'));
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param \App\ProductCoupon $productCoupon
     *
     * @return \Illuminate\Http\Response
     */
    public function edit(ProductCoupon $productCoupon)
    {
        return view('product-coupon.edit', compact('productCoupon'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @param \App\ProductCoupon $productCoupon
     *
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, ProductCoupon $productCoupon)
    {
        $arrValidate = [
            'name' => 'required',
            'limit' => 'required|numeric',
            'code' => 'required',
        ];

        if($request->enable_flat == 'on')
        {
            $arrValidate['pro_flat_discount'] = 'required';
        }
        else
        {
            $arrValidate['discount'] = 'required';
        }
        $validator = \Validator::make(
            $request->all(), $arrValidate
        );
        if($validator->fails())
        {
            $messages = $validator->getMessageBag();

            return redirect()->back()->with('error', $messages->first());
        }

        $productCoupon->name        = $request->name;
        $productCoupon->enable_flat = !empty($request->enable_flat) ? $request->enable_flat : 'off';
        if($request->enable_flat == 'on')
        {
            $productCoupon->flat_discount = $request->pro_flat_discount;
        }
        if(empty($request->enable_flat))
        {
            $productCoupon->discount = $request->discount;
        }
        $productCoupon->limit      = $request->limit;
        $productCoupon->code       = strtoupper($request->code);
        $productCoupon->store_id   = \Auth::user()->current_store;
        $productCoupon->created_by = \Auth::user()->creatorId();
        $productCoupon->update();

        return redirect()->route('product-coupon.index')->with('success', __('Coupon successfully updated!'));
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param \App\ProductCoupon $productCoupon
     *
     * @return \Illuminate\Http\Response
     */
    public function destroy(ProductCoupon $productCoupon)
    {
        $productCoupon->delete();

        return redirect()->route('product-coupon.index')->with('success', __('Coupon successfully deleted!'));
    }

    public function applyProductCoupon(Request $request)
    {
        if($request->price != '' && $request->coupon != '')
        {
            $original_price = $request->price;
            $store          = Store::where('id', $request->store_id)->first();
            $cart           = session()->get($store->slug);


            $coupons = ProductCoupon::where('code', strtoupper($request->coupon))->first();

            if(!empty($coupons))
            {
                $usedCoupun = $coupons->product_coupon();

                if($coupons->limit == $usedCoupun)
                {
                    return response()->json(
                        [
                            'is_success' => false,
                            'final_price' => $original_price,
                            'price' => number_format($request->price, \Utility::getValByName('decimal_number')),
                            'message' => __('This coupon code has expired!'),
                        ]
                    );
                }
                else
                {
                    $requestprice = preg_replace('/[^0-9,"."]/', '', $request->price);
                    if($coupons->enable_flat == 'on')
                    {
                        $discount_value = $coupons->flat_discount;
                    }
                    else
                    {
                        $discount_value = ($requestprice / 100) * $coupons->discount;
                    }

                    $plan_price = $requestprice - $discount_value;

                    if($plan_price < 0)
                    {
                        return response()->json(
                            [
                                'is_success' => false,
                                'final_price' => $original_price,
                                'price' => number_format($request->price),
                                'message' => __('This coupon is in valid!'),
                            ]
                        );
                    }
                    if(!empty($request->shipping_price) && $request->shipping_price != '0.00')
                    {
                        $price = self::formatPrice($requestprice - $discount_value + preg_replace('/[^0-9,"."]/', '', $request->shipping_price), $request->store_id);
                    }
                    else
                    {
                        $price = self::formatPrice($requestprice - $discount_value, $request->store_id);
                    }
                    $discount_value = '-' . self::formatPrice($discount_value, $request->store_id);

                    $cart['coupon'] = [
                        'coupon' => $coupons,
                        'discount_price' => $discount_value,
                        'final_price' => $price,
                        'data_id' => $coupons->id,
                    ];
                    session()->put($store->slug, $cart);

                    return response()->json(
                        [
                            'is_success' => true,
                            'discount_price' => $discount_value,
                            'final_price' => $price,
                            'data_id' => $coupons->id,
                            'price' => number_format($plan_price, Utility::getValByName('decimal_number')),
                            'message' => __('Coupon code has applied successfully!'),
                        ]
                    );
                }
            }
            else
            {
                return response()->json(
                    [
                        'is_success' => false,
                        'final_price' => $original_price,
                        'price' => $request->price,
                        Utility::getValByName('decimal_number'),
                        'message' => __('This coupon code is invalid or has expired!'),
                    ]
                );
            }
        }
        else
        {
            return response()->json(
                [
                    'is_success' => false,
                    'message' => __('Your cart is empty!'),
                ]
            );
        }
    }

    public function formatPrice($price, $store_id)
    {
        $store = Store::where('id', $store_id)->first();

        return $store->currency . number_format((float)$price, 2, '.', '');
    }
}
