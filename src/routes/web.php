<?php

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

// Route::get('/surge', 'SurgePriceTestController@surgeAreas');
// Route::get('/train', 'SurgePriceTestController@trainData');
// Route::get('/provider', 'SurgePriceTestController@provider');

Route::group(
    array('namespace' => 'Codificar\SurgePrice\Http\Controllers', 'prefix' => '/surgeprice'),
    function () {
        Route::get('/', 'SurgePriceController@index');
        Route::post('/', 'SurgePriceController@saveSettings')->name('surgeprice.save_settings');
        Route::get('/region/', 'SurgePriceController@createRegion')->name('surgeprice.create_region');
        Route::post('/region/', 'SurgePriceController@manageRegion')->name('surgeprice.manage_region');
    });
