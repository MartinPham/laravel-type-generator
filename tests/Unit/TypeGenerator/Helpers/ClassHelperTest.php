<?php

namespace Tests\Unit\TypeGenerator\Helpers;

use MartinPham\TypeGenerator\Helpers\ClassHelper;
use MartinPham\TypeGenerator\Helpers\CodeHelper;
use MartinPham\TypeGenerator\Helpers\DocBlockHelper;
use MartinPham\TypeGenerator\Helpers\ModelHelper;
use MartinPham\TypeGenerator\Helpers\SchemaHelper;
use MartinPham\TypeGenerator\Writers\OpenAPI\OpenAPI;
use MartinPham\TypeGenerator\Definitions\Schemas\Schema;
use MartinPham\TypeGenerator\Definitions\Schemas\StringSchema;
use MartinPham\TypeGenerator\Definitions\Schemas\ObjectSchema;
use MartinPham\TypeGenerator\Definitions\Schemas\RefSchema;
use MartinPham\TypeGenerator\Definitions\Schemas\ArraySchema;
use MartinPham\TypeGenerator\Definitions\Items\ComponentSchemaItem;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionProperty;
use ReflectionUnionType;
use DateTime;
use MartinPham\TypeGenerator\Definitions\Spec;

class ClassHelperTest extends TestCase
{
    private $specMock;
    private $classReflectionMock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->specMock = $this->createMock(Spec::class);
        $this->classReflectionMock = $this->createMock(ReflectionClass::class);
    }


    /**
     * Test parseClass with a DateTime class
     */
    public function test_parse_class_with_datetime()
    {
        $schema = ClassHelper::parseClass(DateTime::class, false, false, $this->specMock);

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

        // Include the file
        include_once $tempFile;

        $schema = ClassHelper::parseClass('SimpleTestClass', false, false, $this->specMock);

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

        // Include the file
        include_once $tempFile;

        $schema = ClassHelper::parseClass('ArrayPropsTestClass', false, false, $this->specMock);

        $this->assertInstanceOf(ObjectSchema::class, $schema);

        // Clean up
        unlink($tempFile);
    }
    /**
     * Test getClassFullname with a class in the same namespace
     */
    public function test_get_class_fullname_same_namespace()
    {
        // Create a test class in the same namespace
        $code = '<?php
        namespace Tests\Unit\TypeGenerator\Helpers;

        class TestClassInSameNamespace {}';

        $tempFile = tempnam(sys_get_temp_dir(), 'test_');
        file_put_contents($tempFile, $code);

        // Include the file
        include_once $tempFile;

        // Create a reflection of the current class
        $reflection = new ReflectionClass($this);

        // Test getClassFullname
        $fullname = ClassHelper::getClassFullname('TestClassInSameNamespace', $reflection);

        $this->assertEquals('Tests\Unit\TypeGenerator\Helpers\TestClassInSameNamespace', $fullname);

        // Clean up
        unlink($tempFile);
    }

    /**
     * Test getClassFullname with an imported class
     */
    public function test_get_class_fullname_imported()
    {
        // Create a test class with imports
        $code = '<?php
        namespace Tests\Unit\TypeGenerator\Helpers;

        use DateTime as MyDateTime;

        class TestClassWithImports {}';

        $tempFile = tempnam(sys_get_temp_dir(), 'test_');
        file_put_contents($tempFile, $code);

        // Include the file
        include_once $tempFile;

        // Create a reflection of the test class
        $reflection = new ReflectionClass('Tests\Unit\TypeGenerator\Helpers\TestClassWithImports');

        // Test getClassFullname with an imported class
        $fullname = ClassHelper::getClassFullname('MyDateTime', $reflection);

        $this->assertEquals('DateTime', $fullname);

        // Clean up
        unlink($tempFile);
    }

    /**
     * Test parseClass with class docblock properties
     */
    public function test_parse_class_with_docblock_properties()
    {
        // Create a class with docblock properties
        $code = '<?php
        /**
         * @property string $docProp1 First docblock property
         * @property int $docProp2 Second docblock property
         */
        class DocblockPropsTestClass {
            // No actual properties, just docblock ones
        }';

        $tempFile = tempnam(sys_get_temp_dir(), 'test_');
        file_put_contents($tempFile, $code);

        // Include the file
        include_once $tempFile;

        $schema = ClassHelper::parseClass('DocblockPropsTestClass', false, false, $this->specMock);

        $this->assertInstanceOf(ObjectSchema::class, $schema);

        $properties = $schema->properties;
        $this->assertCount(2, $properties);

        $this->assertArrayHasKey('docProp1', $properties);
        $this->assertEquals('string', $properties['docProp1']->type);

        $this->assertArrayHasKey('docProp2', $properties);
        $this->assertEquals('integer', $properties['docProp2']->type);

        // Clean up
        unlink($tempFile);
    }

    /**
     * Test parseClass with union types
     */
    public function test_parse_class_with_union_types()
    {
        // Skip this test if PHP version doesn't support union types
        if (version_compare(PHP_VERSION, '8.0.0', '<')) {
            $this->markTestSkipped('Union types are only supported in PHP 8.0+');
            return;
        }

        // Create a class with union type properties
        $code = '<?php
        class UnionTypeTestClass {
            public string|int $unionProp;
            public string|null $nullableUnionProp;
        }';

        $tempFile = tempnam(sys_get_temp_dir(), 'test_');
        file_put_contents($tempFile, $code);

        // Include the file
        include_once $tempFile;

        $schema = ClassHelper::parseClass('UnionTypeTestClass', false, false, $this->specMock);

        $this->assertInstanceOf(ObjectSchema::class, $schema);

        $properties = $schema->properties;

        // The exact behavior will depend on how SchemaHelper::mergeSchemas works
        // but we can at least verify the properties exist
        $this->assertArrayHasKey('unionProp', $properties);
        $this->assertArrayHasKey('nullableUnionProp', $properties);
        $this->assertTrue($properties['nullableUnionProp']->nullable);

        // Clean up
        unlink($tempFile);
    }

    /**
     * Test parseClass with onlyFromDocblock parameter
     */
    public function test_parse_class_only_from_docblock()
    {
        // Create a class with both regular properties and docblock properties
        $code = '<?php
        /**
         * @property string $docProp Documentation property
         */
        class MixedPropsTestClass {
            public int $regularProp;
        }';

        $tempFile = tempnam(sys_get_temp_dir(), 'test_');
        file_put_contents($tempFile, $code);

        // Include the file
        include_once $tempFile;

        // Parse with onlyFromDocblock = true
        $schema = ClassHelper::parseClass('MixedPropsTestClass', false, true, $this->specMock);

        $this->assertInstanceOf(ObjectSchema::class, $schema);

        $properties = $schema->properties;
        $this->assertCount(1, $properties);
        $this->assertArrayHasKey('docProp', $properties);
        $this->assertArrayNotHasKey('regularProp', $properties);

        // Clean up
        unlink($tempFile);
    }

}
