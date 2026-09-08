<?php

use App\Http\Controllers\Api\DeviceApiController;
use App\Http\Controllers\Api\NotificationApiController;
use Illuminate\Support\Facades\Route;

Route::post('/device/register', [DeviceApiController::class, 'register']);
Route::post('/device/heartbeat', [DeviceApiController::class, 'heartbeat']);
Route::get('/notifications/pending', [NotificationApiController::class, 'pending']);
Route::post('/notifications/{notification}/delivered', [NotificationApiController::class, 'delivered']);
Route::post('/notifications/{notification}/opened', [NotificationApiController::class, 'opened']);
Route::post('/notifications/{notification}/read-completed', [NotificationApiController::class, 'readCompleted']);
Route::post('/notifications/{notification}/quiz-started', [NotificationApiController::class, 'quizStarted']);
Route::post('/notifications/{notification}/quiz', [NotificationApiController::class, 'submitQuiz']);
Route::post('/notifications/{notification}/acknowledge', [NotificationApiController::class, 'acknowledge']);
