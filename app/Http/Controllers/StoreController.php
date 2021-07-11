<?php

namespace App\Http\Controllers;

use App\Location;
use App\Order;
use App\Plan;
use App\PlanOrder;
use App\Product;
use App\Product_images;
use App\ProductCategorie;
use App\ProductCoupon;
use App\ProductVariantOption;
use App\Shipping;
use App\Store;
use App\User;
use App\UserDetail;
use App\UserStore;
use App\Utility;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use function GuzzleHttp\Promise\queue;
use function Psy\sh;

class StoreController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function __construct()
    {
        if(Auth::check())
        {
            $user  = Auth::user()->current_store;
            $store = Store::where('id', $user)->first();
            \App::setLocale(isset($store->lang) ? $store->lang : 'en');
        }
        else
        {
            $slug          = \Route::current()->parameter('slug');
            $store         = Store::where('slug', $slug)->first();
            $store_lang    = isset($store->lang) ? $store->lang : 'en';
            $sessoion_lang = session()->get('lang');
            $lang          = !empty($sessoion_lang) ? $sessoion_lang : $store_lang;
            \App::setLocale($lang);
        }
    }

    public function index()
    {
        if(\Auth::user()->type == 'super admin')
        {
            $users  = User::where('created_by', '=', \Auth::user()->creatorId())->where('type', '=', 'Owner')->get();
            $stores = Store::get();

            return view('admin_store.index', compact('stores', 'users'));
        }
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        return view('admin_store.create');
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
        if(\Auth::user()->type == 'super admin')
        {
            $settings = Utility::settings();

            $objUser                    = User::create(
                [
                    'name' => $request['name'],
                    'email' => $request['email'],
                    'password' => Hash::make($request['password']),
                    'type' => 'Owner',
                    'lang' => !empty($settings['default_language']) ? $settings['default_language'] : 'en',
                    'avatar' => 'avatar.png',
                    'plan' => Plan::first()->id,
                    'created_by' => 1,
                ]
            );
            $objStore                   = Store::create(
                [
                    'name' => $request['store_name'],
                    'email' => $request['email'],
                    'logo' => !empty($settings['logo']) ? $settings['logo'] : 'logo.png',
                    'invoice_logo' => !empty($settings['logo']) ? $settings['logo'] : 'invoice_logo.png',
                    'lang' => !empty($settings['default_language']) ? $settings['default_language'] : 'en',
                    'currency' => !empty($settings['currency_symbol']) ? $settings['currency_symbol'] : '$',
                    'currency_code' => !empty($settings->currency) ? $settings->currency : 'USD',
                    'paypal_mode' => 'sandbox',
                    'created_by' => $objUser->id,
                ]
            );
            $objStore->enable_storelink = 'on';
            $objStore->store_theme      = 'style-grey-body.css';
            $objStore->save();
            $objUser->current_store = $objStore->id;
            $objUser->save();
            UserStore::create(
                [
                    'user_id' => $objUser->id,
                    'store_id' => $objStore->id,
                    'permission' => 'Owner',
                ]
            );

            return redirect()->back()->with('success', __('Store added!'));
        }
        else
        {
            if(\Auth::user()->type == 'Owner')
            {
                $user        = \Auth::user();
                $total_store = $user->countStore();
                $creator     = User::find($user->creatorId());
                $plan        = Plan::find($creator->plan);
                $settings    = Utility::settings();

                if($total_store < $plan->max_stores || $plan->max_stores == -1)
                {
                    $objStore                   = Store::create(
                        [
                            'created_by' => \Auth::user()->id,
                            'name' => $request['store_name'],
                            'logo' => !empty($settings['logo']) ? $settings['logo'] : 'logo.png',
                            'invoice_logo' => !empty($settings['logo']) ? $settings['logo'] : 'invoice_logo.png',
                            'lang' => !empty($settings['default_language']) ? $settings['default_language'] : 'en',
                            'currency' => !empty($settings['currency_symbol']) ? $settings['currency_symbol'] : '$',
                            'currency_code' => !empty($settings['currency']) ? $settings['currency'] : 'USD',
                            'paypal_mode' => 'sandbox',
                        ]
                    );
                    $objStore->enable_storelink = 'on';
                    $objStore->store_theme      = 'style-grey-body.css';
                    $objStore->save();
                    \Auth::user()->current_store = $objStore->id;
                    \Auth::user()->save();
                    UserStore::create(
                        [
                            'user_id' => \Auth::user()->id,
                            'store_id' => $objStore->id,
                            'permission' => 'Owner',
                        ]
                    );

                    return redirect()->back()->with('Success', __('Successfully added!'));
                }
                else
                {
                    return redirect()->back()->with('error', __('Your Store limit is over, Please upgrade plan'));
                }

            }
        }

    }

    /**
     * Display the specified resource.
     *
     * @param \App\Store $store
     *
     * @return \Illuminate\Http\Response
     */
    public function show(Store $store)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param \App\Store $store
     *
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        if(\Auth::user()->type == 'super admin')
        {
            $user       = User::find($id);
            $user_store = UserStore::where('user_id', $id)->first();
            $store      = Store::where('id', $user_store->store_id)->first();

            return view('admin_store.edit', compact('store', 'user'));
        }
    }

    /**
     * Update the specified resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @param \App\Store $store
     *
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        if(\Auth::user()->type == 'super admin')
        {
            $store      = Store::find($id);
            $user_store = UserStore::where('store_id', $id)->first();
            $user       = User::where('id', $user_store->user_id)->first();

            $validator = \Validator::make(
                $request->all(), [
                                   'name' => 'required|max:120',
                                   'store_name' => 'required|max:120',
                               ]
            );
            if($validator->fails())
            {
                $messages = $validator->getMessageBag();

                return redirect()->back()->with('error', $messages->first());
            }

            $store['name']  = $request->store_name;
            $store['email'] = $request->email;
            $store->update();

            $user['name']  = $request->name;
            $user['email'] = $request->email;
            $user->update();


            return redirect()->back()->with('Success', __('Successfully Updated!'));
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param \App\Store $store
     *
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $user       = User::find($id);
        $user_store = UserStore::where('user_id', $id)->first();
        $store      = Store::where('id', $user_store->store_id)->first();

        $store->delete();
        $user_store->delete();
        $user->delete();

        return redirect()->back()->with(
            'success', __('Store Deleted!')
        );

    }

    public function customDomain()
    {
        if(\Auth::user()->type == 'super admin')
        {
            $serverName = str_replace(
                [
                    'http://',
                    'https://',
                ], '', env('APP_URL')
            );
            $serverIp   = gethostbyname($serverName);

            if($serverIp != $serverName)
            {
                $serverIp;
            }
            else
            {
                $serverIp = request()->server('SERVER_ADDR');
            }
            $users  = User::where('created_by', '=', \Auth::user()->creatorId())->where('type', '=', 'owner')->get();
            $stores = Store::where('enable_domain', 'on')->get();

            return view('admin_store.custom_domain', compact('users', 'stores', 'serverIp'));
        }
        else
        {
            return redirect()->back()->with('error', __('permission Denied'));
        }

    }

    public function subDomain()
    {
        if(\Auth::user()->type == 'super admin')
        {
            $serverName = str_replace(
                [
                    'http://',
                    'https://',
                ], '', env('APP_URL')
            );
            $serverIp   = gethostbyname($serverName);

            if($serverIp != $serverName)
            {
                $serverIp;
            }
            else
            {
                $serverIp = request()->server('SERVER_ADDR');
            }
            $users  = User::where('created_by', '=', \Auth::user()->creatorId())->where('type', '=', 'owner')->get();
            $stores = Store::where('enable_subdomain', 'on')->get();

            return view('admin_store.subdomain', compact('users', 'stores', 'serverIp'));
        }
        else
        {
            return redirect()->back()->with('error', __('permission Denied'));
        }

    }

    public function ownerstoredestroy($id)
    {
        $user        = Auth::user();
        $store       = Store::find($id);
        $user_stores = UserStore::where('user_id', $user->id)->count();

        if($user_stores > 1)
        {
            UserStore::where('store_id', $id)->delete();
            $store->delete();

            $userstore = UserStore::where('user_id', $user->id)->first();

            $user->current_store = $userstore->id;
            $user->save();

            return redirect()->route('dashboard');
        }
        else
        {
            return redirect()->back()->with('error', __('You have only one store'));
        }


    }

    public function savestoresetting(Request $request, $id)
    {
        $validator = \Validator::make(
            $request->all(), [
                               'name' => 'required|max:120',
                               'image' => 'image|mimes:jpeg,png,jpg,gif,svg|max:2048',
                           ]
        );
        if($request->enable_domain == 'on')
        {
            $validator = \Validator::make(
                $request->all(), [
                                   'domains' => 'required',
                               ]
            );
        }
        if($validator->fails())
        {
            $messages = $validator->getMessageBag();

            return redirect()->back()->with('error', $messages->first());
        }
        if(!empty($request->logo))
        {
            $filenameWithExt = $request->file('logo')->getClientOriginalName();
            $filename        = pathinfo($filenameWithExt, PATHINFO_FILENAME);
            $extension       = $request->file('logo')->getClientOriginalExtension();
            $fileNameToStore = $filename . '_' . time() . '.' . $extension;
            $dir             = storage_path('uploads/store_logo/');
            if(!file_exists($dir))
            {
                mkdir($dir, 0777, true);
            }
            $path = $request->file('logo')->storeAs('uploads/store_logo/', $fileNameToStore);
        }
        if(!empty($request->invoice_logo))
        {
            $extension              = $request->file('invoice_logo')->getClientOriginalExtension();
            $fileNameToStoreInvoice = 'invoice_logo' . '_' . $id . '.' . $extension;
            $dir                    = storage_path('uploads/store_logo/');
            if(!file_exists($dir))
            {
                mkdir($dir, 0777, true);
            }
            $path = $request->file('invoice_logo')->storeAs('uploads/store_logo/', $fileNameToStoreInvoice);
        }
        if($request->enable_domain == 'enable_domain')
        {
            // Remove the http://, www., and slash(/) from the URL
            $input = $request->domains;
            // If URI is like, eg. www.way2tutorial.com/
            $input = trim($input, '/');
            // If not have http:// or https:// then prepend it
            if(!preg_match('#^http(s)?://#', $input))
            {
                $input = 'http://' . $input;
            }
            $urlParts = parse_url($input);
            // Remove www.
            $domain_name = preg_replace('/^www\./', '', $urlParts['host']);
            // Output way2tutorial.com
        }
        if($request->enable_domain == 'enable_subdomain')
        {
            // Remove the http://, www., and slash(/) from the URL
            $input = env('APP_URL');

            // If URI is like, eg. www.way2tutorial.com/
            $input = trim($input, '/');
            // If not have http:// or https:// then prepend it
            if(!preg_match('#^http(s)?://#', $input))
            {
                $input = 'http://' . $input;
            }

            $urlParts = parse_url($input);

            // Remove www.
            $subdomain_name = preg_replace('/^www\./', '', $urlParts['host']);
            // Output way2tutorial.com
            $subdomain_name = $request->subdomain . '.' . $subdomain_name;
        }

        $store = Store::find($id);
        if($store->name != $request->name)
        {
            $data          = ['name' => $request->name];
            $slug          = Store::create($data);
            $store['slug'] = $slug->slug;
        }
        $store['name']  = $request->name;
        $store['email'] = $request->email;
        if($request->enable_domain == 'enable_domain')
        {
            $store['domains'] = $domain_name;
        }
        $store['enable_storelink'] = ($request->enable_domain == 'enable_storelink' || empty($request->enable_domain)) ? 'on' : 'off';
        $store['enable_domain']    = ($request->enable_domain == 'enable_domain') ? 'on' : 'off';
        $store['enable_subdomain'] = ($request->enable_domain == 'enable_subdomain') ? 'on' : 'off';

        if($request->enable_domain == 'enable_subdomain')
        {
            $store['subdomain'] = $subdomain_name;
        }
        $store['about']           = $request->about;
        $store['tagline']         = $request->tagline;
        $store['lang']            = $request->store_default_language;
        $store['storejs']         = $request->storejs;
        $store['whatsapp']        = $request->whatsapp;
        $store['facebook']        = $request->facebook;
        $store['instagram']       = $request->instagram;
        $store['twitter']         = $request->twitter;
        $store['youtube']         = $request->youtube;
        $store['google_analytic'] = $request->google_analytic;
        $store['footer_note']     = $request->footer_note;
        $store['enable_shipping'] = $request->enable_shipping ?? 'off';
        $store['address']         = $request->address;
        $store['city']            = $request->city;
        $store['state']           = $request->state;
        $store['zipcode']         = $request->zipcode;
        $store['country']         = $request->country;
        if(!empty($fileNameToStore))
        {
            $store['logo'] = $fileNameToStore;
        }
        if(!empty($fileNameToStoreInvoice))
        {
            $store['invoice_logo'] = $fileNameToStoreInvoice;
        }
        $store['created_by'] = \Auth::user()->creatorId();
        $store->update();

        return redirect()->back()->with('success', __('Store successfully Update.'));
    }

    public function storeSlug($slug, $view = 'grid')
    {
        if(!Auth::check())
        {
            visitor()->visit($slug);
        }

        $store = Store::where('slug', $slug)->first();
        $order = Order::where('user_id', $store->id)->orderBy('id', 'desc')->first();

        $userstore = UserStore::where('store_id', $store->id)->first();
        $settings  = \DB::table('settings')->where('name', 'company_favicon')->where('created_by', $userstore->user_id)->first();

        $locations = Location::where('store_id', $store->id)->get()->pluck('name', 'id');
        $locations->prepend('Select Location', 0);
        $shippings = Shipping::where('store_id', $store->id)->get();

        if(empty($store))
        {
            return redirect()->back()->with('error', __('Store not available'));
        }
        session(['slug' => $slug]);
        $cart = session()->get($slug);

        $categories = ProductCategorie::where('store_id', $userstore->store_id)->get()->pluck('name', 'id');
        $categories->prepend(__('All'), 0);
        $products = [];
        foreach($categories as $id => $category)
        {
            $product = Product::where('store_id', $store->id);

            if($id != 0)
            {
                $product->whereRaw('FIND_IN_SET("' . $id . '", product_categorie)');
            }
            $product             = $product->get();
            $products[$category] = $product;
        }

        $total_item = 0;

        if(isset($cart['products']))
        {
            foreach($cart['products'] as $item)
            {
                if(isset($cart) && !empty($cart['products']))
                {
                    $total_item = count($cart['products']);
                }
                else
                {
                    $total_item = 0;
                }
            }
        }

        if(!empty($cart['customer']))
        {
            $cust_details = $cart['customer'];
        }
        else
        {
            $cust_details = '';
        }

        if(!empty($cart))
        {
            $pro_cart = $cart;
        }
        else
        {
            $pro_cart = '';
        }

        $encode_product = json_encode($pro_cart);

        $pro_qty  = [];
        $pro_name = [];
        if(!empty($order))
        {
            $order_id = '%23' . str_pad($order->id + 1, 4, "100", STR_PAD_LEFT);
        }
        else
        {
            $order_id = '%23' . str_pad(0 + 1, 4, "100", STR_PAD_LEFT);
        }

        if(!empty($pro_cart['products']))
        {
            foreach($pro_cart['products'] as $key => $item)
            {
                if($item['variant_id'] == 0)
                {
                    $pro_qty[] = $item['quantity'] . ' x ' . $item['product_name'];
                }
                else
                {
                    $pro_qty[] = $item['quantity'] . ' x ' . $item['product_name'] . ' - ' . $item['variant_name'];
                }
            }

        }

        $tax_name  = [];
        $tax_price = [];
        $i         = 0;
        if(isset($cart['products']))
        {
            $carts = $cart['products'];
            foreach($carts as $product)
            {
                if($product['variant_id'] != 0)
                {
                    foreach($product['tax'] as $key => $taxs)
                    {

                        if(!in_array($taxs['tax_name'], $tax_name))
                        {
                            $tax_name[]  = $taxs['tax_name'];
                            $price       = $product['variant_price'] * $product['quantity'] * $taxs['tax'] / 100;
                            $tax_price[] = $price;
                        }
                        else
                        {
                            $price                                                 = $product['variant_price'] * $product['quantity'] * $taxs['tax'] / 100;
                            $tax_price[array_search($taxs['tax_name'], $tax_name)] += $price;
                        }
                    }
                }
                else
                {

                    foreach($product['tax'] as $key => $taxs)
                    {
                        if(!in_array($taxs['tax_name'], $tax_name))
                        {
                            $tax_name[]  = $taxs['tax_name'];
                            $price       = $product['price'] * $product['quantity'] * $taxs['tax'] / 100;
                            $tax_price[] = $price;
                        }
                        else
                        {
                            $price                                                 = $product['price'] * $product['quantity'] * $taxs['tax'] / 100;
                            $tax_price[array_search($taxs['tax_name'], $tax_name)] += $price;
                        }
                    }
                }
                $i++;
            }
        }

        $taxArr['tax']  = $tax_name;
        $taxArr['rate'] = $tax_price;

        $url = 'https://api.whatsapp.com/send?phone=' . $store->whatsapp_number . '&text=Hi%2C%0AWelcome+to+%2A' . $store->name . '%2A%2C%0AYour+order+is+confirmed+%26+your+order+no.+is+' . $order_id . '%0AYour+order+detail+is%3A%0AName%20%3A%0AAddress%20%3A%20address%201%20and%20address%202%0A%0A%7E%7E%7E%7E%7E%7E%7E%7E%7E%7E%7E%7E%7E%7E%7E%7E%7E%7E%7E%7E%7E%7E%7E%7E%0A+' . join("+%2C%0A", $pro_qty) . '%0AOrder%20total%20%3A%0A+%0A+%0A%7E%7E%7E%7E%7E%7E%7E%7E%7E%7E%7E%7E%7E%7E%7E%7E%7E%7E%7E%7E%7E%7E%7E%7E%0ATo+collect+the+order+you+need+to+show+the+receipt+at+the+counter.%0A%0AThanks+' . $store->name . '%0A%0A';

        $pro_qty = join("+%2C%0A", $pro_qty);

        return view('storefront.index', compact('products', 'encode_product', 'order_id', 'order', 'cust_details', 'store', 'settings', 'categories', 'total_item', 'pro_cart', 'url', 'view', 'locations', 'shippings', 'taxArr', 'pro_qty'));
    }

    public function UserLocation($slug, $location_id)
    {
        $store     = Store::where('slug', $slug)->first();
        $shippings = Shipping::where('store_id', $store->id)->whereRaw('FIND_IN_SET("' . $location_id . '", location_id)')->get()->toArray();

        return response()->json(
            [
                'code' => 200,
                'status' => 'Success',
                'shipping' => $shippings,
            ]
        );
    }

    public function UserShipping(Request $request, $slug, $shipping_id)
    {
        $store     = Store::where('slug', $slug)->first();
        $shippings = Shipping::where('store_id', $store->id)->where('id', $shipping_id)->first();

        $shipping_price  = Utility::priceFormat($shippings->price);
        $pro_total_price = str_replace(' ', '', str_replace(',', '', str_replace($store->currency, '', $request->pro_total_price)));
        $total_price     = $shippings->price + $pro_total_price;
        if(!empty($request->coupon))
        {
            $coupons = ProductCoupon::where('code', strtoupper($request->coupon))->first();
            if(!empty($coupons))
            {
                if($coupons->enable_flat == 'on')
                {
                    $discount_value = $coupons->flat_discount;
                }
                else
                {
                    $discount_value = ($pro_total_price / 100) * $coupons->discount;
                }
            }
            else
            {
                $discount_value = 0;
            }
            $total_price = $total_price - $discount_value;
        }

        return response()->json(
            [
                'code' => 200,
                'status' => 'Success',
                'price' => $shipping_price,
                'total_price' => Utility::priceFormat($total_price),
            ]
        );
    }

    public function addToCart(Request $request, $product_id, $slug, $variant_id = 0)
    {
        if($request->ajax())
        {
            $store   = Store::where('slug', $slug)->get();
            $variant = ProductVariantOption::find($variant_id);

            if(empty($store))
            {
                return redirect()->back()->with('error', __('Store not available'));
            }

            $product = Product::find($product_id);
            $cart    = session()->get($slug);

            $quantity = $product->quantity;
            if($variant_id > 0)
            {
                $quantity = $variant->quantity;
            }

            if(!empty($product->is_cover))
            {
                $pro_img = $product->is_cover;
            }
            else
            {
                $pro_img = 'default.jpg';
            }

            $productquantity = $product->quantity;
            $i               = 0;

            //            if(!$product && $quantity == 0)
            if($quantity == 0)
            {
                return response()->json(
                    [
                        'code' => 404,
                        'status' => 'Error',
                        'error' => __('This product is out of stock!'),
                    ]
                );
            }

            $productname      = $product->name;
            $productprice     = $product->price != 0 ? $product->price : 0;
            $originalquantity = (int)$productquantity;

            //product count tax
            $taxes      = Utility::tax($product->product_tax);
            $itemTaxes  = [];
            $producttax = 0;

            if(!empty($taxes))
            {
                foreach($taxes as $tax)
                {
                    if(!empty($tax))
                    {
                        $producttax          = Utility::taxRate($tax->rate, $product->price, 1);
                        $itemTax['tax_name'] = $tax->name;
                        $itemTax['tax']      = $tax->rate;
                        $itemTaxes[]         = $itemTax;
                    }
                }
            }

            $subtotal = Utility::priceFormat($productprice + $producttax);

            if($variant_id > 0)
            {
                $variant_itemTaxes       = [];
                $variant_name            = $variant->name;
                $variant_price           = $variant->price;
                $originalvariantquantity = (int)$variant->quantity;
                //variant count tax
                $variant_taxes      = Utility::tax($product->product_tax);
                $variant_producttax = 0;

                if(!empty($variant_taxes))
                {
                    foreach($variant_taxes as $variant_tax)
                    {
                        if(!empty($variant_tax))
                        {
                            $variant_producttax  = Utility::taxRate($variant_tax->rate, $variant_price, 1);
                            $itemTax['tax_name'] = $variant_tax->name;
                            $itemTax['tax']      = $variant_tax->rate;
                            $variant_itemTaxes[] = $itemTax;
                        }
                    }
                }
                $variant_subtotal = Utility::priceFormat($variant_price * $variant->quantity);
            }

            $time = time();
            // if cart is empty then this the first product
            if(!$cart || !$cart['products'])
            {
                if($variant_id > 0)
                {
                    $cart['products'][$time] = [
                        "product_id" => $product->id,
                        "product_name" => $productname,
                        "image" => Storage::url('uploads/is_cover_image/' . $pro_img),
                        "quantity" => 1,
                        "price" => $productprice,
                        "id" => $product_id,
                        "tax" => $variant_itemTaxes,
                        "subtotal" => $subtotal,
                        "originalquantity" => $originalquantity,
                        "variant_name" => $variant_name,
                        "variant_price" => $variant_price,
                        "variant_qty" => $variant->quantity,
                        "variant_subtotal" => $variant_subtotal,
                        "originalvariantquantity" => $originalvariantquantity,
                        'variant_id' => $variant_id,
                    ];
                }
                else if($variant_id <= 0)
                {
                    $cart['products'][$time] = [
                        "product_id" => $product->id,
                        "product_name" => $productname,
                        "image" => Storage::url('uploads/is_cover_image/' . $pro_img),
                        "quantity" => 1,
                        "price" => $productprice,
                        "id" => $product_id,
                        "tax" => $itemTaxes,
                        "subtotal" => $subtotal,
                        "originalquantity" => $originalquantity,
                        'variant_id' => 0,
                    ];
                }

                session()->put($slug, $cart);

                return response()->json(
                    [
                        'code' => 200,
                        'status' => 'Success',
                        'success' => $productname . __('added to cart successfully!'),
                        'cart' => $cart['products'],
                        'item_count' => count($cart['products']),
                    ]
                );
            }

            // if cart not empty then check if this product exist then increment quantity
            if($variant_id > 0)
            {
                $key = false;
                foreach($cart['products'] as $k => $value)
                {
                    if($variant_id == $value['variant_id'])
                    {
                        $key = $k;
                    }
                }

                if($key !== false && isset($cart['products'][$key]['variant_id']) && $cart['products'][$key]['variant_id'] != 0)
                {
                    if(isset($cart['products'][$key]))
                    {
                        $cart['products'][$key]['quantity']         = $cart['products'][$key]['quantity'] + 1;
                        $cart['products'][$key]['variant_subtotal'] = $cart['products'][$key]['variant_price'] * $cart['products'][$key]['quantity'];

                        if($originalvariantquantity < $cart['products'][$key]['quantity'])
                        {
                            return response()->json(
                                [
                                    'code' => 404,
                                    'status' => 'Error',
                                    'error' => __('This product is out of stock!'),
                                ]
                            );
                        }

                        session()->put($slug, $cart);

                        return response()->json(
                            [
                                'code' => 200,
                                'status' => 'Success',
                                'success' => $productname . __('added to cart successfully!'),
                                'cart' => $cart['products'],
                                'item_count' => count($cart['products']),
                            ]
                        );
                    }
                }
            }
            else if($variant_id <= 0)
            {
                $key = false;

                foreach($cart['products'] as $k => $value)
                {
                    if($product_id == $value['product_id'])
                    {
                        $key = $k;
                    }
                }

                if($key !== false)
                {
                    if(isset($cart['products'][$key]))
                    {
                        $cart['products'][$key]['quantity'] = $cart['products'][$key]['quantity'] + 1;
                        $cart['products'][$key]['subtotal'] = $cart['products'][$key]['price'] * $cart['products'][$key]['quantity'];
                        if($originalquantity < $cart['products'][$key]['quantity'])
                        {
                            return response()->json(
                                [
                                    'code' => 404,
                                    'status' => 'Error',
                                    'error' => __('This product is out of stock!'),
                                ]
                            );
                        }

                        session()->put($slug, $cart);

                        return response()->json(
                            [
                                'code' => 200,
                                'status' => 'Success',
                                'success' => $productname . __('added to cart successfully!'),
                                'cart' => $cart['products'],
                                'item_count' => count($cart['products']),
                            ]
                        );
                    }
                }
            }

            // if item not exist in cart then add to cart with quantity = 1
            if($variant_id > 0)
            {
                $cart['products'][$time] = [
                    "product_id" => $product->id,
                    "product_name" => $productname,
                    "image" => Storage::url('uploads/is_cover_image/' . $pro_img),
                    "quantity" => 1,
                    "price" => $productprice,
                    "id" => $product_id,
                    "tax" => $variant_itemTaxes,
                    "subtotal" => $subtotal,
                    "originalquantity" => $originalquantity,
                    "variant_name" => $variant->name,
                    "variant_price" => $variant->price,
                    "variant_qty" => $variant->quantity,
                    "variant_subtotal" => $variant_subtotal,
                    "originalvariantquantity" => $originalvariantquantity,
                    'variant_id' => $variant_id,
                ];
            }
            else if($variant_id <= 0)
            {
                $cart['products'][$time] = [
                    "product_id" => $product->id,
                    "product_name" => $productname,
                    "image" => Storage::url('uploads/is_cover_image/' . $pro_img),
                    "quantity" => 1,
                    "price" => $productprice,
                    "id" => $product_id,
                    "tax" => $itemTaxes,
                    "subtotal" => $subtotal,
                    "originalquantity" => $originalquantity,
                    'variant_id' => 0,
                ];
            }

            session()->put($slug, $cart);

            return response()->json(
                [
                    'code' => 200,
                    'status' => 'Success',
                    'success' => $productname . __('added to cart successfully!'),
                    'cart' => $cart['products'],
                    'item_count' => count($cart['products']),
                ]
            );
        }
    }

    public function productqty(Request $request, $product_id, $slug, $key = 0)
    {
        $cart = session()->get($slug);
        if($request->product_qty == 0)
        {
            foreach($cart['products'] as $k => $subArr)
            {
                if($k == $key)
                {
                    unset($cart['products'][$key]);

                    session()->put($slug, $cart);

                    return response()->json(
                        [
                            'code' => 200,
                            'status' => 'Success',
                            'success' => __('successfully!'),
                            'product' => $cart['products'],
                            'carttotal' => $cart['products'],
                        ]
                    );
                }
            }
        }

        if($cart['products'][$key]['variant_id'] > 0 && $cart['products'][$key]['originalvariantquantity'] < $request->product_qty)
        {
            return response()->json(
                [
                    'code' => 404,
                    'status' => 'Error',
                    'error' => __('You can only purchese max') . ' ' . $cart['products'][$key]['originalvariantquantity'] . ' ' . __('product!'),
                ]
            );
        }
        else if($cart['products'][$key]['originalquantity'] < $request->product_qty && $cart['products'][$key]['variant_id'] == 0)
        {
            return response()->json(
                [
                    'code' => 404,
                    'status' => 'Error',
                    'error' => __('You can only purchese max') . ' ' . $cart['products'][$key]['originalquantity'] . ' ' . __('product!'),
                ]
            );
        }
        if(isset($cart['products'][$key]))
        {
            $cart['products'][$key]['quantity'] = $request->product_qty;
            $cart['products'][$key]['id']       = $product_id;

            $subtotal = $cart['products'][$key]["price"] * $cart['products'][$key]["quantity"];

            $protax = $cart['products'][$key]["tax"];
            if($protax != 0)
            {
                $taxs = 0;
                foreach($protax as $tax)
                {
                    $taxs += ($subtotal * $tax['tax']) / 100;
                }
            }
            else
            {
                $taxs = 0;
                $taxs += ($subtotal * 0) / 100;

            }
            $cart['products'][$key]["subtotal"] = $subtotal + $taxs;

            session()->put($slug, $cart);

            return response()->json(
                [
                    'code' => 200,
                    'status' => 'Success',
                    'success' => $cart['products'][$key]["product_name"] . __('added to cart successfully!'),
                    'product' => $cart['products'],
                    'carttotal' => $cart['products'],
                ]
            );

        }
    }

    public function delete_cart_item($slug, $id, $variant_id = 0)
    {
        $cart = session()->get($slug);

        foreach($cart['products'] as $key => $product)
        {
            if(($variant_id > 0 && $cart['products'][$key]['variant_id'] == $variant_id))
            {
                unset($cart['products'][$key]);
            }
            else if($cart['products'][$key]['product_id'] == $id && $variant_id == 0)
            {
                unset($cart['products'][$key]);
            }

        }

        $cart['products'] = array_values($cart['products']);

        session()->put($slug, $cart);

        return redirect()->back()->with('success', __('Item successfully Deleted.'));
    }

    public function complete($slug, $order_id)
    {
        $order = Order::where('id', Crypt::decrypt($order_id))->first();
        $store = Store::where('slug', $slug)->first();

        return view('storefront.complete', compact('slug', 'store', 'order_id', 'order'));
    }

    public function userorder($slug, $order_id)
    {
        $id    = Crypt::decrypt($order_id);
        $store = Store::where('slug', $slug)->first();
        $order = Order::where('id', $id)->first();

        if(!empty($order->coupon_json))
        {
            $coupon = json_decode($order->coupon_json);
        }

        if(!empty($order->discount_price))
        {
            $discount_price = $order->discount_price;
        }
        else
        {
            $discount_price = '';
        }

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

        $user_details   = UserDetail::where('id', $order->user_address_id)->first();
        $order_products = json_decode($order->product);


        $sub_total = 0;

        if(!empty($order_products))
        {
            $grand_total    = 0;
            $discount_value = 0;
            $final_taxs     = 0;

            foreach($order_products->products as $product)
            {
                if($product->variant_id != 0)
                {
                    $total_taxs = 0;
                    if(!empty($product->tax))
                    {
                        foreach($product->tax as $tax)
                        {
                            $sub_tax    = ($product->variant_price * $product->quantity * $tax->tax) / 100;
                            $total_taxs += $sub_tax;
                            $final_taxs += $sub_tax;
                        }
                    }
                    else
                    {
                        $total_taxs = 0;
                    }

                    $totalprice = $product->variant_price * $product->quantity + $total_taxs;
                    $subtotal1  = $product->variant_price * $product->quantity;

                    $sub_total   += $subtotal1;
                    $grand_total += $totalprice;
                }
                else
                {
                    if(!empty($product->tax))
                    {
                        $total_taxs = 0;
                        foreach($product->tax as $tax)
                        {
                            $sub_tax    = ($product->price * $product->quantity * $tax->tax) / 100;
                            $total_taxs += $sub_tax;
                            $final_taxs += $sub_tax;

                        }
                    }
                    else
                    {
                        $total_taxs = 0;
                    }

                    $totalprice  = $product->price * $product->quantity + $total_taxs;
                    $subtotal1   = $product->price * $product->quantity;
                    $sub_total   += $subtotal1;
                    $grand_total += $totalprice;


                }

            }
        }

        if(!empty($coupon))
        {
            if($coupon->enable_flat == 'on')
            {
                $discount_value = $coupon->flat_discount;
            }
            else
            {
                $discount_value = ($grand_total / 100) * $coupon->discount;
            }
        }

        return view('storefront.userorder', compact('slug', 'store', 'order', 'grand_total', 'order_products', 'sub_total', 'total_taxs', 'user_details', 'shipping_data', 'location_data', 'discount_price', 'discount_value', 'final_taxs'));
    }

    public function whatsapp(Request $request, $slug)
    {
        $store = Store::where('slug', $slug)->first();
        if(empty($store))
        {
            return response()->json(
                [
                    'status' => 'error',
                    'success' => __('Store not available.'),
                ]
            );
        }
        $validator = \Validator::make(
            $request->all(), [
                               'name' => 'required|max:120',
                               'phone' => 'required',
                               'billing_address' => 'required',
                           ]
        );

        if($validator->fails())
        {
            return response()->json(
                [
                    'status' => 'error',
                    'success' => __('All field is required.'),
                ]
            );
        }

        $userdetail                     = new UserDetail();
        $userdetail['store_id']         = $store->id;
        $userdetail['name']             = $request->name;
        $userdetail['phone']            = $request->phone;
        $userdetail['billing_address']  = $request->billing_address;
        $userdetail['shipping_address'] = $request->shipping_address;
        $userdetail['special_instruct'] = $request->special_instruct;
        $userdetail->save();
        $userdetail->id;

        $customer = [
            "id" => $userdetail->id,
            "name" => $request->name,
            "phone" => $request->phone,
            "billing_address" => $request->billing_address,
            "shipping_address" => $request->shipping_address,
            "special_instruct" => $request->special_instruct,
        ];

        $products     = $request['product'];
        $order_id     = $request['order_id'];
        $cart         = session()->get($slug);
        $cust_details = $customer;
        if(empty($cart))
        {
            return response()->json(
                [
                    'status' => 'error',
                    'success' => __('Please add to product into cart.'),
                ]
            );
        }
        if(!empty($request->coupon_id))
        {
            $coupon = ProductCoupon::where('id', $request->coupon_id)->first();
        }
        else
        {
            $coupon = '';
        }

        $product_name = [];
        $product_id   = [];
        $tax_name     = [];
        $totalprice   = 0;
        foreach($products['products'] as $key => $product)
        {
            if($product['variant_id'] == 0)
            {
                $new_qty                = $product['originalquantity'] - $product['quantity'];
                $product_edit           = Product::find($product['product_id']);
                $product_edit->quantity = $new_qty;
                $product_edit->save();

                $tax_price = 0;
                if(!empty($product['tax']))
                {
                    foreach($product['tax'] as $key => $taxs)
                    {
                        $tax_price += $product['price'] * $product['quantity'] * $taxs['tax'] / 100;

                    }
                }
                $totalprice     += $product['price'] * $product['quantity'] + $tax_price;
                $product_name[] = $product['product_name'];
                $product_id[]   = $product['id'];
            }
            elseif($product['variant_id'] != 0)
            {
                $new_qty                   = $product['originalvariantquantity'] - $product['quantity'];
                $product_variant           = ProductVariantOption::find($product['variant_id']);
                $product_variant->quantity = $new_qty;
                $product_variant->save();

                $tax_price = 0;
                if(!empty($product['tax']))
                {
                    foreach($product['tax'] as $key => $taxs)
                    {
                        $tax_price += $product['variant_price'] * $product['quantity'] * $taxs['tax'] / 100;

                    }
                }
                $totalprice     += $product['variant_price'] * $product['quantity'] + $tax_price;
                $product_name[] = $product['product_name'] . ' - ' . $product['variant_name'];
                $product_id[]   = $product['id'];
            }
        }

        if(!empty($request->shipping_id))
        {
            $shipping = Shipping::find($request->shipping_id);
            if(!empty($shipping))
            {
                $totalprice     = $totalprice + $shipping->price;
                $shipping_name  = $shipping->name;
                $shipping_price = $shipping->price;
                $shipping_data  = json_encode(
                    [
                        'shipping_name' => $shipping_name,
                        'shipping_price' => $shipping_price,
                        'location_id' => $shipping->location_id,
                    ]
                );
            }
        }
        else
        {
            $shipping_data = '';
        }

        if($product)
        {
            $order                  = new Order();
            $order->order_id        = $order_id;
            $order->name            = $cust_details['name'];
            $order->card_number     = '';
            $order->card_exp_month  = '';
            $order->card_exp_year   = '';
            $order->status          = 'pending';
            $order->phone           = $request->phone;
            $order->user_address_id = $cust_details['id'];
            $order->shipping_data   = !empty($shipping_data) ? $shipping_data : '';
            $order->product_id      = implode(',', $product_id);
            $order->price           = $totalprice;
            $order->coupon          = $request->coupon_id;
            $order->coupon_json     = json_encode($coupon);
            $order->discount_price  = $request->dicount_price;
            $order->coupon          = $request->coupon_id;
            $order->product         = json_encode($products);
            $order->price_currency  = $store->currency_code;
            $order->txn_id          = '';
            $order->payment_type    = __('Whatsapp');
            $order->payment_status  = 'approved';
            $order->receipt         = '';
            $order->user_id         = $store['id'];
            $order->save();

            return response()->json(
                [
                    'status' => 'success',
                    'success' => __('Your Order Successfully Added'),
                    'order_id' => Crypt::encrypt($order->id),
                ]
            );
        }
        else
        {
            return redirect()->back()->with('error', __('failed'));
        }
    }

    public function grid()
    {
        if(\Auth::user()->type == 'super admin')
        {
            $users  = User::where('created_by', '=', \Auth::user()->creatorId())->where('type', '=', 'owner')->get();
            $stores = Store::get();

            return view('user.grid', compact('users', 'stores'));
        }
        else
        {
            return redirect()->back()->with('error', __('permission Denied'));
        }

    }

    public function upgradePlan($user_id)
    {
        if(\Auth::user()->type == 'super admin')
        {
            $user = User::find($user_id);

            $plans = Plan::get();

            return view('user.plan', compact('user', 'plans'));
        }
    }

    public function activePlan($user_id, $plan_id)
    {
        if(\Auth::user()->type == 'super admin')
        {

            $user       = User::find($user_id);
            $assignPlan = $user->assignPlan($plan_id);
            $plan       = Plan::find($plan_id);
            if($assignPlan['is_success'] == true && !empty($plan))
            {
                $orderID = strtoupper(str_replace('.', '', uniqid('', true)));
                PlanOrder::create(
                    [
                        'order_id' => $orderID,
                        'name' => null,
                        'card_number' => null,
                        'card_exp_month' => null,
                        'card_exp_year' => null,
                        'plan_name' => $plan->name,
                        'plan_id' => $plan->id,
                        'price' => $plan->price,
                        'price_currency' => Utility::getValByName('site_currency'),
                        'txn_id' => '',
                        'payment_status' => 'succeeded',
                        'receipt' => null,
                        'payment_type' => __('Manually'),
                        'user_id' => $user->id,
                    ]
                );

                return redirect()->back()->with('success', __('Plan successfully upgraded.'));
            }
            else
            {
                return redirect()->back()->with('error', __('Plan fail to upgrade.'));
            }
        }

    }

    public function storedit($id)
    {
        if(\Auth::user()->type == 'super admin')
        {
            $user       = User::find($id);
            $user_store = UserStore::where('user_id', $id)->first();
            $store      = Store::where('id', $user_store->store_id)->first();

            return view('admin_store.edit', compact('store', 'user'));
        }
        else
        {
            return redirect()->back()->with('error', __('permission Denied'));
        }
    }

    public function storeupdate(Request $request, $id)
    {
        $user      = User::find($id);
        $validator = \Validator::make(
            $request->all(), [
                               'username' => 'required|max:120',
                               'name' => 'required|max:120',
                           ]
        );
        if($validator->fails())
        {
            $messages = $validator->getMessageBag();

            return redirect()->back()->with('error', $messages->first());
        }

        $user['username']   = $request->username;
        $user['name']       = $request->name;
        $user['title']      = $request->title;
        $user['phone']      = $request->phone;
        $user['gender']     = $request->gender;
        $user['is_active']  = ($request->is_active == 'on') ? 1 : 0;
        $user['user_roles'] = $request->user_roles;
        $user->update();

        Stream::create(
            [
                'user_id' => \Auth::user()->id,
                'created_by' => \Auth::user()->creatorId(),
                'log_type' => 'updated',
                'remark' => json_encode(
                    [
                        'owner_name' => \Auth::user()->username,
                        'title' => 'user',
                        'stream_comment' => '',
                        'user_name' => $request->name,
                    ]
                ),
            ]
        );

        return redirect()->back()->with('success', __('User Successfully Updated'));

    }

    public function storedestroy($id)
    {
        if(\Auth::user()->type == 'super admin')
        {
            $user      = User::find($id);
            $userstore = UserStore::where('user_id', $user->id)->first();
            $store     = Store::where('id', $userstore->store_id)->first();

            $user->delete();
            $userstore->delete();
            $store->delete();

            return redirect()->back()->with('success', __('User Store Successfully Deleted'));
        }
        else
        {
            return redirect()->back()->with('error', __('permission Denied'));
        }
    }

    public function changeCurrantStore($storeID)
    {
        $objStore = Store::find($storeID);
        if($objStore->is_active)
        {
            $objUser                = Auth::user();
            $objUser->current_store = $storeID;
            $objUser->update();

            return redirect()->route('dashboard')->with('success', __('Store Change Successfully!'));
        }
        else
        {
            return redirect()->back()->with('error', __('Store is locked'));
        }
    }

    public function storeVariant($slug, $id)
    {
        $store                 = Store::where('slug', $slug)->first();
        $products              = Product::where('id', $id)->first();
        $variant_name          = json_decode($products->variants_json);
        $product_variant_names = $variant_name;

        return view('storefront.store_variant', compact('store', 'products', 'product_variant_names'));
    }

    public function changeTheme(Request $request, $slug)
    {
        $validator = \Validator::make(
            $request->all(), [
                               'theme_color' => 'required',
                           ]
        );
        if($validator->fails())
        {
            $messages = $validator->getMessageBag();

            return redirect()->back()->with('error', $messages->first());
        }
        $store = Store::find($slug);

        $store['store_theme'] = $request->theme_color;
        $store->save();

        return redirect()->back()->with('success', __('Theme Successfully Updated.'));

    }

    public function filterproductview(Request $request)
    {
        $store     = Store::where('slug', $request->slug)->first();
        $userstore = UserStore::where('store_id', $store->id)->first();

        $categories = ProductCategorie::where('store_id', $userstore->store_id)->get()->pluck('name', 'id');
        $categories->prepend(__('All'), 0);

        $products = [];
        foreach($categories as $id => $category)
        {
            $product = Product::where('store_id', $store->id);

            if($id != 0)
            {
                $product->whereRaw('FIND_IN_SET("' . $id . '", product_categorie)');
            }
            if($request->types == 'hightolow')
            {
                $product->orderBy('price', 'desc');
            }
            else
            {
                $product->orderBy('price', 'asc');
            }
            $product = $product->get();

            $arrPriceSort = [];
            foreach($product as $v)
            {
                $variant_product = ProductVariantOption::where('product_id', $v->id)->first();

                if(!empty($variant_product))
                {
                    $arrPriceSort[$variant_product->price . '-' . $variant_product->id] = $v;
                }
                else
                {
                    $arrPriceSort[$v->price . '-' . $v->id] = $v;
                }
            }

            if($request->types == 'hightolow')
            {
                krsort($arrPriceSort, SORT_NUMERIC);
            }
            else
            {
                ksort($arrPriceSort, SORT_NUMERIC);
            }

            $products[$category] = $arrPriceSort;
        }

        $returnHTML = view('storefront.' . $request->view, compact('products', 'store'))->render();

        return response()->json(
            [
                'success' => true,
                'html' => $returnHTML,
            ]
        );
    }

    public function productView($slug, $id)
    {
        $store_setting = Store::where('slug', $slug)->first();
        $cart          = session()->get($slug);

        $store = Store::where('slug', $slug)->first();
        if(empty($store))
        {
            return redirect()->back()->with('error', __('Store not available'));
        }
        $products = Product::where('id', $id)->first();

        $products_image = Product_images::where('product_id', $products->id)->get();

        $variant_item = 0;
        $total_item   = 0;
        if(isset($cart['products']))
        {
            foreach($cart['products'] as $item)
            {
                if(isset($cart) && !empty($cart['products']))
                {
                    if(isset($item['variants']))
                    {
                        $variant_item += count($item['variants']);
                    }
                    else
                    {
                        $product_item = count($cart['products']);
                        $total_item   = $variant_item + $product_item;
                    }
                }
                else
                {
                    $total_item = 0;
                }
            }
        }

        $variant_name          = json_decode($products->variants_json);
        $product_variant_names = $variant_name;

        return view('storefront.view', compact('products', 'store', 'products_image', 'total_item', 'store_setting', 'product_variant_names'));
    }

    public function removeSession($slug)
    {
        session()->forget($slug);
    }

    public function customMassage(Request $request, $slug)
    {
        $validator = \Validator::make(
            $request->all(), [
                               'content' => 'required',
                           ]
        );

        if($validator->fails())
        {
            $messages = $validator->getMessageBag();

            return redirect()->back()->with('error', $messages->first());
        }

        $store                = Store::where('slug', $slug)->first();
        $store->content       = $request['content'];
        $store->item_variable = $request['item_variable'];
        $store->update();

        return redirect()->back()->with('success', __('Massage successfully updated.'));
    }

    public function getWhatsappUrl(Request $request, $slug)
    {
        $store = Store::where('slug', $slug)->first();
        $cart  = session()->get($slug);

        if(!empty($cart))
        {
            $products = $cart['products'];
        }
        else
        {
            return response()->json(
                [
                    'status' => 'error',
                    'msg' => __('Please add to product into cart'),
                ]
            );
        }

        if($store->enable_shipping == 'on')
        {
            if(!empty($request->shipping_id))
            {
                $shipping_details = Shipping::where('store_id', $store->id)->where('id', $request->shipping_id)->first();
                $shipping_price   = $shipping_details->price;
            }
        }
        else
        {
            $shipping_price = 0;
        }
        // For Url
        $pro_qty  = [];
        $pro_name = [];
        $order_id = '#' . time();

        $lists     = [];
        $total_tax = 0;
        foreach($products as $item)
        {
            $pro_data = Product::where('id', $item['id'])->first();

            if($item['variant_id'] == 0)
            {
                $pro_qty[] = $item['quantity'] . ' x ' . $item['product_name'];
                $total_tax = 0;
                foreach($item['tax'] as $tax)
                {
                    $sub_tax   = ($item['price'] * $item['quantity'] * $tax['tax']) / 100;
                    $total_tax += $sub_tax;
                }
                $lists[] = array(
                    'sku' => $pro_data->SKU,
                    'quantity' => $item['quantity'],
                    'product_name' => $item['product_name'],
                    'item_tax' => $total_tax,
                    'item_total' => $item['price'] * $item['quantity'],
                );
            }
            elseif($item['variant_id'] != 0)
            {
                $pro_data  = Product::where('id', $item['id'])->first();
                $pro_qty[] = $item['quantity'] . ' x ' . $item['product_name'] . ' - ' . $item['variant_name'];
                foreach($item['tax'] as $tax)
                {
                    $sub_tax   = ($item['variant_price'] * $item['quantity'] * $tax['tax']) / 100;
                    $total_tax += $sub_tax;
                }

                $lists[] = [
                    'sku' => $pro_data->SKU,
                    'quantity' => $item['quantity'],
                    'product_name' => $item['product_name'],
                    'variant_name' => $item['variant_name'],
                    'item_tax' => $total_tax,
                    'item_total' => $item['variant_price'] * $item['quantity'],
                ];
            }
        }

        $item_variable = '';
        $qty_total     = 0;
        $sub_total     = 0;
        $total_tax     = 0;

        foreach($lists as $l)
        {
            $arrList = [
                'sku' => $l['sku'],
                'quantity' => $l['quantity'],
                'product_name' => $l['product_name'],
                'item_tax' => $l['item_tax'],
                'item_total' => Utility::priceFormat($l['item_total']),
            ];

            if(isset($l['variant_name']) && !empty($l['variant_name']))
            {
                $arrList['variant_name'] = $l['variant_name'];
            }

            $resp = Utility::replaceVariable($store->item_variable, $arrList);
            $resp = str_replace('-  ', '', $resp);;
            $item_variable .= $resp . PHP_EOL;

            $qty_total = $qty_total + $l['quantity'];
            $sub_total += $l['item_total'] * $l['quantity'];
            $total_tax += $l['item_tax'];
        }

        $total_price = Utility::priceFormat(floatval($sub_total) + (int)$request->shipping_price + floatval($total_tax));

        $arr = [
            'store_name' => $store->name,
            'order_no' => $request->order_id,
            'customer_name' => $request->name,
            'phone' => $request->phone,
            'billing_address' => $request->billing_address,
            'shipping_address' => $request->shipping_address,
            'special_instruct' => $request->special_instruct,
            'item_variable' => $item_variable,
            'qty_total' => $qty_total,
            'sub_total' => Utility::priceFormat($sub_total),
            'shipping_amount' => Utility::priceFormat($shipping_price),
            'item_tax' => Utility::priceFormat($total_tax),
            'item_total' => $request->total_price,
        ];

        if(isset($request->coupon) && !empty($request->coupon))
        {
            $arr['discount_amount'] = $request->dicount_price;
        }
        if(isset($request->finalprice) && !empty($request->finalprice))
        {
            $arr['final_total'] = Utility::priceFormat($request->total_price);
        }

        $resp = Utility::replaceVariable($store->content, $arr);

        $url = 'https://api.whatsapp.com/send?phone=' . $store->whatsapp_number . '&text=' . urlencode($resp);

        return response()->json(
            [
                'status' => 'success',
                'order_id' => Crypt::encrypt($order_id),
                'url' => $url,
            ]
        );
    }
}
