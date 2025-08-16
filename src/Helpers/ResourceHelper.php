<?php

namespace MartinPham\TypeGenerator\Helpers;

use Exception;
use phpDocumentor\Reflection\DocBlock\Tags\Mixin;
use phpDocumentor\Reflection\DocBlock\Tags\Property;
use phpDocumentor\Reflection\DocBlock\Tags\PropertyRead;
use phpDocumentor\Reflection\DocBlockFactory;
use ReflectionClass;

class ResourceHelper
{
    public static function parseResource(string $classFullname, bool $nullable, ReflectionClass $resourceClass, array $attributes, SchemaHelper $schemaHelper): array
    {
        $resourceAttributes = [];
        $typeClassName = null;
        $resourceDocs = $resourceClass->getDocComment();
        if ($resourceDocs) {
            $resourceDocBlock = DocBlockFactory::createInstance()->create($resourceDocs);
            $mixinTags = $resourceDocBlock->getTagsByName('mixin');
            $propertyTags = $resourceDocBlock->getTagsByName('property');
            $propertyReadTags = $resourceDocBlock->getTagsByName('property-read');


            /** @var Mixin $mixinTag */
            foreach ($mixinTags as $mixinTag) {
                $type = $mixinTag->getType();
                $typeClassName = $type->getFqsen()->getName();
            }

            if ($typeClassName === null) {
                /** @var Property $mixinTag */
                foreach ($propertyTags as $propertyTag) {
                    $type = $mixinTag->getType();
                    $typeClassName = $type->getFqsen()->getName();
                }
            }


            if ($typeClassName === null) {
                /** @var PropertyRead $mixinTag */
                foreach ($propertyReadTags as $propertyReadTag) {
                    $type = $propertyReadTag->getType();
                    $typeClassName = $type->getFqsen()->getName();
                }
            }
        }


        if ($typeClassName === null) {
            throw new Exception("Cannot understand which model class of resource $classFullname");
        }

        $typeClassFullname = ClassHelper::getClassFullname($typeClassName, $resourceClass);
        $resourceModel = ModelHelper::parseModel($typeClassFullname, $nullable, $schemaHelper);

        foreach ($attributes as $attribute) {
            if (isset($resourceModel->properties[$attribute])) {
                $resourceAttributes[$attribute] = $resourceModel->properties[$attribute];
            }
        }

        return $resourceAttributes;
    }
}
