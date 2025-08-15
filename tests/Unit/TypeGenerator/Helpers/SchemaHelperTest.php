<?php

namespace Tests\Unit\TypeGenerator\Helpers;

use MartinPham\TypeGenerator\Definitions\Schemas\OneOfSchema;
use MartinPham\TypeGenerator\Definitions\Schemas\Schema;
use MartinPham\TypeGenerator\Helpers\SchemaHelper;
use PHPUnit\Framework\TestCase;

class SchemaHelperTest extends TestCase
{
    /**
     * Test merging a single schema
     */
    public function test_merge_schemas_single()
    {
        $schema = new Schema(type: 'string');

        $result = SchemaHelper::mergeSchemas([$schema]);

        $this->assertSame($schema, $result);
        $this->assertEquals('string', $result->type);
    }

    /**
     * Test merging a single schema with nullable flag
     */
    public function test_merge_schemas_single_nullable()
    {
        $schema = new Schema(type: 'string');

        $result = SchemaHelper::mergeSchemas([$schema], true);

        $this->assertSame($schema, $result);
        $this->assertEquals('string', $result->type);
        $this->assertTrue($result->nullable);
    }

    /**
     * Test merging multiple schemas
     */
    public function test_merge_schemas_multiple()
    {
        $schema1 = new Schema(type: 'string');
        $schema2 = new Schema(type: 'integer');

        $result = SchemaHelper::mergeSchemas([$schema1, $schema2]);

        $this->assertInstanceOf(OneOfSchema::class, $result);
        $this->assertCount(2, $result->oneOf);
        $this->assertSame($schema1, $result->oneOf[0]);
        $this->assertSame($schema2, $result->oneOf[1]);
    }

    /**
     * Test merging multiple schemas with nullable flag
     */
    public function test_merge_schemas_multiple_nullable()
    {
        $schema1 = new Schema(type: 'string');
        $schema2 = new Schema(type: 'integer');

        $result = SchemaHelper::mergeSchemas([$schema1, $schema2], true);

        $this->assertInstanceOf(OneOfSchema::class, $result);
        $this->assertCount(2, $result->oneOf);
        $this->assertSame($schema1, $result->oneOf[0]);
        $this->assertSame($schema2, $result->oneOf[1]);
        $this->assertTrue($result->nullable);
    }

}
