<?php

namespace MartinPham\TypeGenerator\Helpers;

use Illuminate\Support\Facades\Schema as FacadeSchema;
use MartinPham\TypeGenerator\Definitions\Schemas\Schema;
use MartinPham\TypeGenerator\Definitions\Schemas\StringSchema;
use Illuminate\Support\Str;
use MartinPham\TypeGenerator\Definitions\Schemas\ObjectSchema;
use phpDocumentor\Reflection\DocBlock\Tags\Return_;
use phpDocumentor\Reflection\DocBlockFactory;
use ReflectionClass;
use ReflectionMethod;

class ModelHelper
{
    public const RELATION_TYPE = [
        'Illuminate\Database\Eloquent\Relations\HasOne' => 'single',
        'Illuminate\Database\Eloquent\Relations\BelongsTo' => 'single',
        'Illuminate\Database\Eloquent\Relations\MorphTo' => 'single',
        'Illuminate\Database\Eloquent\Relations\MorphOne' => 'single',

        'Illuminate\Database\Eloquent\Relations\HasOneThrough' => 'multiple',
        'Illuminate\Database\Eloquent\Relations\HasMany' => 'multiple',
        'Illuminate\Database\Eloquent\Relations\BelongsToMany' => 'multiple',
        'Illuminate\Database\Eloquent\Relations\HasManyThrough' => 'multiple',
        'Illuminate\Database\Eloquent\Relations\MorphMany' => 'multiple',
    ];

    public static function parseModel(string $classFullname, $spec, $nullable)
    {
        $instance = new $classFullname;
        $table = $instance->getTable();

        $connection = FacadeSchema::getConnection();
        $schemaBuilder = $connection->getSchemaBuilder();
        $columns = $schemaBuilder->getColumns($table);


        $classReflection = new ReflectionClass($classFullname);

        $hiddenProperty = $classReflection->getProperty('hidden');
        $hiddenProperty->setAccessible(true);
        $hidden = $hiddenProperty->getValue($instance);

        $castsProperty = $classReflection->getProperty('casts');
        $castsProperty->setAccessible(true);
        $casts = $castsProperty->getValue($instance);

        $properties = [];

        $typeMapping = [
            'string' => 'string',
            'text' => 'string',
            'longtext' => 'string',
            'mediumtext' => 'string',
            'varchar' => 'string',
            'char' => 'string',
            'integer' => 'int',
            'int' => 'int',
            'bigint' => 'int',
            'smallint' => 'int',
            'tinyint' => 'int',
            'decimal' => 'float',
            'double' => 'float',
            'float' => 'float',
            'boolean' => 'bool',
            'tinyint(1)' => 'bool',
            'date' => 'DateTime',
            'datetime' => 'DateTime',
            'timestamp' => 'DateTime',
            'time' => 'DateTime',
            'json' => 'array',
            'jsonb' => 'array',
        ];

        foreach ($columns as $column) {
            $columnName = $column['name'];

            if (in_array($columnName, $hidden)) {
                continue;
            }

            $columnType = $column['type_name'];
            $columnNullable = $column['nullable'];

            $columnType = explode(':', $columnType)[0];

            $columnCastedType = $casts[$columnName] ?? $columnType;
            $columnCastedType = explode(':', $columnCastedType)[0];

            $columnMappedType = $typeMapping[$columnCastedType] ?? $columnCastedType;

            switch ($columnMappedType) {
                case 'string':
                    $properties[$columnName] = new Schema(
                        type: 'string',
                        nullable: $columnNullable
                    );
                    break;
                case 'int':
                    $properties[$columnName] = new Schema(
                        type: 'integer',
                        nullable: $columnNullable
                    );
                    break;
                case 'float':
                    $properties[$columnName] = new Schema(
                        type: 'number',
                        nullable: $columnNullable
                    );
                    break;
                case 'bool':
                    $properties[$columnName] = new Schema(
                        type: 'boolean',
                        nullable: $columnNullable
                    );
                    break;
                case 'DateTime':
                    $properties[$columnName] = new StringSchema(
                        format: 'date-time',
                        nullable: $columnNullable
                    );
                    break;

                default:
                    throw new \Exception("Cannot understand model structure - $classFullname::$columnName (type $columnType -> casted: $columnCastedType -> mapped: $columnMappedType)");
            }
        }

        $methods = $classReflection->getMethods(ReflectionMethod::IS_PUBLIC);

        foreach ($methods as $method) {
            if (
                $method->isStatic() ||
                $method->getNumberOfParameters() > 0 ||
                Str::startsWith($method->getName(), ['get', 'set', 'scope', '__']) ||
                in_array($method->getName(), ['save', 'delete', 'update', 'create', 'find', 'where'])
            ) {
                continue;
            }

            $methodName = $method->getName();
            if (in_array($methodName, $hidden)) {
                continue;
            }

            $methodReturnType = $method->getReturnType();
            /** @var \ReflectionNamedType|null $methodReturnType */
            if ($methodReturnType !== null && isset(self::RELATION_TYPE[$methodReturnType->getName()])) {
                $methodReflection = $classReflection->getMethod($methodName);
                $methodDocs = $methodReflection->getDocComment();

                if ($methodDocs) {
                    $methodDocblock = DocBlockFactory::createInstance()->create($methodDocs);
                    $returnTags = $methodDocblock->getTagsByName('return');

                    /** @var Return_ $returnTag */
                    foreach ($returnTags as $returnTag) {
                        $schema = DocBlockHelper::parseTagType($returnTag->getType(), false, $spec, $classReflection);

                        $properties[$methodName] = $schema;
                    }
                }
            }
        }


        return new ObjectSchema(
            properties: $properties,
            nullable: $nullable
        );
    }
}
