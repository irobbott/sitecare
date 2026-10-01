<?php

use App\Http\Controllers\Api\SiteCareController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::post('/auth/login',[SiteCareController::class,'login'])->middleware('throttle:5,1');
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/auth/me',[SiteCareController::class,'me']);
        Route::post('/auth/logout',[SiteCareController::class,'logout']);
        Route::get('/dashboard',[SiteCareController::class,'dashboard']);
        Route::get('/websites',[SiteCareController::class,'websites']);
        Route::post('/websites',[SiteCareController::class,'storeWebsite']);
        Route::patch('/websites/{website}',[SiteCareController::class,'reviewWebsite']);
        Route::get('/tickets',[SiteCareController::class,'tickets']);
        Route::post('/tickets',[SiteCareController::class,'storeTicket']);
        Route::patch('/tickets/{ticket}',[SiteCareController::class,'updateTicket']);
        Route::get('/tickets/{ticket}/comments',[SiteCareController::class,'comments']);
        Route::post('/tickets/{ticket}/comments',[SiteCareController::class,'storeComment']);
    });
});

Route::get('/health',fn()=>response()->json(['status'=>'ok','service'=>'sitecare-api']));
