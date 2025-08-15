<?php

namespace MartinPham\TypeGenerator\Helpers;

use Exception;
use MartinPham\TypeGenerator\Definitions\Items\ComponentSchemaItem;
use MartinPham\TypeGenerator\Definitions\Schemas\ObjectSchema;
use MartinPham\TypeGenerator\Definitions\Schemas\RefSchema;
use MartinPham\TypeGenerator\Definitions\Schemas\Schema;
use MartinPham\TypeGenerator\Definitions\Schemas\StringSchema;
use phpDocumentor\Reflection\DocBlock\Tags\Property;
use phpDocumentor\Reflection\DocBlock\Tags\Var_;
use phpDocumentor\Reflection\DocBlockFactory;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionProperty;
use ReflectionUnionType;

class ClassHelper
{
    public static function parseClass(string $classFullname, bool $nullable, bool $onlyFromDocblock, SchemaHelper $schemaHelper)
    {
        if (is_subclass_of($classFullname, 'Illuminate\Database\Eloquent\Model')) {
            return ModelHelper::parseModel($classFullname, $nullable, $schemaHelper);
        }
        if (is_subclass_of($classFullname, 'DateTimeInterface')) {
            return new StringSchema(
                format: 'date-time'
            );
        }


        $properties = [];
        $docblockProperties = [];

        $classReflection = new ReflectionClass($classFullname);

        $classDocs = $classReflection->getDocComment();
        if ($classDocs) {
            $classDocblock = DocBlockFactory::createInstance()->create($classDocs);
            $propertyTags = $classDocblock->getTagsByName('property');

            /** @var Property $propertyTag */
            foreach ($propertyTags as $propertyTag) {
                $docblockProperties[$propertyTag->getVariableName()] = DocBlockHelper::parseTagType($propertyTag->getType(), $nullable, $classReflection, $schemaHelper);
            }
        }


        if (!$onlyFromDocblock) {
            $publicProperties = $classReflection->getProperties(ReflectionProperty::IS_PUBLIC);

            foreach ($publicProperties as $property) {
                $propertyName = $property->getName();
                $propertyType = $property->getType();

                if ($propertyType === null) {
                    continue;
                }

                $propertyNullable = $propertyType->allowsNull();
                $propertyTypes = $propertyType instanceof ReflectionUnionType ? $propertyType->getTypes() : [$propertyType];

                $propertySchemas = [];

                /** @var ReflectionNamedType|null $propertyType */
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
                                    $propertySchemas[] = DocBlockHelper::parseTagType($varTag->getType(), false, $classReflection, $schemaHelper);
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

                            $classFullname = ClassHelper::getClassFullname($name, $classReflection);

                            if (is_subclass_of($classFullname, 'DateTimeInterface')) {
                                return new StringSchema(
                                    format: "date-time",
                                    nullable: $nullable
                                );
                            }

                            $schemaHelper->registerSchema($name, function () use ($name, $classFullname, $schemaHelper, $nullable) {
                                return new ComponentSchemaItem(
                                    id: $name,
                                    schema: ClassHelper::parseClass($classFullname, $nullable, false, $schemaHelper)
                                );
                            });


                            $propertySchemas[] = new RefSchema(
                                ref: $name
                            );
                        }
                    }

                }

                if (count($propertySchemas) === 0) {
                    if (isset($docblockProperties[$propertyName])) {
                        $properties[$propertyName] = $docblockProperties[$propertyName];
                        unset($docblockProperties[$propertyName]);
                    } else {
                        throw new Exception("Cannot undestand class structure - $classFullname::$propertyName");
                    }

                } else {
                    $properties[$propertyName] = SchemaHelper::mergeSchemas($propertySchemas, $propertyNullable);
                }
            }
        }

        $properties = array_merge($properties, $docblockProperties);


        return new ObjectSchema(
            properties: $properties,
            nullable: $nullable
        );
    }

    public static function getClassFullname(string $className, ReflectionClass $inClassReflection): string
    {
        $namespace = $inClassReflection->getNamespaceName();
        $imports = CodeHelper::getImports(file_get_contents($inClassReflection->getFileName()));

        $classFullname = $className;

        if (isset($imports[$className])) {
            $classFullname = $imports[$className];
        } else if (!class_exists($className)) {
            if (!class_exists($namespace . '\\' . $className)) {
                throw new Exception("Cannot locate class $className");
            }
            $classFullname = $namespace . '\\' . $className;
        }

        return $classFullname;
    }
}
