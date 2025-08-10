<?php

namespace Tests\Unit\TypeGenerator\Helpers;

use MartinPham\TypeGenerator\Helpers\ClassHelper;
use MartinPham\TypeGenerator\Definitions\OpenAPI;
use MartinPham\TypeGenerator\Definitions\Schema;
use MartinPham\TypeGenerator\Definitions\StringSchema;
use MartinPham\TypeGenerator\Definitions\ObjectSchema;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use DateTime;

class ClassHelperTest extends TestCase
{
    /**
     * Test getClassImports with a class that has imports
     */
    public function test_get_class_imports_with_imports()
    {
        $reflection = new ReflectionClass(ClassHelper::class);
        $imports = ClassHelper::getClassImports($reflection);

        $this->assertIsArray($imports);
        $this->assertNotEmpty($imports);

        // Check for specific imports that should be in the ClassHelper class
        $this->assertArrayHasKey('EloquentModel', $imports);
        $this->assertEquals('Illuminate\Database\Eloquent\Model', $imports['EloquentModel']);
    }

    /**
     * Test getClassImports with a class that has no imports
     */
    public function test_get_class_imports_with_no_imports()
    {
        // Create a simple class with no imports for testing
        $code = '<?php class EmptyClass {}';
        $tempFile = tempnam(sys_get_temp_dir(), 'test_');
        file_put_contents($tempFile, $code);

        // Include the file and get a reflection of the class
        include_once $tempFile;
        $reflection = new ReflectionClass('EmptyClass');

        $imports = ClassHelper::getClassImports($reflection);

        $this->assertIsArray($imports);
        $this->assertEmpty($imports);

        // Clean up
        unlink($tempFile);
    }

    /**
     * Test getClassImports with a class that has grouped imports
     */
    public function test_get_class_imports_with_grouped_imports()
    {
        // Create a class with grouped imports for testing
        $code = '<?php
        namespace Test;
        use Foo\Bar\{Baz, Qux as AliasQux};
        class GroupedImportsClass {}';

        $tempFile = tempnam(sys_get_temp_dir(), 'test_');
        file_put_contents($tempFile, $code);

        // Include the file and get a reflection of the class
        include_once $tempFile;
        $reflection = new ReflectionClass('Test\GroupedImportsClass');

        $imports = ClassHelper::getClassImports($reflection);

        $this->assertIsArray($imports);
        $this->assertNotEmpty($imports);
        $this->assertArrayHasKey('Baz', $imports);
        $this->assertEquals('Foo\Bar\Baz', $imports['Baz']);
        $this->assertArrayHasKey('AliasQux', $imports);
        $this->assertEquals('Foo\Bar\Qux', $imports['AliasQux']);

        // Clean up
        unlink($tempFile);
    }

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

        // Include the file and create a mock OpenAPI spec
        include_once $tempFile;
        $spec = $this->createMock(OpenAPI::class);


        $schema = ClassHelper::parseClass('ArrayPropsTestClass', $spec, false);

        $this->assertInstanceOf(ObjectSchema::class, $schema);

        // Clean up
        unlink($tempFile);
    }
}
