<?php

namespace Tests\Unit\TypeGenerator\Helpers;

use Mockery;
use PHPUnit\Framework\TestCase;

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
