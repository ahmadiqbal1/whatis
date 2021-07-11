<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Plan;
use App\Providers\RouteServiceProvider;
use App\Store;
use App\User;
use App\UserStore;
use App\Utility;
use Illuminate\Foundation\Auth\RegistersUsers;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class RegisterController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Register Controller
    |--------------------------------------------------------------------------
    |
    | This controller handles the registration of new users as well as their
    | validation and creation. By default this controller uses a trait to
    | provide this functionality without requiring any additional code.
    |
    */

    use RegistersUsers;

    /**
     * Where to redirect users after registration.
     *
     * @var string
     */
    protected $redirectTo = RouteServiceProvider::HOME;

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('guest');
    }

    /**
     * Get a validator for an incoming registration request.
     *
     * @param array $data
     *
     * @return \Illuminate\Contracts\Validation\Validator
     */
    protected function validator(array $data)
    {
        return Validator::make(
            $data, [
            'name' => [
                'required',
                'string',
                'max:255',
            ],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                'unique:users',
            ],
            'password' => [
                'required',
                'string',
                'min:8',
                'confirmed',
            ],
        ]
        );
    }

    /**
     * Create a new user instance after a valid registration.
     *
     * @param array $data
     *
     * @return \App\User
     */
    protected function create(array $data)
    {
        $settings = Utility::settings();

        $objUser = User::create(
            [
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
                'type' => 'Owner',
                'lang' => !empty($settings['default_language']) ? $settings['default_language'] : 'en',
                'avatar' => 'avatar.png',
                'plan' => Plan::first()->id,
                'created_by' => 1,
            ]
        );

        $objStore = Store::create(
            [
                'created_by' => $objUser->id,
                'name' => $data['store_name'],
                'email' => $data['email'],
                'logo' => !empty($settings['logo']) ? $settings['logo'] : 'logo.png',
                'invoice_logo' => !empty($settings['logo']) ? $settings['logo'] : 'invoice_logo.png',
                'lang' => !empty($settings['default_language']) ? $settings['default_language'] : 'en',
                'currency' => !empty($settings['currency_symbol']) ? $settings['currency_symbol'] : '$',
                'currency_code' => !empty($settings->currency) ? $settings->currency : 'USD',
                'paypal_mode' => 'sandbox',
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

        return $objUser;
    }

    public function showRegistrationForm($lang = 'en')
    {
        if($lang == '')
        {
            $lang = \App\Utility::getValByName('default_language');
        }
        \App::setLocale($lang);

        return view('auth.register', compact('lang'));
    }
}
