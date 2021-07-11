<?php

namespace App\Http\Controllers;

use App\Location;
use App\Order;
use App\Product;
use App\Store;
use App\UserDetail;
use App\UserStore;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;

class OrderController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        if(Auth::user()->type == 'super admin')
        {
            $user  = \Auth::user();
            $store = Store::where('id', $user->current_store)->first();

            $orders = Order::orderBy('id', 'DESC')->get();
        }
        else
        {
            $user  = \Auth::user();
            $store = Store::where('id', $user->current_store)->first();

            $orders = Order::orderBy('id', 'DESC')->where('user_id', $store->id)->get();
        }

        return view('orders.index', compact('orders'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
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
        //
    }

    /**
     * Display the specified resource.
     *
     * @param \App\Order $order
     *
     * @return \Illuminate\Http\Response
     */
    public function show(Order $order)
    {
        $store = Store::where('id', $order->user_id)->first();

        if(!empty($order->shipping_data))
        {
            $shipping_data = json_decode($order->shipping_data);
            $location_data = Location::where('id', $shipping_data->location_id)->first();
        }
        else
        {
            $shipping_data = '';
            $location_data = '';
        }

        $user_details = UserDetail::where('id', $order->user_address_id)->first();

        $order_products = json_decode($order->product);
        $sub_total      = 0;
        if(!empty($order_products))
        {
            $grand_total = 0;
            $total_taxs  = 0;
            foreach($order_products->products as $product)
            {
                if($product->variant_id != 0)
                {
                    if(!empty($product->tax))
                    {
                        foreach($product->tax as $tax)
                        {
                            $sub_tax    = ($product->variant_price * $product->quantity * $tax->tax) / 100;
                            $total_taxs += $sub_tax;
                        }
                    }

                    $totalprice  = $product->variant_price * $product->quantity + $total_taxs;
                    $subtotal    = $product->variant_price * $product->quantity;
                    $sub_total   += $subtotal;
                    $grand_total += $totalprice;
                }
                else
                {
                    if(!empty($product->tax))
                    {
                        foreach($product->tax as $tax)
                        {
                            $sub_tax    = ($product->price * $product->quantity * $tax->tax) / 100;
                            $total_taxs += $sub_tax;
                        }
                    }

                    $totalprice  = $product->price * $product->quantity + $total_taxs;
                    $subtotal    = $product->price * $product->quantity;
                    $sub_total   += $subtotal;
                    $grand_total += $totalprice;
                }
            }
        }

        $discount_value = 0;
        $plan_price = 0;
        if(!empty($order->coupon_json))
        {
            $coupons = json_decode($order->coupon_json);
            if(!empty($coupons))
            {
                if($coupons->enable_flat == 'on')
                {
                    $discount_value = $coupons->flat_discount;
                }
                else
                {
                    $discount_value = ($subtotal / 100) * $coupons->discount;
                }
            }

            $plan_price = $subtotal - $discount_value;
        }
        $order_id = Crypt::encrypt($order->id);

        return view('orders.view', compact('order', 'store', 'grand_total', 'order_products', 'sub_total', 'total_taxs', 'user_details', 'order_id', 'shipping_data', 'location_data','plan_price','discount_value'));
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param \App\Order $order
     *
     * @return \Illuminate\Http\Response
     */
    public function edit(Order $order)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @param \App\Order $order
     *
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Order $order)
    {
        $order['status'] = $request->delivered;
        $order->update();

        return response()->json(
            [
                'success' => __('Success fully') . $order['status'],
            ]
        );
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param \App\Order $order
     *
     * @return \Illuminate\Http\Response
     */
    public function destroy(Order $order)
    {
        $order->delete();

        return redirect()->back()->with(
            'success', __('Order Deleted!')
        );
    }

    public function receipt($id)
    {
        $order = Order::find($id);
        $store = Store::where('id', $order->user_id)->first();

        if(!empty($order->shipping_data))
        {
            $shipping_data = json_decode($order->shipping_data);
            $location_data = Location::where('id', $shipping_data->location_id)->first();
        }
        else
        {
            $shipping_data = '';
            $location_data = '';
        }

        $user_details = UserDetail::where('id', $order->user_address_id)->first();

        $order_products = json_decode($order->product);
        $sub_total      = 0;
        if(!empty($order_products))
        {
            $grand_total = 0;
            $total_taxs  = 0;
            foreach($order_products->products as $k => $product)
            {
                if(isset($product->variant_id) && $product->variant_id != 0)
                {
                    if(!empty($product->tax))
                    {
                        foreach($product->tax as $tax)
                        {
                            $sub_tax    = ($product->variant_price * $product->quantity * $tax->tax) / 100;
                            $total_taxs += $sub_tax;
                        }
                    }
                    $totalprice  = $product->variant_price * $product->quantity + $total_taxs;
                    $subtotal    = $product->variant_price * $product->quantity;
                    $sub_total   += $subtotal;
                    $grand_total += $totalprice;
                }
                else
                {
                    if(!empty($product->tax))
                    {
                        foreach($product->tax as $tax)
                        {
                            $sub_tax    = ($product->price * $product->quantity * $tax->tax) / 100;
                            $total_taxs += $sub_tax;
                        }
                    }

                    $totalprice  = $product->price * $product->quantity + $total_taxs;
                    $subtotal    = $product->price * $product->quantity;
                    $sub_total   += $subtotal;
                    $grand_total += $totalprice;
                }
            }
        }
        $discount_value = 0;
        $plan_price = 0;
        if(!empty($order->coupon_json))
        {
            $coupons = json_decode($order->coupon_json);
            if(!empty($coupons))
            {
                if($coupons->enable_flat == 'on')
                {
                    $discount_value = $coupons->flat_discount;
                }
                else
                {
                    $discount_value = ($subtotal / 100) * $coupons->discount;
                }
            }

            $plan_price = $subtotal - $discount_value;
        }
        $order_id = Crypt::encrypt($order->id);

        return view('orders.receipt', compact('order', 'store', 'grand_total', 'order_products', 'sub_total', 'total_taxs', 'user_details', 'order_id', 'shipping_data', 'location_data','discount_value','plan_price'));
    }
}
