<?php

use Illuminate\Support\Facades\Route;

Route::get('{type}/p/{vendor}/{module}.json', [
    'middleware' => ['xss', 'https'],
    'uses' => 'BoratController@package'
]);

Route::get('{type}/packages.json', [
    'middleware' => ['xss', 'https'],
    'uses' => 'App\Http\Controllers\BoratController@packages'
]);

Route::get('{type}/p/{vendor}/{module}.json', [
    'middleware' => ['xss', 'https'],
    'uses' => 'App\Http\Controllers\BoratController@package'
]);

Route::get('{type}/packages.json', [
    'middleware' => ['xss', 'https'],
    'uses' => 'App\Http\Controllers\BoratController@packages'
]);
