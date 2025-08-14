<?php

namespace MartinPham\TypeGenerator\Helpers;

use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\LengthAwarePaginator;
use MartinPham\TypeGenerator\Definitions\Schemas\ArraySchema;
use MartinPham\TypeGenerator\Definitions\Items\ComponentSchemaItem;
use MartinPham\TypeGenerator\Definitions\Schemas\CursorPaginatorSchema;
use MartinPham\TypeGenerator\Definitions\Schemas\DataCursorPaginatorSchema;
use MartinPham\TypeGenerator\Definitions\Schemas\DataPaginatorSchema;
use MartinPham\TypeGenerator\Definitions\Schemas\LengthAwarePaginatorSchema;
use MartinPham\TypeGenerator\Definitions\Schemas\ObjectSchema;
use MartinPham\TypeGenerator\Definitions\Schemas\OneOfSchema;
use MartinPham\TypeGenerator\Definitions\Items\PropertyItem;
use MartinPham\TypeGenerator\Definitions\Schemas\PaginatorSchema;
use MartinPham\TypeGenerator\Definitions\Schemas\RefSchema;
use MartinPham\TypeGenerator\Definitions\Schemas\Schema;
use MartinPham\TypeGenerator\Definitions\Schemas\StringSchema;
use MartinPham\TypeGenerator\Definitions\Spec;
use phpDocumentor\Reflection\DocBlock\Tags\Property;
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
use phpDocumentor\Reflection\Types\Null_;
use phpDocumentor\Reflection\Types\Object_;
use phpDocumentor\Reflection\Types\String_;
use phpDocumentor\Reflection\Types\This;

class DocBlockHelper
{
    public static function parseTagType(Type $type, $nullable, $classReflection, Spec $spec)
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

            if (is_subclass_of($classFullname, 'DateTimeInterface')) {
                return new StringSchema(
                    format: "date-time",
                    nullable: $nullable
                );
            } else if ($classFullname === UploadedFile::class) {
                return new StringSchema(
                    format: "binary",
                    nullable: $nullable
                );
            } else if (is_subclass_of($classFullname, 'Illuminate\Http\Resources\Json\JsonResource') || is_subclass_of($classFullname, 'Illuminate\Http\Resources\Json\ResourceCollection')) {
                $resourceClass = new \ReflectionClass($classFullname);
                $toArrayMethod = $resourceClass->getMethod('toArray');
                $toArrayMethodDocs = $toArrayMethod->getDocComment();
                if ($toArrayMethodDocs) {
                    $toArrayMethodDocBlock = DocBlockFactory::createInstance()->create($toArrayMethodDocs);
                    $returnTags = $toArrayMethodDocBlock->getTagsByName('return');

                    /** @var Return_ $propertyTag */
                    foreach ($returnTags as $returnTag) {

                        $spec->putComponentSchema($name, function () use ($name, $classFullname, $spec, $nullable, $returnTag, $resourceClass) {
                            return new ComponentSchemaItem(
                                id: $name,
                                schema: DocBlockHelper::parseTagType($returnTag->getType(), $nullable, $resourceClass, $spec)
                            );
                        });

                        return new RefSchema(
                            ref: $name,
                            nullable: $nullable
                        );
                    }
                }
            }


            $spec->putComponentSchema($name, function () use ($name, $classFullname, $spec, $nullable) {
                return new ComponentSchemaItem(
                    id: $name,
                    schema: ClassHelper::parseClass((string) $classFullname, $spec, $nullable)
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
                    $oneOf[] = self::parseTagType($innerType, false, $classReflection, $spec);
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
                    schema: self::parseTagType($innerType, false, $classReflection, $spec)
                ));
            }

