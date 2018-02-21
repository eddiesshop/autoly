<?php

/*
|--------------------------------------------------------------------------
| Application Routes
|--------------------------------------------------------------------------
|
| Here is where you can register all of the routes for an application.
| It's a breeze. Simply tell Laravel the URIs it should respond to
| and give it the controller to call when that URI is requested.
|
*/

Route::get('/', function () {
    return view('welcome');
});

Route::group([
    'prefix' => 'slack',
    'middleware' => ['api'],
], function(){
    Route::post('/message-callback', 'MessageCallbackController@handle');
});

Route::group([
    'prefix' => 'github',
    'middleware' => ['api'],
], function(){
    Route::post('/webhook', 'GitHubWebhooksController@handle');
});