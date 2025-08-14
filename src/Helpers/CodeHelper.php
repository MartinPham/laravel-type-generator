<?php

namespace MartinPham\TypeGenerator\Helpers;

use Illuminate\Database\Eloquent\Model as EloquentModel;
use MartinPham\TypeGenerator\Definitions\Items\ComponentSchemaItem;
use MartinPham\TypeGenerator\Definitions\Schemas\Schema;
use MartinPham\TypeGenerator\Definitions\Schemas\StringSchema;
use MartinPham\TypeGenerator\Definitions\Schemas\ObjectSchema;
use MartinPham\TypeGenerator\Definitions\Schemas\RefSchema;
use phpDocumentor\Reflection\DocBlock\Tags\Var_;
use phpDocumentor\Reflection\DocBlockFactory;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\NullableType;
use PhpParser\Node\Scalar\DNumber;
use PhpParser\Node\Scalar\LNumber;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Property;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\Return_;
use PhpParser\NodeFinder;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor;
use PhpParser\NodeVisitorAbstract;
use PhpParser\ParserFactory;
use PHPStan\Type\UnionType;
use ReflectionClass;
use ReflectionProperty;
use ReflectionUnionType;

class CodeHelper
{

    public static function getImports($contents): array
    {
        // Only parse up to the class/interface/trait definition
        $classPos = strpos($contents, 'class');
        if ($classPos === false) {
            $classPos = strpos($contents, 'interface');
        }
        if ($classPos === false) {
            $classPos = strpos($contents, 'trait');
        }
        if ($classPos === false) {
            $classPos = strlen($contents); // fallback
        }

        $head = substr($contents, 0, $classPos);

        // Normalize use statements (collapse multi-line use blocks)
        $head = preg_replace('/\s*\\\s*\n\s*/', '\\', $head); // Join split namespaces
        $head = preg_replace('/use\s+([^;]+);/mi', 'use $1;', $head); // Clean spacing
        $head = preg_replace('/\s+/', ' ', $head); // Remove excessive whitespace

        preg_match_all('/use\s+([^;]+);/', $head, $matches);

        $mapped = [];

        foreach ($matches[1] as $importBlock) {
            // Split by commas not in braces
            $parts = preg_split('/,(?![^{]*})/', $importBlock);
            foreach ($parts as $part) {
                $part = trim($part);

                // Grouped import: use Foo\Bar\{Baz, Qux as Alias};
                if (preg_match('/^(.+?)\\\{(.+)}$/', $part, $groupMatch)) {
                    $base = trim($groupMatch[1], '\\');
                    $classes = explode(',', $groupMatch[2]);
                    foreach ($classes as $class) {
                        $class = trim($class);
                        if (stripos($class, ' as ') !== false) {
                            [$original, $alias] = preg_split('/\s+as\s+/i', $class);
                            $mapped[trim($alias)] = $base . '\\' . trim($original);
                        } else {
                            $mapped[$class] = $base . '\\' . $class;
                        }
                    }
                }
                // Aliased import
                elseif (stripos($part, ' as ') !== false) {
                    [$fqcn, $alias] = preg_split('/\s+as\s+/i', $part);
                    $mapped[trim($alias)] = trim($fqcn);
                }
                // Simple import
                else {
                    $fqcn = trim($part);
                    $segments = explode('\\', $fqcn);
                    $classname = end($segments);
                    $mapped[$classname] = $fqcn;
                }
            }
        }

        return $mapped;
    }

    public static function createAST(\ReflectionClass $classReflection) {
        $parser = (new ParserFactory())->createForNewestSupportedVersion();
        $contents = file_get_contents($classReflection->getFileName());

        return $parser->parse($contents);
    }

    public static function parseClassNodes(\ReflectionClass $classReflection, callable $handleProperty, callable $handleMethod) {
        $nodeFinder = new NodeFinder();
        $ast = CodeHelper::createAST($classReflection);
        $classNodes = $nodeFinder->findInstanceOf($ast, Class_::class);

        foreach ($classNodes as $node) {
            $propertyNodes = $nodeFinder->findInstanceOf($node, Property::class);
            foreach ($propertyNodes as $property) {
                $handleProperty($property);
            }

            $methodNodes = $nodeFinder->findInstanceOf($node, ClassMethod::class);
            foreach ($methodNodes as $method) {
                $returnNodes = $nodeFinder->findInstanceOf($method, Return_::class);
                $handleMethod($method, $returnNodes);
            }
        }
    }


    public static function parseClassCode(\ReflectionClass $classReflection, NodeVisitor $visitor)
    {
        $ast = self::createAST($classReflection);

        $traverser = new NodeTraverser();
        $traverser->addVisitor($visitor);
        $traverser->traverse($ast);

        return $visitor->results;
    }

    public static function extractArgumentValue($arg)
    {
        if ($arg instanceof ClassConstFetch) {
            if ($arg->class instanceof Name && $arg->name->toString() === 'class') {
                return $arg->class->toString();
            }
        } elseif ($arg instanceof String_) {
            return $arg->value;
        } elseif ($arg instanceof LNumber) {
            return $arg->value;
        } elseif ($arg instanceof Variable) {
            return '$' . $arg->name;
        }

        return 'unknown';
    }

    public static function extractStringValue($node)
    {
        if ($node instanceof String_) {
            return $node->value;
        }
        return null;
    }

    public static function extractArrayValues($node)
    {
        if (!($node instanceof Array_)) {
            return [];
        }

        $values = [];
        foreach ($node->items as $item) {
            if ($item && $item->value instanceof String_) {
                $values[] = $item->value->value;
            }
        }

        return $values;
    }

    public static function extractAssocArrayValues($node)
    {
        if (!($node instanceof Array_)) {
            return [];
        }

        $values = [];
        foreach ($node->items as $item) {
            if ($item && $item->key && $item->value) {
                $key = self::extractScalarValue($item->key);
                $value = self::extractScalarValue($item->value);
                if ($key !== null) {
                    $values[$key] = $value;
                }
            }
        }

        return $values;
    }

    public static function extractScalarValue($node)
    {
        if ($node instanceof String_) {
            return $node->value;
        } elseif ($node instanceof LNumber) {
            return $node->value;
        } elseif ($node instanceof DNumber) {
            return $node->value;
        } elseif ($node instanceof ConstFetch) {
            return $node->name->toString();
        }

        return null;
    }

    public static function getTypeString($type)
    {
        if ($type instanceof Name) {
            return $type->toString();
        } elseif ($type instanceof Identifier) {
            return $type->toString();
        } elseif ($type instanceof UnionType) {
            $types = [];
            foreach ($type->getTypes() as $unionType) {
                $types[] = self::getTypeString($unionType);
            }
            return implode('|', $types);
        } elseif ($type instanceof NullableType) {
            return self::getTypeString($type->type) . '|null';
        }

        return 'unknown';
    }

}
