<?php

namespace MartinPham\TypeGenerator\Commands;

use Closure;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route as FacadeRoute;
use MartinPham\TypeGenerator\Definitions\Items\ComponentSchemaItem;
use MartinPham\TypeGenerator\Definitions\Items\ContentExampleItem;
use MartinPham\TypeGenerator\Definitions\Items\ContentItem;
use MartinPham\TypeGenerator\Definitions\Items\PathItem;
use MartinPham\TypeGenerator\Definitions\Items\PropertyItem;
use MartinPham\TypeGenerator\Definitions\Items\RequestBodyItem;
use MartinPham\TypeGenerator\Definitions\Items\ResponseItem;
use MartinPham\TypeGenerator\Definitions\Operation;
use MartinPham\TypeGenerator\Definitions\Parameter;
use MartinPham\TypeGenerator\Definitions\Response;
use MartinPham\TypeGenerator\Definitions\Schemas\ArraySchema;
use MartinPham\TypeGenerator\Definitions\Schemas\CursorPaginatorSchema;
use MartinPham\TypeGenerator\Definitions\Schemas\CustomSchema;
use MartinPham\TypeGenerator\Definitions\Schemas\LengthAwarePaginatorSchema;
use MartinPham\TypeGenerator\Definitions\Schemas\ObjectSchema;
use MartinPham\TypeGenerator\Definitions\Schemas\PaginatorSchema;
use MartinPham\TypeGenerator\Definitions\Schemas\RefSchema;
use MartinPham\TypeGenerator\Definitions\Schemas\Schema;
use MartinPham\TypeGenerator\Definitions\Spec;
use MartinPham\TypeGenerator\Helpers\ClassHelper;
use MartinPham\TypeGenerator\Helpers\CodeHelper;
use MartinPham\TypeGenerator\Helpers\DocBlockHelper;
use MartinPham\TypeGenerator\Helpers\SchemaHelper;
use phpDocumentor\Reflection\DocBlock\Tags\Generic;
use phpDocumentor\Reflection\DocBlock\Tags\Param;
use phpDocumentor\Reflection\DocBlock\Tags\Return_;
use phpDocumentor\Reflection\DocBlock\Tags\Throws;
use phpDocumentor\Reflection\DocBlockFactory;
use phpDocumentor\Reflection\Fqsen;
use phpDocumentor\Reflection\PseudoTypes\ArrayShape;
use phpDocumentor\Reflection\Types\Collection;
use phpDocumentor\Reflection\Types\Object_;
use PhpParser\Node;
use PhpParser\NodeVisitorAbstract;
use ReflectionClass;
use ReflectionFunction;
use ReflectionNamedType;
use ReflectionUnionType;


class GenerateTypeCommand extends Command
{
    private const TYPE_MAP = [
        "int" => ["type" => "integer", "format" => "int32"],
        "float" => ["type" => "number", "format" => "float"],
        "string" => ["type" => "string"],
        "bool" => ["type" => "boolean"],
        "object" => ["type" => "object"],

        "array" => ["type" => "array", "items" => ["type" => "object"]],
        "iterable" => ["type" => "array", "items" => ["type" => "object"]],

        "mixed" => ["type" => "object"],
    ];
    protected $signature = 'type:generate';
    protected $description = 'Generates types';

