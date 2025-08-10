<?php

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;

Route::name('api.openapi.')
    ->prefix('/api')
    ->group(static function () {
        Route::get('openapi', static function () {
            /** @var view-string */
            $route = 'type-generator::swagger';

            return view($route, ['json_url' => route('api.openapi.json')]);
        })->name('page');

        Route::get('openapi.json', static function () {
            return new JsonResponse(
                data: File::get(resource_path('api/openapi.json')),
                json: true,
            );
        })->name('json');
    });