            return $ret;
        } else if (
            $type instanceof Array_
        ) {
            $innerType = $type->getValueType();

            return new ArraySchema(
                items: self::parseTagType($innerType, false, $classReflection, $spec)
            );
        } else if (
            $type instanceof DocBlockCollection
        ) {
            $collectionType = $type->getFqsen();
            $collectionTypeName = $collectionType->getName();

            $collectionTypeClass = ClassHelper::getClassFullname($collectionTypeName, $classReflection);

            $innerType = $type->getValueType();

            if ($innerType instanceof This) {
                $innerType = $type->getKeyType();
            }

            if (is_subclass_of($collectionTypeClass, 'Illuminate\Support\Collection') || $collectionTypeClass === 'Spatie\LaravelData\DataCollection') {
                return new ArraySchema(
                    items: self::parseTagType($innerType, false, $classReflection, $spec)
                );
            } else if (isset(ModelHelper::RELATION_TYPE[$collectionTypeClass])) {
                $mapped = ModelHelper::RELATION_TYPE[$collectionTypeClass];

                if ($mapped === 'single') {
                    return self::parseTagType($innerType, false, $classReflection, $spec);
                }else if ($mapped === 'multiple') {
                    return new ArraySchema(
                        items: self::parseTagType($innerType, false, $classReflection, $spec)
                    );
                }
            } else if ($collectionTypeClass === 'Spatie\LaravelData\PaginatedDataCollection') {
                /** @var Object_ $innerType */
                $schemaName = $innerType->getFqsen()->getName() . '_DataPaginator';
                $spec->putComponentSchema($schemaName, function () use ($schemaName, $innerType, $spec, $classReflection) {
                    return new ComponentSchemaItem(
                        id: $schemaName,
                        schema: new DataPaginatorSchema(
                            schema: self::parseTagType($innerType, false, $classReflection, $spec)
                        )
                    );
                });


                return new RefSchema(
                    ref: $schemaName,
                    nullable: false
                );
            } else if (is_subclass_of($collectionTypeClass, 'Illuminate\Contracts\Pagination\LengthAwarePaginator')) {
                /** @var Object_ $innerType */
                $schemaName = $innerType->getFqsen()->getName() . '_LengthAwarePaginator';
                $spec->putComponentSchema($schemaName, function () use ($schemaName, $innerType, $spec, $classReflection) {
                    return new ComponentSchemaItem(
                        id: $schemaName,
                        schema: new LengthAwarePaginatorSchema(
                            schema: self::parseTagType($innerType, false, $classReflection, $spec)
                        )
                    );
                });


                return new RefSchema(
                    ref: $schemaName,
                    nullable: false
                );
            } else if (is_subclass_of($collectionTypeClass, 'Illuminate\Contracts\Pagination\Paginator')) {
                /** @var Object_ $innerType */
                $schemaName = $innerType->getFqsen()->getName() . '_Paginator';
                $spec->putComponentSchema($schemaName, function () use ($schemaName, $innerType, $spec, $classReflection) {
                    return new ComponentSchemaItem(
                        id: $schemaName,
                        schema: new PaginatorSchema(
                            schema: self::parseTagType($innerType, false, $classReflection, $spec)
                        )
                    );
                });


                return new RefSchema(
                    ref: $schemaName,
                    nullable: false
                );
            } else if ($collectionTypeClass === 'Spatie\LaravelData\CursorPaginatedDataCollection') {
                /** @var Object_ $innerType */
                $schemaName = $innerType->getFqsen()->getName() . '_CursorPaginator';
                $spec->putComponentSchema($schemaName, function () use ($schemaName, $innerType, $spec, $classReflection) {
                    return new ComponentSchemaItem(
                        id: $schemaName,
                        schema: new DataCursorPaginatorSchema(
                            schema: self::parseTagType($innerType, false, $classReflection, $spec)
                        )
                    );
                });


                return new RefSchema(
                    ref: $schemaName,
                    nullable: false
                );
            } else if (is_subclass_of($collectionTypeClass, 'Illuminate\Contracts\Pagination\CursorPaginator')) {
                /** @var Object_ $innerType */
                $schemaName = $innerType->getFqsen()->getName() . '_CursorPaginator';
                $spec->putComponentSchema($schemaName, function () use ($schemaName, $innerType, $spec, $classReflection) {
                    return new ComponentSchemaItem(
                        id: $schemaName,
                        schema: new CursorPaginatorSchema(
                            schema: self::parseTagType($innerType, false, $classReflection, $spec)
                        )
                    );
                });


                return new RefSchema(
                    ref: $schemaName,
                    nullable: false
                );
            }

            throw new \Exception("Cannot understand collection type $collectionTypeName ($collectionTypeClass)");

        }

        throw new \Exception("Cannot understand type $type");
    }

}
