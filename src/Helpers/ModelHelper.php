<?php

namespace MartinPham\TypeGenerator\Helpers;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Schema as FacadeSchema;
use MartinPham\TypeGenerator\Definitions\Items\ComponentSchemaItem;
use MartinPham\TypeGenerator\Definitions\Schemas\ArraySchema;
use MartinPham\TypeGenerator\Definitions\Schemas\RefSchema;
use MartinPham\TypeGenerator\Definitions\Schemas\Schema;
use MartinPham\TypeGenerator\Definitions\Schemas\StringSchema;
use Illuminate\Support\Str;
use MartinPham\TypeGenerator\Definitions\Schemas\ObjectSchema;
use phpDocumentor\Reflection\DocBlock\Tags\Return_;
use phpDocumentor\Reflection\DocBlockFactory;
use phpDocumentor\Reflection\Types\Collection;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Property;
use PhpParser\NodeFinder;
use PhpParser\ParserFactory;
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

    public const RELATION_CALL_NAME = [
        'hasOne' => 'Illuminate\Database\Eloquent\Relations\HasOne',
        'hasMany' => 'Illuminate\Database\Eloquent\Relations\HasMany',
        'belongsTo' => 'Illuminate\Database\Eloquent\Relations\BelongsTo',
        'belongsToMany' => 'Illuminate\Database\Eloquent\Relations\BelongsToMany',
        'morphOne' => 'Illuminate\Database\Eloquent\Relations\MorphOne',
        'morphMany' => 'Illuminate\Database\Eloquent\Relations\MorphMany',
        'morphTo' => 'Illuminate\Database\Eloquent\Relations\MorphTo',
        'morphToMany' => 'Illuminate\Database\Eloquent\Relations\MorphToMany',
        'morphedByMany' => 'Illuminate\Database\Eloquent\Relations\MorphedByMany',
        'hasManyThrough' => 'Illuminate\Database\Eloquent\Relations\HasManyThrough',
        'hasOneThrough' => 'Illuminate\Database\Eloquent\Relations\HasOneThrough'
    ];

    public static function parseModel(string $classFullname, $spec, $nullable)
    {
        $classReflection = new ReflectionClass($classFullname);

        $table = null;
        $connection = null;
        $hidden = [];
        $casts = [];
        $relationships = [];

        CodeHelper::parseClassNodes(
            $classReflection,
            /** @var Property $property */
            function ($property) use (&$table, &$hidden, &$casts, &$connection) {
                foreach ($property->props as $prop) {
                    $propertyName = $prop->name->toString();
                    switch ($propertyName) {
                        case 'connection':
                            $connection = CodeHelper::extractStringValue($prop->default);
                            break;

                        case 'table':
                            $table = CodeHelper::extractStringValue($prop->default);
                            break;

                        case 'hidden':
                            $hidden = array_merge($hidden, CodeHelper::extractArrayValues($prop->default));
                            break;

                        case 'casts':
                            $casts = array_merge($casts, CodeHelper::extractAssocArrayValues($prop->default));
                            break;
                    }
                }
            },
            /** @var ClassMethod $method */
            function ($method, $methodReturnNodes) use (&$table, &$hidden, &$casts, &$connection, $classReflection, $spec, &$relationships) {
                $methodName = $method->name->toString();
                if (
                    $method->isStatic() ||
                    $method->isPrivate() ||
                    !in_array($methodName, ['casts', 'hidden', 'getCasts', 'getHidden', 'getTable', 'getConnectionName'])
                ) {
                    return;
                }

                foreach ($methodReturnNodes as $return) {
                    if ($methodName === 'getCasts' || $methodName === 'casts') {
                        $casts = array_merge($casts, CodeHelper::extractAssocArrayValues($return->expr));
                    }
                    else if ($methodName === 'getHidden' || $methodName === 'hidden') {
                        $hidden = array_merge($hidden, CodeHelper::extractArrayValues($return->expr));
                    }
                    else if ($methodName === 'getTable') {
                        $table = CodeHelper::extractStringValue($return->expr);
                    }
                    else if ($methodName === 'getConnectionName') {
                        $connection = CodeHelper::extractStringValue($return->expr);
                    }
                }
            }
        );

        if ($table === null) {
            $baseClassName = basename(str_replace('\\', '/', $classFullname));
            $table = Str::snake(Str::pluralStudly($baseClassName));
        }

        $dbConnection = ($connection === null) ? FacadeSchema::getConnection() : FacadeSchema::connection($connection)->getConnection();
        $schemaBuilder = $dbConnection->getSchemaBuilder();
        $columns = $schemaBuilder->getColumns($table);

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

        CodeHelper::parseClassNodes(
            $classReflection,
            /** @var Property $property */
            function ($property)  {
            },
            /** @var ClassMethod $method */
            function ($method, $methodReturnNodes) use ($hidden, $classReflection, $spec, &$relationships) {
                $methodName = $method->name->toString();

                if (
                    $method->isStatic() ||
                    $method->isPrivate() ||
                    count($method->params) > 0 ||
                    Str::startsWith($methodName, ['get', 'set', 'scope', '__'])
                ) {
                    return;
                }

                if (in_array($methodName, $hidden)) {
                    return;
                }

                $methodReflection = $classReflection->getMethod($methodName);;
                $methodDocs = $methodReflection->getDocComment();

                if ($methodDocs) {
                    $methodDocblock = DocBlockFactory::createInstance()->create($methodDocs);
                    $returnTags = $methodDocblock->getTagsByName('return');

                    /** @var Return_ $returnTag */
                    foreach ($returnTags as $returnTag) {
                        $returnTagType = $returnTag->getType();
                        if ($returnTagType instanceof Collection) {
                            $returnTagTypeClassname = $returnTagType->getFqsen()->getName();
                            $returnTagTypeClassFullname = ClassHelper::getClassFullname($returnTagTypeClassname, $classReflection);

                            if(isset(self::RELATION_TYPE[$returnTagTypeClassFullname])) {
                                $relationships[$methodName] = DocBlockHelper::parseTagType($returnTag->getType(), false, $classReflection, $spec);
                            }
                        }
                    }
                }

                if (!isset($relationships[$methodName])) {
                    foreach ($methodReturnNodes as $return) {
                        if ($return->expr instanceof MethodCall) {
                            $call = $return->expr;

                            if (!($call->var instanceof Variable && $call->var->name === 'this')) {
                                continue;
                            }

                            $callMethod = $call->name->toString();

                            if(!isset(self::RELATION_CALL_NAME[$callMethod])) {
                                continue;
                            }

                            $relation = self::RELATION_CALL_NAME[$callMethod];
                            $relationType = self::RELATION_TYPE[$relation];

                            foreach ($call->args as $index => $arg) {
                                $value = CodeHelper::extractArgumentValue($arg->value);
                                if ($index === 0) {
                                    $relatedModel = $value;
                                    $relatedModelFullClassname = ClassHelper::getClassFullname($relatedModel, $classReflection);
                                    $parts = explode('\\', $relatedModelFullClassname);
                                    $relatedModelClassname = $parts[count($parts) - 1];

                                    $spec->putComponentSchema($relatedModelClassname, function () use ($relatedModelClassname, $relatedModelFullClassname, $spec) {
                                        return new ComponentSchemaItem(
                                            id: $relatedModelClassname,
                                            schema: ClassHelper::parseClass($relatedModelFullClassname, $spec, false)
                                        );
                                    });

                                    if ($relationType === 'single') {
                                        $relationships[$methodName] = new RefSchema(
                                            ref: $relatedModelClassname
                                        );
                                    } else if ($relationType === 'multiple') {
                                        $relationships[$methodName] = new ArraySchema(
                                            items: new RefSchema(
                                                ref: $relatedModelClassname
                                            )
                                        );
                                    }

                                    break 2;
                                }
                            }

                            break;
                        }
                    }
                }

            }
        );


        return new ObjectSchema(
            properties: array_merge($properties, $relationships),
            nullable: $nullable
        );
    }
}
