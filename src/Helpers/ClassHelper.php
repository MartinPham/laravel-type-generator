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
use ReflectionClass;
use ReflectionProperty;
use ReflectionUnionType;

class ClassHelper
{
    public static function getClassImports(ReflectionClass $reflection): array
    {
        $filename = $reflection->getFileName();
        if (!$filename || !file_exists($filename)) {
            return [];
        }

        $contents = file_get_contents($filename);

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


    public static function parseClass(string $classFullname, $spec, $nullable)
    {
        if (is_subclass_of($classFullname, EloquentModel::class)) {
            return ModelHelper::parseModel($classFullname, $spec, $nullable);
        }
        if (is_subclass_of($classFullname, 'DateTimeInterface')) {
            return new StringSchema(
                format: 'date-time'
            );
        }


        $classReflection = new ReflectionClass($classFullname);
        $publicProperties = $classReflection->getProperties(ReflectionProperty::IS_PUBLIC);


        $properties = [];


        foreach ($publicProperties as $property) {
            $propertyName = $property->getName();
            $propertyType = $property->getType();

            if ($propertyType === null) {
                continue;
            }

            $propertyNullable = $propertyType->allowsNull();
            $propertyTypes = $propertyType instanceof ReflectionUnionType ? $propertyType->getTypes() : [$propertyType];

            $propertySchemas = [];

            /** @var \ReflectionNamedType|null $propertyType */
            foreach ($propertyTypes as $propertyType) {
                if ($propertyType !== null) {
                    $propertyTypeName = $propertyType->getName();

                    if ($propertyTypeName === 'null') {
                        $propertyNullable = true;
                    } else if ($propertyTypeName === 'array') {
                        $propertyDocs = $property->getDocComment();

                        if ($propertyDocs) {
                            $propertyDocblock = DocBlockFactory::createInstance()->create($propertyDocs);
                            $varTags = $propertyDocblock->getTagsByName('var');

                            /** @var Var_ $varTag */
                            foreach ($varTags as $varTag) {
                                $propertySchemas[] = DocBlockHelper::parseTagType($varTag->getType(), false, $spec, $classReflection);
                            }
                        }
                    } else if ($propertyTypeName === 'bool') {
                        $propertySchemas[] = new Schema(
                            type: 'boolean'
                        );
                    } else if ($propertyTypeName === 'int') {
                        $propertySchemas[] = new Schema(
                            type: 'integer'
                        );
                    } else if ($propertyTypeName === 'float') {
                        $propertySchemas[] = new Schema(
                            type: 'number'
                        );
                    } else if ($propertyTypeName === 'string') {
                        $propertySchemas[] = new Schema(
                            type: 'string'
                        );
                    } else if (is_subclass_of($propertyTypeName, 'DateTimeInterface')) {
                        $propertySchemas[] = new StringSchema(
                            format: 'date-time'
                        );
                    } else {
                        $name = $propertyTypeName;
                        $namespace = $classReflection->getNamespaceName();
                        $imports = ClassHelper::getClassImports($classReflection);

                        $classFullname = $propertyTypeName;

                        if (isset($imports[$name])) {
                            $classFullname = $imports[$name];
                        } else if (!class_exists($name)) {
                            if (!class_exists($namespace . '\\' . $name)) {
                                throw new \Exception("Cannot locate class $name");
                            }
                            $classFullname = $namespace . '\\' . $name;
                        }

                        if (is_subclass_of($classFullname, 'DateTimeInterface')) {
                            return new StringSchema(
                                format: "date-time",
                                nullable: $nullable
                            );
                        }

                        $spec->putComponentSchema($name, function () use ($name, $classFullname, $spec, $nullable) {
                            return new ComponentSchemaItem(
                                id: $name,
                                schema: ClassHelper::parseClass((string) $classFullname, $spec, $nullable)
                            );
                        });


                        $propertySchemas[] = new RefSchema(
                            ref: $name
                        );
                    }
                }

            }

            if (count($propertySchemas) === 0) {
                throw new \Exception("Cannot undestand class structure - $classFullname::$propertyName");
            }

            $properties[$propertyName] = SchemaHelper::mergeSchemas($propertySchemas, $propertyNullable);
        }

        return new ObjectSchema(
            properties: $properties,
            nullable: $nullable
        );
    }
}
