<?php

namespace MartinPham\TypeGenerator\Helpers;

use Exception;
use MartinPham\TypeGenerator\Definitions\Items\ComponentSchemaItem;
use MartinPham\TypeGenerator\Definitions\Items\PropertyItem;
use MartinPham\TypeGenerator\Definitions\Schemas\ArraySchema;
use MartinPham\TypeGenerator\Definitions\Schemas\CursorPaginatorSchema;
use MartinPham\TypeGenerator\Definitions\Schemas\DataCursorPaginatorSchema;
use MartinPham\TypeGenerator\Definitions\Schemas\DataPaginatorSchema;
use MartinPham\TypeGenerator\Definitions\Schemas\LengthAwarePaginatorSchema;
use MartinPham\TypeGenerator\Definitions\Schemas\ObjectSchema;
use MartinPham\TypeGenerator\Definitions\Schemas\OneOfSchema;
use MartinPham\TypeGenerator\Definitions\Schemas\PaginatorSchema;
use MartinPham\TypeGenerator\Definitions\Schemas\RefSchema;
use MartinPham\TypeGenerator\Definitions\Schemas\Schema;
use MartinPham\TypeGenerator\Definitions\Schemas\StringSchema;
use phpDocumentor\Reflection\DocBlock\Tags\Return_;
use phpDocumentor\Reflection\DocBlockFactory;
use phpDocumentor\Reflection\PseudoTypes\ArrayShape;
use phpDocumentor\Reflection\Type;
use phpDocumentor\Reflection\Types\Array_;
use phpDocumentor\Reflection\Types\Boolean;
use phpDocumentor\Reflection\Types\Collection as DocBlockCollection;
use phpDocumentor\Reflection\Types\Compound;
use phpDocumentor\Reflection\Types\Float_;
use phpDocumentor\Reflection\Types\Integer;
use phpDocumentor\Reflection\Types\Mixed_;
use phpDocumentor\Reflection\Types\Null_;
use phpDocumentor\Reflection\Types\Object_;
use phpDocumentor\Reflection\Types\String_;
use ReflectionClass;

