<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->json(['service' => 'SiteCare API', 'api' => '/api/v1']);
});
