<?php

namespace Tests\Unit\TypeGenerator\Helpers;

use MartinPham\TypeGenerator\Helpers\ClassHelper;
use MartinPham\TypeGenerator\Writers\OpenAPI\OpenAPI;
use MartinPham\TypeGenerator\Definitions\Schemas\Schema;
use MartinPham\TypeGenerator\Definitions\Schemas\StringSchema;
use MartinPham\TypeGenerator\Definitions\Schemas\ObjectSchema;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use DateTime;
use MartinPham\TypeGenerator\Definitions\Spec;

class ClassHelperTest extends TestCase
{


    /**
     * Test parseClass with a DateTime class
     */
    public function test_parse_class_with_datetime()
    {
        $spec = $this->createMock(OpenAPI::class);
        $schema = ClassHelper::parseClass(DateTime::class, $spec, false);

        $this->assertInstanceOf(StringSchema::class, $schema);
        $this->assertEquals('date-time', $schema->format);
    }

    /**
     * Test parseClass with a simple class
     */
    public function test_parse_class_with_simple_class()
    {
        // Create a simple class with public properties for testing
        $code = '<?php
        class SimpleTestClass {
            public string $stringProp;
            public int $intProp;
            public float $floatProp;
            public bool $boolProp;
            public ?string $nullableStringProp;
        }';

        $tempFile = tempnam(sys_get_temp_dir(), 'test_');
        file_put_contents($tempFile, $code);

        // Include the file and create a mock OpenAPI spec
        include_once $tempFile;
        $spec = $this->createMock(OpenAPI::class);

        $schema = ClassHelper::parseClass('SimpleTestClass', $spec, false);

        $this->assertInstanceOf(ObjectSchema::class, $schema);

        $properties = $schema->properties;
        $this->assertCount(5, $properties);

        $this->assertArrayHasKey('stringProp', $properties);
        $this->assertInstanceOf(Schema::class, $properties['stringProp']);
        $this->assertEquals('string', $properties['stringProp']->type);

        $this->assertArrayHasKey('intProp', $properties);
        $this->assertInstanceOf(Schema::class, $properties['intProp']);
        $this->assertEquals('integer', $properties['intProp']->type);

        $this->assertArrayHasKey('floatProp', $properties);
        $this->assertInstanceOf(Schema::class, $properties['floatProp']);
        $this->assertEquals('number', $properties['floatProp']->type);

        $this->assertArrayHasKey('boolProp', $properties);
        $this->assertInstanceOf(Schema::class, $properties['boolProp']);
        $this->assertEquals('boolean', $properties['boolProp']->type);

        $this->assertArrayHasKey('nullableStringProp', $properties);
        $this->assertInstanceOf(Schema::class, $properties['nullableStringProp']);
        $this->assertEquals('string', $properties['nullableStringProp']->type);
        $this->assertTrue($properties['nullableStringProp']->nullable);

        // Clean up
        unlink($tempFile);
    }

    /**
     * Test parseClass with a class that has array properties with docblock type hints
     */
    public function test_parse_class_with_array_properties()
    {
        // Create a class with array properties and docblock type hints
        $code = '<?php
        class ArrayPropsTestClass {
            /**
             * @var string[]
             */
            public array $stringArray;

            /**
             * @var int[]
             */
            public array $intArray;
        }';

        $tempFile = tempnam(sys_get_temp_dir(), 'test_');
        file_put_contents($tempFile, $code);

        // Include the file and create a mock spec
        include_once $tempFile;
        $spec = $this->createMock(Spec::class);


        $schema = ClassHelper::parseClass('ArrayPropsTestClass', $spec, false);

        $this->assertInstanceOf(ObjectSchema::class, $schema);

        // Clean up
        unlink($tempFile);
    }
}
