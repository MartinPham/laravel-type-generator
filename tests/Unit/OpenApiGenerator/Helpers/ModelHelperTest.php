<?php

namespace Tests\Unit\TypeGenerator\Helpers;

use MartinPham\TypeGenerator\Helpers\ModelHelper;
use MartinPham\TypeGenerator\Definitions\OpenAPI;
use MartinPham\TypeGenerator\Definitions\ObjectSchema;
use MartinPham\TypeGenerator\Definitions\Schema as SchemaDefinition;
use MartinPham\TypeGenerator\Definitions\StringSchema;
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

    /**
     * Test parsing a model with various column types
     */
    public function test_parse_model()
    {
        // Create a mock for the OpenAPI spec
        $spec = Mockery::mock(OpenAPI::class);

        // Create a test model class
        $testModel = new class extends Model {
            protected $table = 'test_models';
            protected $hidden = ['password'];
            protected $casts = [
                'is_active' => 'boolean',
                'created_at' => 'datetime',
            ];
        };

        // Mock the Schema facade to return test columns
        FacadeSchema::shouldReceive('getConnection->getSchemaBuilder->getColumns')
            ->with('test_models')
            ->andReturn([
                [
                    'name' => 'id',
                    'type_name' => 'int',
                    'nullable' => false
                ],
                [
                    'name' => 'name',
                    'type_name' => 'varchar',
                    'nullable' => false
                ],
                [
                    'name' => 'email',
                    'type_name' => 'varchar',
                    'nullable' => false
                ],
                [
                    'name' => 'password', // This should be hidden
                    'type_name' => 'varchar',
                    'nullable' => false
                ],
                [
                    'name' => 'description',
                    'type_name' => 'text',
                    'nullable' => true
                ],
                [
                    'name' => 'is_active',
                    'type_name' => 'tinyint(1)',
                    'nullable' => false
                ],
                [
                    'name' => 'age',
                    'type_name' => 'int',
                    'nullable' => true
                ],
                [
                    'name' => 'created_at',
                    'type_name' => 'timestamp',
                    'nullable' => true
                ],
                [
                    'name' => 'updated_at',
                    'type_name' => 'timestamp',
                    'nullable' => true
                ],
            ]);

        // Call the method under test
        $schema = ModelHelper::parseModel(get_class($testModel), $spec, false);

        // Assertions
        $this->assertInstanceOf(ObjectSchema::class, $schema);


        $properties = $schema->properties;

        // Check that we have the expected properties
        $this->assertArrayHasKey('id', $properties);
        $this->assertArrayHasKey('name', $properties);
        $this->assertArrayHasKey('email', $properties);
        $this->assertArrayHasKey('description', $properties);
        $this->assertArrayHasKey('is_active', $properties);
        $this->assertArrayHasKey('age', $properties);
        $this->assertArrayHasKey('created_at', $properties);
        $this->assertArrayHasKey('updated_at', $properties);
        // Check that hidden fields are not included
        $this->assertArrayNotHasKey('password', $properties);

        // Check property types
        $this->assertInstanceOf(SchemaDefinition::class, $properties['id']);
        $this->assertEquals('integer', $properties['id']->type);

        $this->assertInstanceOf(SchemaDefinition::class, $properties['name']);
        $this->assertEquals('string', $properties['name']->type);

        $this->assertInstanceOf(SchemaDefinition::class, $properties['description']);
        $this->assertEquals('string', $properties['description']->type);

        $this->assertInstanceOf(SchemaDefinition::class, $properties['is_active']);
        $this->assertEquals('boolean', $properties['is_active']->type);

        $this->assertInstanceOf(SchemaDefinition::class, $properties['age']);
        $this->assertEquals('integer', $properties['age']->type);

        $this->assertInstanceOf(StringSchema::class, $properties['created_at']);
        $this->assertEquals('date-time', $properties['created_at']->format);
    }

}
