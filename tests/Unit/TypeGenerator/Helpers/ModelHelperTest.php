<?php

namespace Tests\Unit\TypeGenerator\Helpers;

use MartinPham\TypeGenerator\Helpers\ModelHelper;
use MartinPham\TypeGenerator\Writers\OpenAPI\OpenAPI;
use MartinPham\TypeGenerator\Definitions\Schemas\ObjectSchema;
use MartinPham\TypeGenerator\Definitions\Schemas\Schema as SchemaDefinition;
use MartinPham\TypeGenerator\Definitions\Schemas\StringSchema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema as FacadeSchema;
use PHPUnit\Framework\TestCase;
use Mockery;

class ModelHelperTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // This test requires a Laravel application context
        // In a real test, we would use the Laravel TestCase
        if (!function_exists('app') || !class_exists('Illuminate\Foundation\Application')) {
            $this->markTestSkipped('Laravel application is required for this test');
        }
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }


}