class DocBlockHelper
{
    public static function parseTagType(Type $type, bool $nullable, ReflectionClass $classReflection, SchemaHelper $schemaHelper)
    {
        if (
            $type instanceof String_
        ) {
            return new Schema(type: 'string', nullable: $nullable);
        } else if (
            $type instanceof Integer
        ) {
            return new Schema(type: 'integer', nullable: $nullable);
        } else if (
            $type instanceof Float_
        ) {
            return new Schema(type: 'number', nullable: $nullable);
        } else if (
            $type instanceof Boolean
        ) {
            return new Schema(type: 'boolean', nullable: $nullable);
        } else if (
            $type instanceof Object_
        ) {
            $fqsen = $type->getFqsen();
            $name = $fqsen->getName();

            $classFullname = ClassHelper::getClassFullname($name, $classReflection);

            if (ClassHelper::isKindOf($classFullname, 'DateTimeInterface')) {
                return new StringSchema(
                    format: "date-time",
                    nullable: $nullable
                );
            }

            else if ($classFullname === 'Illuminate\Http\UploadedFile') {
                return new StringSchema(
                    format: "binary",
                    nullable: $nullable
                );
            }

            else if (ClassHelper::isKindOf($classFullname, 'TiMacDonald\JsonApi\JsonApiResource')) {
                $resourceClass = new ReflectionClass($classFullname);

                $toAttributesMethod = $resourceClass->getMethod('toAttributes');
                if ($toAttributesMethod->getDeclaringClass()->getName() !== 'TiMacDonald\JsonApi\JsonApiResource') {
                    $toAttributesDocs = $toAttributesMethod->getDocComment();
                    if ($toAttributesDocs) {
                        $toAttributesDocBlock = DocBlockFactory::createInstance()->create($toAttributesDocs);
                        $returnTags = $toAttributesDocBlock->getTagsByName('return');

                        /** @var Return_ $propertyTag */
                        foreach ($returnTags as $returnTag) {
                            $schemaHelper->registerSchema($name, function () use ($name, $classFullname, $schemaHelper, $nullable, $returnTag, $resourceClass) {
                                $schema = DocBlockHelper::parseTagType($returnTag->getType(), $nullable, $resourceClass, $schemaHelper);

                                return new ComponentSchemaItem(
                                    id: $name,
                                    schema: new ObjectSchema(
                                        properties: [
                                            'id' => new Schema(
                                                type: 'string',
                                            ),
                                            'attributes' => new ObjectSchema(
                                                properties: $schema->properties
                                            )
                                        ]
                                    )
                                );
                            });

                            return new RefSchema(
                                ref: $name,
                                nullable: $nullable
                            );
                        }
                    }
                }


                $attributes = [];
                CodeHelper::parseClassNodes(
                    $resourceClass,
                    /** @var \PhpParser\Node\Stmt\Property $property */
                    function (\PhpParser\Node\Stmt\Property $property) use (&$attributes) {
                        foreach ($property->props as $prop) {
                            $propertyName = $prop->name->toString();
                            switch ($propertyName) {
                                case 'attributes':
                                    $attributes = array_merge($attributes, CodeHelper::extractArrayValues($prop->default));
                                    break;
                            }
                        }
                    },
                    /** @var ClassMethod $method */
                    function (ClassMethod $method, $methodReturnNodes) use (&$attributes) {
                        $methodName = $method->name->toString();
                        if (
                            $method->isStatic() ||
                            $method->isPrivate() ||
                            !in_array($methodName, ['toAttributes'])
                        ) {
                            return;
                        }

                        foreach ($methodReturnNodes as $return) {
                            if ($methodName === 'toAttributes') {
                                $assocAttributes = CodeHelper::extractAssocArrayValues($return->expr);
                                $attributes = array_merge($attributes, array_keys($assocAttributes));
                            }
                        }
                    }
                );

                $resourceAttributes = ResourceHelper::parseResource($classFullname, $nullable, $resourceClass, $attributes, $schemaHelper);

                $schemaHelper->registerSchema($name, function () use ($name, $resourceAttributes) {
                    return new ComponentSchemaItem(
                        id: $name,
                        schema: new ObjectSchema(
                            properties: [
                                'id' => new Schema(
                                    type: 'string',
                                ),
                                'attributes' => new ObjectSchema(
                                    properties: $resourceAttributes
                                )
                            ]
                        )
                    );
                });

                return new RefSchema(
                    ref: $name,
                    nullable: $nullable
                );
            }

            else if (
                ClassHelper::isKindOf($classFullname, 'Illuminate\Http\Resources\Json\JsonResource')
                || ClassHelper::isKindOf($classFullname, 'Illuminate\Http\Resources\Json\ResourceCollection')
            ) {
                $resourceClass = new ReflectionClass($classFullname);
                $toArrayMethod = $resourceClass->getMethod('toArray');
                $toArrayMethodDocs = $toArrayMethod->getDocComment();
                if ($toArrayMethodDocs) {
                    $toArrayMethodDocBlock = DocBlockFactory::createInstance()->create($toArrayMethodDocs);
                    $returnTags = $toArrayMethodDocBlock->getTagsByName('return');

                    /** @var Return_ $propertyTag */
                    foreach ($returnTags as $returnTag) {
                        $schemaHelper->registerSchema($name, function () use ($name, $classFullname, $schemaHelper, $nullable, $returnTag, $resourceClass) {
                            return new ComponentSchemaItem(
                                id: $name,
                                schema: DocBlockHelper::parseTagType($returnTag->getType(), $nullable, $resourceClass, $schemaHelper)
                            );
                        });

                        return new RefSchema(
                            ref: $name,
                            nullable: $nullable
                        );
                    }
                }


                $attributes = [];
                CodeHelper::parseClassNodes(
                    $resourceClass,
                    /** @var \PhpParser\Node\Stmt\Property $property */
                    function (\PhpParser\Node\Stmt\Property $property) {
                    },
                    /** @var ClassMethod $method */
                    function (ClassMethod $method, $methodReturnNodes) use (&$attributes) {
                        $methodName = $method->name->toString();
                        if (
                            $method->isStatic() ||
                            $method->isPrivate() ||
                            !in_array($methodName, ['toArray'])
                        ) {
                            return;
                        }

                        foreach ($methodReturnNodes as $return) {
                            if ($methodName === 'toArray') {
                                $assocAttributes = CodeHelper::extractAssocArrayValues($return->expr);
                                $attributes = array_merge($attributes, array_keys($assocAttributes));
                            }
                        }
                    }
                );


                $resourceAttributes = ResourceHelper::parseResource($classFullname, $nullable, $resourceClass, $attributes, $schemaHelper);

                $schemaHelper->registerSchema($name, function () use ($name, $resourceAttributes) {
                    return new ComponentSchemaItem(
                        id: $name,
                        schema: new ObjectSchema(
                            properties: $resourceAttributes
                        )
                    );
                });

                return new RefSchema(
                    ref: $name,
                    nullable: $nullable
                );


            }


            $schemaHelper->registerSchema($name, function () use ($name, $classFullname, $schemaHelper, $nullable) {
                return new ComponentSchemaItem(
                    id: $name,
                    schema: ClassHelper::parseClass($classFullname, $nullable, false, $schemaHelper)
                );
            });

            return new RefSchema(
                ref: $name,
                nullable: $nullable
            );
        } else if (
            $type instanceof Compound
        ) {
            $oneOf = [];
            $nullable = false;
            foreach ($type as $innerType) {
                if ($innerType instanceof Null_) {
                    $nullable = true;
                } else {
                    $oneOf[] = self::parseTagType($innerType, false, $classReflection, $schemaHelper);
                }
            }

            return new OneOfSchema(
                oneOf: $oneOf,
                nullable: $nullable
            );
        } else if (
            $type instanceof ArrayShape
        ) {
            $ret = new ObjectSchema();

            foreach ($type->getItems() as $item) {
                $key = $item->getKey();
                $innerType = $item->getValue();

                $ret->putPropertyItem(new PropertyItem(
                    id: $key,
                    schema: self::parseTagType($innerType, false, $classReflection, $schemaHelper)
                ));
            }

            return $ret;
        } else if (
            $type instanceof Array_
        ) {
            $innerType = $type->getValueType();

            return new ArraySchema(
                items: self::parseTagType($innerType, false, $classReflection, $schemaHelper)
            );
        } else if (
            $type instanceof Mixed_
        ) {
            return new Schema(
                type: 'string'
            );
        } else if (
            $type instanceof DocBlockCollection
        ) {
            $collectionType = $type->getFqsen();
            $collectionTypeName = $collectionType->getName();

            $collectionTypeClass = ClassHelper::getClassFullname($collectionTypeName, $classReflection);

            $innerType = $type->getValueType();

            if ($innerType instanceof \phpDocumentor\Reflection\Types\This) {
                $innerType = $type->getKeyType();
            }

            if (
                ClassHelper::isKindOf($collectionTypeClass, 'Illuminate\Http\Resources\Json\JsonResource')
            ) {
                return self::parseTagType($innerType, false, $classReflection, $schemaHelper);
            }

            else if (
                ClassHelper::isKindOf($collectionTypeClass, 'Illuminate\Support\Collection')
                || ClassHelper::isKindOf($collectionTypeClass, 'Spatie\LaravelData\DataCollection')
                || ClassHelper::isKindOf($collectionTypeClass, 'Illuminate\Http\Resources\Json\ResourceCollection')
            ) {
                return new ArraySchema(
                    items: self::parseTagType($innerType, false, $classReflection, $schemaHelper)
                );
            }

            else if (isset(ModelHelper::RELATION_TYPE[$collectionTypeClass])) {
                $mapped = ModelHelper::RELATION_TYPE[$collectionTypeClass];

                if ($mapped === 'single') {
                    return self::parseTagType($innerType, false, $classReflection, $schemaHelper);
                } else if ($mapped === 'multiple') {
                    return new ArraySchema(
                        items: self::parseTagType($innerType, false, $classReflection, $schemaHelper)
                    );
                }
            }

            else if ($collectionTypeClass === 'Spatie\LaravelData\PaginatedDataCollection') {
                /** @var Object_ $innerType */
                $schemaName = $innerType->getFqsen()->getName() . '_DataPaginator';
                $schemaHelper->registerSchema($schemaName, function () use ($schemaName, $innerType, $schemaHelper, $classReflection) {
                    return new ComponentSchemaItem(
                        id: $schemaName,
                        schema: new DataPaginatorSchema(
                            schema: self::parseTagType($innerType, false, $classReflection, $schemaHelper)
                        )
                    );
                });


                return new RefSchema(
                    ref: $schemaName,
                    nullable: false
                );
            }

            else if (ClassHelper::isKindOf($collectionTypeClass, 'Illuminate\Contracts\Pagination\LengthAwarePaginator') ) {
                /** @var Object_ $innerType */
                $schemaName = $innerType->getFqsen()->getName() . '_LengthAwarePaginator';
                $schemaHelper->registerSchema($schemaName, function () use ($schemaName, $innerType, $schemaHelper, $classReflection) {
                    return new ComponentSchemaItem(
                        id: $schemaName,
                        schema: new LengthAwarePaginatorSchema(
                            schema: self::parseTagType($innerType, false, $classReflection, $schemaHelper)
                        )
                    );
                });


                return new RefSchema(
                    ref: $schemaName,
                    nullable: false
                );
            }

            else if (ClassHelper::isKindOf($collectionTypeClass, 'Illuminate\Contracts\Pagination\Paginator')) {
                /** @var Object_ $innerType */
                $schemaName = $innerType->getFqsen()->getName() . '_Paginator';
                $schemaHelper->registerSchema($schemaName, function () use ($schemaName, $innerType, $schemaHelper, $classReflection) {
                    return new ComponentSchemaItem(
                        id: $schemaName,
                        schema: new PaginatorSchema(
                            schema: self::parseTagType($innerType, false, $classReflection, $schemaHelper)
                        )
                    );
                });


                return new RefSchema(
                    ref: $schemaName,
                    nullable: false
                );
            }

            else if ($collectionTypeClass === 'Spatie\LaravelData\CursorPaginatedDataCollection') {
                /** @var Object_ $innerType */
                $schemaName = $innerType->getFqsen()->getName() . '_CursorPaginator';
                $schemaHelper->registerSchema($schemaName, function () use ($schemaName, $innerType, $schemaHelper, $classReflection) {
                    return new ComponentSchemaItem(
                        id: $schemaName,
                        schema: new DataCursorPaginatorSchema(
                            schema: self::parseTagType($innerType, false, $classReflection, $schemaHelper)
                        )
                    );
                });


                return new RefSchema(
                    ref: $schemaName,
                    nullable: false
                );
            }

            else if (ClassHelper::isKindOf($collectionTypeClass, 'Illuminate\Contracts\Pagination\CursorPaginator') ) {
                /** @var Object_ $innerType */
                $schemaName = $innerType->getFqsen()->getName() . '_CursorPaginator';
                $schemaHelper->registerSchema($schemaName, function () use ($schemaName, $innerType, $schemaHelper, $classReflection) {
                    return new ComponentSchemaItem(
                        id: $schemaName,
                        schema: new CursorPaginatorSchema(
                            schema: self::parseTagType($innerType, false, $classReflection, $schemaHelper)
                        )
                    );
                });


                return new RefSchema(
                    ref: $schemaName,
                    nullable: false
                );
            }

            throw new Exception("Cannot understand collection type $collectionTypeName ($collectionTypeClass)");

        }

        throw new Exception("Cannot understand type $type");
    }

}
