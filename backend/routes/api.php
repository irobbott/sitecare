<?php

use App\Http\Controllers\Api\SiteCareController;
use App\Models\Website;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::post('/auth/login',[SiteCareController::class,'login'])->middleware('throttle:5,1');
    Route::post('/invitations/accept',[SiteCareController::class,'acceptInvitation'])->middleware('throttle:10,1');
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/auth/me',[SiteCareController::class,'me']);
        Route::post('/auth/logout',[SiteCareController::class,'logout']);
        Route::get('/invitations',[SiteCareController::class,'invitations']);
        Route::post('/invitations',[SiteCareController::class,'storeInvitation'])->middleware('throttle:10,1');
        Route::delete('/invitations/{invitation}',[SiteCareController::class,'revokeInvitation']);
        Route::get('/dashboard',[SiteCareController::class,'dashboard']);
        Route::get('/websites',[SiteCareController::class,'websites']);
        Route::post('/websites',[SiteCareController::class,'storeWebsite']);
        Route::patch('/websites/{website}',[SiteCareController::class,'reviewWebsite']);
        Route::get('/websites/{website}/backups',[SiteCareController::class,'backups']);
        Route::post('/websites/{website}/backups',[SiteCareController::class,'storeBackup']);
        Route::post('/websites/{website}/backup-webhook-secret',[SiteCareController::class,'rotateBackupWebhookSecret']);
        Route::get('/websites/{website}/maintenance',[SiteCareController::class,'maintenance']);
        Route::post('/websites/{website}/maintenance',[SiteCareController::class,'storeMaintenance']);
        Route::get('/tickets',[SiteCareController::class,'tickets']);
        Route::post('/tickets',[SiteCareController::class,'storeTicket']);
        Route::patch('/tickets/{ticket}',[SiteCareController::class,'updateTicket']);
        Route::get('/tickets/{ticket}/comments',[SiteCareController::class,'comments']);
        Route::post('/tickets/{ticket}/comments',[SiteCareController::class,'storeComment']);
        Route::get('/tickets/{ticket}/history',[SiteCareController::class,'ticketHistory']);
        Route::get('/incidents',[SiteCareController::class,'incidents']);
        Route::patch('/incidents/{incident}',[SiteCareController::class,'acknowledgeIncident']);
    });
    Route::post('/webhooks/websites/{website}/backups',[SiteCareController::class,'backupWebhook'])->middleware('throttle:30,1');
});

Route::get('/health',fn()=>response()->json(['status'=>'ok','service'=>'sitecare-api']));
