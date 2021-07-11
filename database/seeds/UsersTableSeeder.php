<?php

use App\User;
use App\Store;
use App\UserStore;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class UsersTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $superAdmin              = User::create(
            [
                'name' => 'Super Admin',
                'email' => 'superadmin@example.com',
                'password' => Hash::make('1234'),
                'type' => 'super admin',
                'lang' => 'en',
                'created_by' => 0,
            ]
        );
        $_ENV['CURRENCY_SYMBOL'] = '$';
        $_ENV['CURRENCY']        = 'USD ';

        $admin = User::create(
            [
                'name' => 'Owner',
                'email' => 'owner@example.com',
                'password' => Hash::make('1234'),
                'type' => 'Owner',
                'created_by' => $superAdmin->id,
            ]
        );

        $objStore             = Store::create(
            [
                'name' => 'My WhatsStore',
                'email' => 'owner@example.com',
                'created_by' => $admin->id,
                'tagline' => 'WhatsStore',
                'enable_storelink' => 'on',
                'store_theme' => 'style-grey-body.css',
                'address' => 'india',
                'whatsapp' => '#',
                'facebook' => '#',
                'instagram' => '#',
                'twitter' => '#',
                'youtube' => '#',
                'footer_note' => '© 2020 WhatsStore. All rights reserved',
                'logo' => 'logo.png',
            ]
        );
        $admin->current_store = $objStore->id;
        $admin->save();

        UserStore::create(
            [
                'user_id' => $admin->id,
                'store_id' => $objStore->id,
                'permission' => 'Owner',
            ]
        );

        Utility::add_landing_page_data();

    }
}
