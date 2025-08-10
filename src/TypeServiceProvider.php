<?php

namespace MartinPham\TypeGenerator;

use Illuminate\Support\ServiceProvider;
use MartinPham\TypeGenerator\Commands\GenerateTypeCommand;

class TypeServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/config/type-generator.php' => config_path('type-generator.php'),
            __DIR__ . '/resources/views' => resource_path('views/vendor/type-generator-openapi'),
            __DIR__ . '/routes/routes.php' => base_path('routes/type-generator-openapi.php'),
        ], 'type-generator');

        if ($this->app->runningInConsole()) {
            $this->commands([
                GenerateTypeCommand::class,
            ]);
        }

        $publishedRoutesPath = base_path('routes/type-generator-openapi.php');
        if (file_exists($publishedRoutesPath)) {
            $this->loadRoutesFrom($publishedRoutesPath);
        } else {
            $this->loadRoutesFrom(__DIR__ . '/routes/routes.php');
        }

        $publishedViewsPath = base_path('views/vendor/type-generator-openapi');
        if (file_exists($publishedViewsPath)) {
            $this->loadViewsFrom($publishedViewsPath);
        } else {
            $this->loadViewsFrom(__DIR__ . '/resources/views', 'type-generator-openapi');
        }


    }

    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/config/type-generator.php',
            'type-generator'
        );
    }
}
