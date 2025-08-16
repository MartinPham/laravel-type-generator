<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Ignored route methods
    |--------------------------------------------------------------------------
    |
    | The methods that should be ignored when generating the OpenAPI file.
    |
    */
    'ignored_methods' => [
        'head',
        'options',
    ],

    /*
    |--------------------------------------------------------------------------
    | Routes prefixes
    |--------------------------------------------------------------------------
    |
    | The routes that should be included when generating the OpenAPI file.
    |
    */
    'route_prefixes' => [
        'uri:api' => [
            'output' => resource_path('api/openapi.json'),
            'class' => 'MartinPham\TypeGenerator\Writers\OpenAPI\OpenAPI',
            'options' => [
                'openapi' => '3.0.2',
                'title' => 'OpenAPI',
                'version' => '1.0.0'
            ]
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Excluded routes
    |--------------------------------------------------------------------------
    |
    | The routes that should be excluded when generating the OpenAPI file.
    | Uses a str_starts_with comparison with the Route::getName method to determine.
    |
    */
    'ignored_route_names' => [
        'api.openapi.',
        'api.not_found',
    ],
    'ignored_route_returns' => [
        'Illuminate\Http\RedirectResponse',
    ],
];