    public function handle(): int
    {

        $this->info("Start");

        $routes = [];

        $filteredRoutes = array_values(array_filter(
            FacadeRoute::getRoutes()->getRoutes(),
            fn(Route $route) => !$this->strStartsWith($route->getName() ?? '', config('type-generator.ignored_route_names', [])),
        ));

        $specs = config('type-generator.route_prefixes', []);

        foreach ($specs as $_prefix => $config) {
            $specs[$_prefix]['spec'] = new Spec();
            $specs[$_prefix]['schemaHelper'] = new SchemaHelper();
            $specs[$_prefix]['routes'] = [];

            foreach ($filteredRoutes as $route) {
                [$type, $prefix] = explode(':', $_prefix);

                if ($type === 'uri') {
                    $routePrefix = $route->getPrefix() ?? '';

                    if (str_starts_with($routePrefix, $prefix)) {
                        $specs[$_prefix]['routes'][] = $route;
                    }
                } else if ($type === 'controller') {
                    $routeController = $route->action['controller'] ?? '';

                    if (str_starts_with($routeController, $prefix)) {
                        $specs[$_prefix]['routes'][] = $route;
                    }
                }
            }
        }

        foreach ($specs as $prefix => $config) {
            $spec = &$specs[$prefix]['spec'];
            $schemaHelper = &$specs[$prefix]['schemaHelper'];

            /** @var Route $route */
            foreach ($specs[$prefix]['routes'] as $route) {
                $uri = '/' . $route->uri;

                $this->info("> Discovered route " . $uri . '  ' . ($route->getPrefix() ?? ''));

                if (!key_exists($uri, $routes)) {
                    $routes[$uri] = [];
                }

                /** @var string $method */
                foreach ($route->methods as $method) {
                    $method = strtolower($method);
                    if (in_array($method, config('type-generator.ignored_methods', []), true)) {
                        continue;
                    }


                    $hasAuth = in_array('auth', $route->action['middleware']);
                    $uses = $route->action['uses'];
                    $operationId = $route->uri;

                    $this->info("> > Discovered route method [$method] $operationId");

                    if (isset($route->action['as'])) {
                        $operationId = $route->action['as'];
                    }


                    $methodNullable = false;
                    $methodDocsSchemas = [];
                    $methodDocblock = null;
                    $returnTags = null;
                    $throwsTags = null;
                    $classIdTags = [];
                    $idTags = [];
                    $classTagTags = [];
                    $tagTags = [];
                    $paramTags = [];

                    if (is_string($uses)) {
                        $classReflection = new ReflectionClass($route->getController());
                        $methodReflection = $classReflection->getMethod($route->getActionMethod());


                        $classDocs = $classReflection->getDocComment();

                        if ($classDocs) {

                            $classDocblock = DocBlockFactory::createInstance()->create($classDocs);
                            $_idsTags = $classDocblock->getTagsByName('id');
                            /** @var Generic $idTag */
                            foreach ($_idsTags as $idTag) {
                                $classIdTags[] = (string)$idTag->getDescription();
                            }

                            $_tagTags = $classDocblock->getTagsByName('tag');
                            /** @var Generic $idTag */
                            foreach ($_tagTags as $tagTag) {
                                $classTagTags[] = (string)$tagTag->getDescription();
                            }

                        }

                        if (!isset($route->action['as'])) {
                            $usesParts = explode('@', $uses);
                            $operationId = $route->action['prefix'] . '.' . $usesParts[1];
                        }
                    } elseif ($uses instanceof Closure) {
                        $classReflection = null;
                        $methodReflection = new ReflectionFunction($uses);
                    } else {
                        throw new Exception('Unknown uses for route ' . $operationId);
                    }

                    $op = new Operation(
                        operationId: $operationId
                    );

                    $parameters = [];

                    $methodType = $methodReflection->getReturnType();
                    $methodTypes = $methodType instanceof ReflectionUnionType ? $methodType->getTypes() : [$methodType];


                    $methodDocs = $methodReflection->getDocComment();


                    if ($methodDocs) {
                        $methodDocblock = DocBlockFactory::createInstance()->create($methodDocs);

                        $op->summary = $methodDocblock->getSummary();
                        $op->description = $methodDocblock->getDescription();

                        $returnTags = $methodDocblock->getTagsByName('return');

                        /** @var Return_ $returnTag */
                        foreach ($returnTags as $returnTag) {
                            $methodDocsSchemas[] = DocBlockHelper::parseTagType($returnTag->getType(), false, $classReflection, $schemaHelper);
                        }

                        $_paramTags = $methodDocblock->getTagsByName('param');
                        /** @var Param $paramTag */
                        foreach ($_paramTags as $paramTag) {
                            $paramTags[$paramTag->getVariableName()] = $paramTag;
                        }

                        $_idsTags = $methodDocblock->getTagsByName('id');
                        /** @var Generic $idTag */
                        foreach ($_idsTags as $idTag) {
                            $idTags[] = (string)$idTag->getDescription();
                        }

                        $_tagTags = $methodDocblock->getTagsByName('tag');
                        /** @var Generic $idTag */
                        foreach ($_tagTags as $tagTag) {
                            $tagTags[] = (string)$tagTag->getDescription();
                        }


                        $throwsTags = $methodDocblock->getTagsByName('throws');
                    }

                    if (count($idTags) > 0) {
                        $opId = $idTags[0];
                        if (count($classIdTags) > 0) {
                            $opId = $classIdTags[0] . '.' . $opId;
                        }
                        $op->operationId = $opId;
                    }

                    $tagTags = array_merge($classTagTags, $tagTags);
                    if (count($tagTags) > 0) {
                        $op->setTags($tagTags);
                    }

                    $requestParams = [];
                    $requestParamsNullable = false;

                    foreach ($route->signatureParameters() as $parameter) {
                        /** @var Param|null $paramTag */
                        $paramTag = $paramTags[$parameter->name] ?? null;

                        $type = $parameter->getType();
                        if ($type === null) {
                            $type = $paramTag?->getType() ?? null;

                            if ($type === null) {
                                $type = 'string';
                            }

                            $type = (string)$type;
                        }

                        if ($type instanceof ReflectionNamedType) {
                            $typeClass = $type->getName() ?? null;

                            if (ClassHelper::isKindOf($typeClass, FormRequest::class)) {
                                $schema = ClassHelper::parseClass($typeClass, $type->allowsNull(), true, $schemaHelper);

                                $requestParamsNullable = $type->allowsNull();

                                if (count($schema->properties) === 0) {
                                    $typeClassReflection = new ReflectionClass($typeClass);
                                    $properties = CodeHelper::parseClassCode($typeClassReflection, new class extends NodeVisitorAbstract {
                                        public array $results = [];

                                        public function enterNode(Node $node)
                                        {
                                            // Look for the rules() method
                                            if ($node instanceof Node\Stmt\ClassMethod && $node->name->toString() === 'rules') {
                                                // Look inside method statements
                                                foreach ($node->stmts as $stmt) {
                                                    if ($stmt instanceof Node\Stmt\Return_ && $stmt->expr instanceof Node\Expr\Array_) {
                                                        foreach ($stmt->expr->items as $item) {
                                                            if ($item->key instanceof Node\Scalar\String_) {
                                                                $this->results[] = $item->key->value;
                                                            }
                                                        }
                                                    }
                                                }
                                            }
                                        }
                                    });
                                    if (count($properties) > 0) {
                                        foreach ($properties as $property) {
                                            $schema->properties[$property] = new Schema(
                                                type: 'string',
                                            );
                                        }
                                    }
                                }

                                $requestParams[] = $schema;
                                continue;
                            } else if ($typeClass === Request::class) {
                                if ($paramTag !== null) {
                                    $paramTagType = $paramTag->getType();
                                    if ($paramTagType instanceof Collection) {
                                        $paramTagValueType = $paramTagType->getValueType();


                                        if ($paramTagValueType instanceof ArrayShape) {
                                            $requestParams[] = DocBlockHelper::parseTagType($paramTagValueType, $requestParamsNullable, $classReflection, $schemaHelper);
                                        }
                                    }
                                }
                                continue;
                            }
                        }

                        $parameters[$parameter->name] = new Parameter(
                            name: $parameter->name,
                            in: 'path',
                            required: true,
                            schema: new Schema(
                                type: 'string'
                            ),
                            description: $paramTag?->getDescription() ?? ''
                        );
                    }


                    $requestParamProperties = [];
                    foreach ($requestParams as $body) {
                        $requestParamProperties = array_merge($requestParamProperties, $body->properties);
                    }

                    if (in_array($method, ['post', 'put', 'patch'])) {
                        if (count($requestParamProperties) > 0) {
                            $detectFile = SchemaHelper::containsBinaryString($requestParamProperties);

                            if (!$detectFile) {
                                $op->putRequestBody(new RequestBodyItem(
                                    contentType: 'application/json',
                                    schema: new ObjectSchema(
                                        properties: $requestParamProperties,
                                        nullable: $requestParamsNullable
                                    )
                                ));
                            }

                            $op->putRequestBody(new RequestBodyItem(
                                contentType: 'application/x-www-form-urlencoded',
                                schema: new ObjectSchema(
                                    properties: $requestParamProperties,
                                    nullable: $requestParamsNullable
                                )
                            ));
                        }
                    } else {
                        foreach ($requestParamProperties as $property => $schema) {
                            $parameters[$property] = new Parameter(
                                name: $property,
                                in: 'query',
                                required: true,
                                schema: $schema,
                                description: ''
                            );
                        }
                    }


                    $this->info("> > > Collected " . count($methodDocsSchemas) . " method return(s) from DocBlock");


                    $op->putParameters(array_values($parameters));

                    $this->info("> > > Recorded " . count($parameters) . " parameter(s)");


                    $methodSchemas = [];
                    /** @var ReflectionNamedType|null $methodType */
                    foreach ($methodTypes as $methodType) {
                        if (in_array($methodType, config('type-generator.ignored_route_returns', []))) {
                            continue;
                        }
                        if ($methodType === null) {
                            $this->info("> > > No native method return");
                        } else {
                            $methodTypeName = $methodType->getName();


                            if (
                                (
                                    $methodTypeName === 'array'
                                    || $methodTypeName === 'iterable'
                                    || ClassHelper::isKindOf($methodTypeName, 'Illuminate\Support\Collection')
                                )
                                && count($methodDocsSchemas) > 0
                            ) {
                                $methodSchemas = $methodDocsSchemas;

                                $this->info("> > > Native method returns list data type => Applied " . count($methodSchemas) . " method return(s) from DocBlock");
                            }

                            else if (
                                ClassHelper::isKindOf($methodTypeName, 'Illuminate\Contracts\Pagination\Paginator')
                                || ClassHelper::isKindOf($methodTypeName, 'Illuminate\Contracts\Pagination\LengthAwarePaginator')
                                || ClassHelper::isKindOf($methodTypeName, 'Illuminate\Contracts\Pagination\CursorPaginator')
                            ) {
                                if (count($methodDocsSchemas) > 0) {
                                    $methodSchemas = $methodDocsSchemas;
                                    $this->info("> > > Native method return paginator => Applied " . count($methodSchemas) . " method return(s) from DocBlock");
                                } else {
                                    $methodAllowNull = $methodType->allowsNull();
                                    if ($methodAllowNull) {
                                        $methodNullable = true;
                                    }

                                    if (ClassHelper::isKindOf($methodTypeName, 'Illuminate\Contracts\Pagination\Paginator')) {
                                        $className = 'Paginator';
                                        $schema = new PaginatorSchema(
                                            schema: new ObjectSchema()
                                        );

                                        $schemaHelper->registerSchema($className, function () use ($className, $schema, $methodTypeName, $schemaHelper, $methodAllowNull) {
                                            return new ComponentSchemaItem(
                                                id: $className,
                                                schema: $schema
                                            );
                                        });

                                        $methodSchemas[] = new RefSchema(
                                            ref: $className,
                                            nullable: $methodAllowNull
                                        );
                                    } else if (ClassHelper::isKindOf($methodTypeName, 'Illuminate\Contracts\Pagination\LengthAwarePaginator')) {
                                        $className = 'LengthAwarePaginator';
                                        $schema = new LengthAwarePaginatorSchema(
                                            schema: new ObjectSchema()
                                        );;

                                        $schemaHelper->registerSchema($className, function () use ($className, $schema, $methodTypeName, $schemaHelper, $methodAllowNull) {
                                            return new ComponentSchemaItem(
                                                id: $className,
                                                schema: $schema
                                            );
                                        });

                                        $methodSchemas[] = new RefSchema(
                                            ref: $className,
                                            nullable: $methodAllowNull
                                        );
                                    } else if (ClassHelper::isKindOf($methodTypeName, 'Illuminate\Contracts\Pagination\CursorPaginator')) {
                                        $className = 'CursorPaginator';
                                        $schema = new CursorPaginatorSchema(
                                            schema: new ObjectSchema()
                                        );;

                                        $schemaHelper->registerSchema($className, function () use ($className, $schema, $methodTypeName, $schemaHelper, $methodAllowNull) {
                                            return new ComponentSchemaItem(
                                                id: $className,
                                                schema: $schema
                                            );
                                        });

                                        $methodSchemas[] = new RefSchema(
                                            ref: $className,
                                            nullable: $methodAllowNull
                                        );
                                    }


                                    $this->info("> > > Native method return paginator => Add method return(s) without object type");
                                }

                            }

                            else if (
                                ClassHelper::isKindOf($methodTypeName, 'TiMacDonald\JsonApi\JsonApiResource')
                                || ClassHelper::isKindOf($methodTypeName, 'TiMacDonald\JsonApi\JsonApiResourceCollection')
                                || ClassHelper::isKindOf($methodTypeName, 'Illuminate\Http\Resources\Json\ResourceCollection')
                                || ClassHelper::isKindOf($methodTypeName, 'Illuminate\Http\Resources\Json\JsonResource')
                            ) {

                                $methodAllowNull = $methodType->allowsNull();
                                if ($methodAllowNull) {
                                    $methodNullable = true;
                                }

                                $parts = explode('\\', $methodTypeName);
                                $name = end($parts);
                                $classFullname = ClassHelper::getClassFullname($name, $classReflection);

                                if (
                                    $classFullname === 'TiMacDonald\JsonApi\JsonApiResource'
                                    || $classFullname === 'Illuminate\Http\Resources\Json\JsonResource'
                                ) {
                                    if (count($methodDocsSchemas) > 0) {
                                        $methodSchemas = array_map(function ($schema) {
                                            return new ObjectSchema(
                                                properties: [
                                                    'data' => $schema
                                                ]
                                            );
                                        }, $methodDocsSchemas);


                                        $this->info("> > > Native method return generic json api resource => Applied " . count($methodSchemas) . " method return(s) with filters from DocBlock");
                                    } else {
                                        $methodSchemas[] = new ObjectSchema(
                                            properties: [
                                                'data' => new ObjectSchema()
                                            ]
                                        );
                                        $this->info("> > > Native method return generic json api resource => Added method return(s) without object type");
                                    }
                                } else if (
                                    $classFullname === 'TiMacDonald\JsonApi\JsonApiResourceCollection'
                                    || $classFullname === 'Illuminate\Http\Resources\Json\ResourceCollection'
                                ) {
                                    if (count($methodDocsSchemas) > 0) {
                                        $methodSchemas = array_map(function ($schema) {
                                            return new ObjectSchema(
                                                properties: [
                                                    'data' => $schema
                                                ]
                                            );
                                        }, $methodDocsSchemas);


                                        $this->info("> > > Native method return generic json api collection resource => Applied " . count($methodSchemas) . " method return(s) with filters from DocBlock");
                                    } else {
                                        $methodSchemas[] = new ObjectSchema(
                                            properties: [
                                                'data' => new ArraySchema(
                                                    items: new ObjectSchema()
                                                )
                                            ]
                                        );
                                        $this->info("> > > Native method return generic json api collection resource => Added method return(s) without object type");
                                    }
                                } else {

                                    $resourceClass = new ReflectionClass($classFullname);

                                    $schema = DocBlockHelper::parseTagType(new Object_(new Fqsen('\\' . $name)), $methodAllowNull, $resourceClass, $schemaHelper);

                                    $methodSchemas[] = new ObjectSchema(
                                        properties: [
                                            'data' => $schema
                                        ]
                                    );

                                    $this->info("> > > Native method return resource => try to parse the resource");
                                }

                            }

                            else if (class_exists($methodTypeName)) {
                                $methodAllowNull = $methodType->allowsNull();
                                if ($methodAllowNull) {
                                    $methodNullable = true;
                                }

                                $parts = explode('\\', $methodTypeName);
                                $className = end($parts);
                                $classFullname = ClassHelper::getClassFullname($className, $classReflection);

                                if (
                                    $classFullname === 'Illuminate\Http\Resources\Json\ResourceCollection'
                                    || $classFullname === 'Illuminate\Http\Resources\Json\JsonResource'
                                ) {
                                    $methodSchemas = array_map(function ($schema) {
                                        return new ObjectSchema(
                                            properties: [
                                                'data' => $schema
                                            ]
                                        );
                                    }, $methodDocsSchemas);
                                    $this->info("> > > Native method return generic resource => Applied " . count($methodSchemas) . " method return(s) with filters from DocBlock");
                                } else {

                                    $schemaHelper->registerSchema($className, function () use ($className, $methodTypeName, $schemaHelper, $methodAllowNull) {
                                        return new ComponentSchemaItem(
                                            id: $className,
                                            schema: ClassHelper::parseClass($methodTypeName, false, false, $schemaHelper)
                                        );
                                    });

                                    $methodSchemas[] = new RefSchema(
                                        ref: $className,
                                        nullable: $methodAllowNull
                                    );

                                    $this->info("> > > Native method return class $methodTypeName ($className) => Collected " . count($methodSchemas) . " method return(s) from Reflection");
                                }

                            }

                            else if (isset(self::TYPE_MAP[$methodTypeName])) {
                                $this->info("> > > Native method returns non-class data type");

                                $methodSchemas[] = new CustomSchema(self::TYPE_MAP[$methodTypeName]);
                            }

                            else if ($methodTypeName === 'void' || $methodTypeName === 'never') {
                                $this->info("> > > Native method doesnt return anything");
                            }

                            else {
                                throw new Exception("Cannot understand route method return type {$route->getName()}-{$route->getActionMethod()}");
                            }
                        }
                    }


                    $response = new Response(
                        description: $methodReflection->getName(),
                    );

                    if (count($methodSchemas) === 0) {
                        $this->warn("> > > > Nothing can be found for route method return type {$route->getName()}-{$route->getActionMethod()}");
                    } else {
                        $methodSchema = SchemaHelper::mergeSchemas($methodSchemas, $methodNullable);
                        $contentItem = new ContentItem(
                            contentType: 'application/json',
                            schema: $methodSchema
                        );

                        $response->putContent($contentItem);
                    }
                    $op->putResponse(new ResponseItem(
                        code: '200',
                        response: $response
                    ));

                    if ($throwsTags !== null && count($throwsTags) > 0) {
                        $errorContentItem = new ContentItem(
                            contentType: 'application/json',
                            schema: (new ObjectSchema())->putPropertyItem(new PropertyItem(
                                id: 'message',
                                schema: new Schema(
                                    type: 'string'
                                )
                            ))
                        );
                        /** @var Throws $throwsTag */
                        foreach ($throwsTags as $throwsTag) {
                            /** @var Object_ $throwsType */
                            $throwsType = $throwsTag->getType();
                            $name = $throwsType->getFqsen()->getName();

                            $classFullname = ClassHelper::getClassFullname($name, $classReflection);

                            $errorContentItem->putExample(new ContentExampleItem(
                                name: $throwsTag->getDescription(),
                                value: [
                                    'message' => $classFullname
                                ]
                            ));
                        }

                        $op->putResponse(new ResponseItem(
                            code: '500',
                            response: (new Response(
                                description: 'Exception',
                            ))->putContent($errorContentItem)
                        ));
                    }

                    if ($hasAuth) {
                        $op->putResponse(new ResponseItem(
                            code: '401',
                            response: (new Response(
                                description: 'Unauthenticated.',
                            ))->putContent((new ContentItem(
                                contentType: 'application/json',
                                schema: (new ObjectSchema())->putPropertyItem(new PropertyItem(
                                    id: 'message',
                                    schema: new Schema(
                                        type: 'string'
                                    )
                                ))
                            ))->putExample(new ContentExampleItem(
                                name: 'Unauthenticated.',
                                value: [
                                    'message' => 'Unauthenticated.'
                                ]
                            )))
                        ));
                    }

                    $spec->putPath(new PathItem(
                        path: $uri,
                        method: $method,
                        operation: $op
                    ));
                }
            }

            $spec->components['schemas'] = $schemaHelper->resolveAllSchemas();


            $location = $config['output'];
            $directory = dirname($location);

            if (!File::isDirectory($directory)) {
                File::makeDirectory(
                    path: dirname($location),
                    recursive: true,
                );
            }

            $writer = new $config['class'](
                spec: $spec,
                options: $config['options']
            );

            $output = $writer->output();

            File::put(
                $location,
                $output
            );

            $this->info("Output generated at $location");
        }


        return self::SUCCESS;
    }

    /**
     * @param string|string[] $needles
     */
    protected function strStartsWith(string $haystack, string|array $needles): bool
    {
        foreach ((array)$needles as $needle) {
            if ('' !== (string)$needle && str_starts_with($haystack, $needle)) {
                return true;
            }
        }

        return false;
    }
}
