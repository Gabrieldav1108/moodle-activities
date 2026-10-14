<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Owner account
    |--------------------------------------------------------------------------
    |
    | Used by the UserSeeder to create the single account of this dashboard.
    | Leave the password empty to keep an existing user's password.
    |
    */

    'name' => env('SEED_USER_NAME') ?: 'Dashboard Owner',

    'email' => env('SEED_USER_EMAIL') ?: 'owner@example.com',

    'password' => (string) env('SEED_USER_PASSWORD', ''),

];
