<?php

namespace Tests\Unit\TypeGenerator\Helpers;

use MartinPham\TypeGenerator\Helpers\DocBlockHelper;
use MartinPham\TypeGenerator\Writers\OpenAPI\OpenAPI;
use MartinPham\TypeGenerator\Definitions\Schemas\Schema;
use MartinPham\TypeGenerator\Definitions\Schemas\StringSchema;
use MartinPham\TypeGenerator\Definitions\Schemas\ArraySchema;
use MartinPham\TypeGenerator\Definitions\Schemas\ObjectSchema;
use MartinPham\TypeGenerator\Definitions\Schemas\OneOfSchema;
use MartinPham\TypeGenerator\Definitions\Schemas\RefSchema;
use MartinPham\TypeGenerator\Definitions\Spec;
use PHPUnit\Framework\TestCase;
use phpDocumentor\Reflection\DocBlockFactory;
use phpDocumentor\Reflection\Types\String_;
use phpDocumentor\Reflection\Types\Integer;
use phpDocumentor\Reflection\Types\Float_;
use phpDocumentor\Reflection\Types\Boolean;
use phpDocumentor\Reflection\Types\Array_;
use phpDocumentor\Reflection\Types\Object_;
use phpDocumentor\Reflection\Types\Compound;
use phpDocumentor\Reflection\Types\Null_;
use phpDocumentor\Reflection\Fqsen;
use ReflectionClass;

class DocBlockHelperTest extends TestCase
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
     * Test parsing a string type
     */
    public function test_parse_tag_type_string()
    {
        $stringType = new String_();

        $schema = DocBlockHelper::parseTagType($stringType, false, $this->classReflectionMock, $this->specMock);

        $this->assertInstanceOf(Schema::class, $schema);
        $this->assertEquals('string', $schema->type);

        // Test with nullable
        $schema = DocBlockHelper::parseTagType($stringType, true, $this->classReflectionMock, $this->specMock);

        $this->assertInstanceOf(Schema::class, $schema);
        $this->assertEquals('string', $schema->type);
        $this->assertTrue($schema->nullable);
    }

    /**
     * Test parsing an integer type
     */
    public function test_parse_tag_type_integer()
    {
        $intType = new Integer();

        $schema = DocBlockHelper::parseTagType($intType, false, $this->classReflectionMock, $this->specMock);

        $this->assertInstanceOf(Schema::class, $schema);
        $this->assertEquals('integer', $schema->type);
    }

    /**
     * Test parsing a float type
     */
    public function test_parse_tag_type_float()
    {
        $floatType = new Float_();

        $schema = DocBlockHelper::parseTagType($floatType, false, $this->classReflectionMock, $this->specMock);

        $this->assertInstanceOf(Schema::class, $schema);
        $this->assertEquals('number', $schema->type);
    }

    /**
     * Test parsing a boolean type
     */
    public function test_parse_tag_type_boolean()
    {
        $boolType = new Boolean();

        $schema = DocBlockHelper::parseTagType($boolType, false, $this->classReflectionMock, $this->specMock);

        $this->assertInstanceOf(Schema::class, $schema);
        $this->assertEquals('boolean', $schema->type);

    }


    /**
     * Test parsing an array type
     */
    public function test_parse_tag_type_array()
    {
        // Create an array of strings
        $stringType = new String_();
        $arrayType = new Array_($stringType);

        $schema = DocBlockHelper::parseTagType($arrayType, false, $this->classReflectionMock, $this->specMock);

        $this->assertInstanceOf(ArraySchema::class, $schema);
        $this->assertInstanceOf(Schema::class, $schema->items);
        $this->assertEquals('string', $schema->items->type);
    }

}
