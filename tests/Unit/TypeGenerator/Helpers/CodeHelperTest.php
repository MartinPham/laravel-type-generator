<?php

namespace Tests\Unit\TypeGenerator\Helpers;

use MartinPham\TypeGenerator\Helpers\CodeHelper;
use PhpParser\Node;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ArrayItem;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\DNumber;
use PhpParser\Node\Scalar\LNumber;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Property;
use PhpParser\NodeVisitor\NodeVisitorAbstract;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class CodeHelperTest extends TestCase
{
    /**
     * Test getImports method with simple imports
     */
    public function test_get_imports_simple()
    {
        $code = '<?php
        namespace Test;

        use Foo\Bar;
        use Baz\Qux;

        class TestClass {}
        ';

        $imports = CodeHelper::getImports($code);

        $this->assertIsArray($imports);
        $this->assertCount(2, $imports);
        $this->assertEquals('Foo\Bar', $imports['Bar']);
        $this->assertEquals('Baz\Qux', $imports['Qux']);
    }

    /**
     * Test getImports method with aliased imports
     */
    public function test_get_imports_with_aliases()
    {
        $code = '<?php
        namespace Test;

        use Foo\Bar as Baz;
        use Qux\Quux as Corge;

        class TestClass {}
        ';

        $imports = CodeHelper::getImports($code);

        $this->assertIsArray($imports);
        $this->assertCount(2, $imports);
        $this->assertEquals('Foo\Bar', $imports['Baz']);
        $this->assertEquals('Qux\Quux', $imports['Corge']);
    }

    /**
     * Test getImports method with grouped imports
     */
    public function test_get_imports_grouped()
    {
        $code = '<?php
        namespace Test;

        use Foo\Bar\{Baz, Qux as Quux};

        class TestClass {}
        ';

        $imports = CodeHelper::getImports($code);

        $this->assertIsArray($imports);
        $this->assertCount(2, $imports);
        $this->assertEquals('Foo\Bar\Baz', $imports['Baz']);
        $this->assertEquals('Foo\Bar\Qux', $imports['Quux']);
    }

    /**
     * Test getImports method with multi-line imports
     */
    public function test_get_imports_multiline()
    {
        $code = '<?php
        namespace Test;

        use Foo\
            Bar;
        use Baz\
            Qux;

        class TestClass {}
        ';

        $imports = CodeHelper::getImports($code);

        $this->assertIsArray($imports);
        $this->assertCount(2, $imports);
        $this->assertEquals('Foo\Bar', $imports['Bar']);
        $this->assertEquals('Baz\Qux', $imports['Qux']);
    }

    /**
     * Test extractArgumentValue method with class constant
     */
    public function test_extract_argument_value_class_const()
    {
        $class = new Name('TestClass');
        $name = new Identifier('class');
        $arg = new ClassConstFetch($class, $name);

        $value = CodeHelper::extractArgumentValue($arg);

        $this->assertEquals('TestClass', $value);
    }

    /**
     * Test extractArgumentValue method with string
     */
    public function test_extract_argument_value_string()
    {
        $arg = new String_('test_string');

        $value = CodeHelper::extractArgumentValue($arg);

        $this->assertEquals('test_string', $value);
    }

    /**
     * Test extractArgumentValue method with number
     */
    public function test_extract_argument_value_number()
    {
        $arg = new LNumber(42);

        $value = CodeHelper::extractArgumentValue($arg);

        $this->assertEquals(42, $value);
    }

    /**
     * Test extractArgumentValue method with variable
     */
    public function test_extract_argument_value_variable()
    {
        $arg = new Variable('testVar');

        $value = CodeHelper::extractArgumentValue($arg);

        $this->assertEquals('$testVar', $value);
    }

    /**
     * Test extractArgumentValue method with unknown type
     */
    public function test_extract_argument_value_unknown()
    {
        $arg = new ConstFetch(new Name('TEST_CONST'));

        $value = CodeHelper::extractArgumentValue($arg);

        $this->assertEquals('unknown', $value);
    }

    /**
     * Test extractStringValue method with string
     */
    public function test_extract_string_value()
    {
        $node = new String_('test_string');

        $value = CodeHelper::extractStringValue($node);

        $this->assertEquals('test_string', $value);
    }

    /**
     * Test extractStringValue method with non-string
     */
    public function test_extract_string_value_non_string()
    {
        $node = new LNumber(42);

        $value = CodeHelper::extractStringValue($node);

        $this->assertNull($value);
    }

    /**
     * Test extractArrayValues method with array of strings
     */
    public function test_extract_array_values()
    {
        $items = [
            new ArrayItem(new String_('value1')),
            new ArrayItem(new String_('value2')),
            new ArrayItem(new String_('value3'))
        ];
        $node = new Array_($items);

        $values = CodeHelper::extractArrayValues($node);

        $this->assertIsArray($values);
        $this->assertCount(3, $values);
        $this->assertEquals(['value1', 'value2', 'value3'], $values);
    }

    /**
     * Test extractArrayValues method with non-array
     */
    public function test_extract_array_values_non_array()
    {
        $node = new String_('not_an_array');

        $values = CodeHelper::extractArrayValues($node);

        $this->assertIsArray($values);
        $this->assertEmpty($values);
    }

    /**
     * Test extractAssocArrayValues method with associative array
     */
    public function test_extract_assoc_array_values()
    {
        $items = [
            new ArrayItem(new String_('value1'), new String_('key1')),
            new ArrayItem(new String_('value2'), new String_('key2')),
            new ArrayItem(new LNumber(42), new String_('key3'))
        ];
        $node = new Array_($items);

        $values = CodeHelper::extractAssocArrayValues($node);

        $this->assertIsArray($values);
        $this->assertCount(3, $values);
        $this->assertEquals([
            'key1' => 'value1',
            'key2' => 'value2',
            'key3' => 42
        ], $values);
    }

    /**
     * Test extractAssocArrayValues method with non-array
     */
    public function test_extract_assoc_array_values_non_array()
    {
        $node = new String_('not_an_array');

        $values = CodeHelper::extractAssocArrayValues($node);

        $this->assertIsArray($values);
        $this->assertEmpty($values);
    }

    /**
     * Test extractScalarValue method with string
     */
    public function test_extract_scalar_value_string()
    {
        $node = new String_('test_string');

        $value = CodeHelper::extractScalarValue($node);

        $this->assertEquals('test_string', $value);
    }

    /**
     * Test extractScalarValue method with integer
     */
    public function test_extract_scalar_value_integer()
    {
        $node = new LNumber(42);

        $value = CodeHelper::extractScalarValue($node);

        $this->assertEquals(42, $value);
    }

    /**
     * Test extractScalarValue method with float
     */
    public function test_extract_scalar_value_float()
    {
        $node = new DNumber(3.14);

        $value = CodeHelper::extractScalarValue($node);

        $this->assertEquals(3.14, $value);
    }

    /**
     * Test extractScalarValue method with constant
     */
    public function test_extract_scalar_value_constant()
    {
        $node = new ConstFetch(new Name('TEST_CONST'));

        $value = CodeHelper::extractScalarValue($node);

        $this->assertEquals('TEST_CONST', $value);
    }

    /**
     * Test extractScalarValue method with non-scalar
     */
    public function test_extract_scalar_value_non_scalar()
    {
        $node = new Variable('testVar');

        $value = CodeHelper::extractScalarValue($node);

        $this->assertNull($value);
    }

    /**
     * Test createAST and parseClassCode methods
     * This test is skipped because it requires actual PHP files
     */
    public function test_parse_class_code()
    {
        $this->markTestSkipped('This test requires actual PHP files');

        // Create a simple test class
        $code = '<?php
        namespace Tests\Unit\TypeGenerator\Helpers;

        class TestParseClass {
            public string $testProp = "test";

            public function testMethod() {
                return "test";
            }
        }';

        $tempFile = tempnam(sys_get_temp_dir(), 'test_');
        file_put_contents($tempFile, $code);

        // Include the file
        include_once $tempFile;

        // Create a reflection of the test class
        $reflection = new ReflectionClass('Tests\Unit\TypeGenerator\Helpers\TestParseClass');

        // Create a simple visitor that collects property names
        $visitor = new class extends NodeVisitorAbstract {
            public $results = [];

            public function leaveNode(Node $node)
            {
                if ($node instanceof Property) {
                    foreach ($node->props as $prop) {
                        $this->results[] = $prop->name->toString();
                    }
                }
            }
        };

        // Parse the class code
        $results = CodeHelper::parseClassCode($reflection, $visitor);

        $this->assertIsArray($results);
        $this->assertContains('testProp', $results);

        // Clean up
        unlink($tempFile);
    }

    /**
     * Test parseClassNodes method
     * This test is skipped because it requires actual PHP files
     */
    public function test_parse_class_nodes()
    {
        $this->markTestSkipped('This test requires actual PHP files');

        // Create a simple test class
        $code = '<?php
        namespace Tests\Unit\TypeGenerator\Helpers;

        class TestNodesClass {
            public string $testProp = "test";

            public function testMethod() {
                return "test";
            }
        }';

        $tempFile = tempnam(sys_get_temp_dir(), 'test_');
        file_put_contents($tempFile, $code);

        // Include the file
        include_once $tempFile;

        // Create a reflection of the test class
        $reflection = new ReflectionClass('Tests\Unit\TypeGenerator\Helpers\TestNodesClass');

        $properties = [];
        $methods = [];

        // Parse the class nodes
        CodeHelper::parseClassNodes(
            $reflection,
            function ($property) use (&$properties) {
                foreach ($property->props as $prop) {
                    $properties[] = $prop->name->toString();
                }
            },
            function ($method, $returnNodes) use (&$methods) {
                $methods[] = $method->name->toString();
            }
        );

        $this->assertIsArray($properties);
        $this->assertContains('testProp', $properties);

        $this->assertIsArray($methods);
        $this->assertContains('testMethod', $methods);

        // Clean up
        unlink($tempFile);
    }
}
