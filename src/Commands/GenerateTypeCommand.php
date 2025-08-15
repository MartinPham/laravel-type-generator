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
use MartinPham\TypeGenerator\Definitions\Schemas\ObjectSchema;
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

                            if (is_subclass_of($typeClass, FormRequest::class)) {
                                $schema = ClassHelper::parseClass($typeClass, $type->allowsNull(), true, $schemaHelper);

                                $requestParamsNullable = $type->allowsNull();

                                if (count($schema->properties) === 0) {
//                                    $requestInstance = new $orgType;
//                                    $rules = $requestInstance->rules();
//
//                                    foreach ($rules as $field => $rule) {
//                                        $schema->properties[$field] = new Schema(
//                                            type: 'string',
//                                        );
//                                    }

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

                    $methodSchemas = [];
                    /** @var ReflectionNamedType|null $methodType */
                    foreach ($methodTypes as $methodType) {
                        if (in_array($methodType, config('type-generator.ignored_route_returns', []))) {
                            continue;
                        }
                        if ($methodType === null) {
                            $methodSchemas = $methodDocsSchemas;
                            $this->info("> > > No native method return => Applied " . count($methodSchemas) . " method return(s) from DocBlock");
                        } else {
                            $methodTypeName = $methodType->getName();


                            if ($methodTypeName === 'array' || is_subclass_of($methodTypeName, 'Illuminate\Support\Collection')) {
                                $methodSchemas = $methodDocsSchemas;

                                $this->info("> > > Native method returns list data type => Applied " . count($methodSchemas) . " method return(s) from DocBlock");
                            } else if (
                                is_subclass_of($methodTypeName, 'Illuminate\Contracts\Pagination\Paginator') || $methodTypeName === 'Illuminate\Contracts\Pagination\Paginator'
                                || is_subclass_of($methodTypeName, 'Illuminate\Contracts\Pagination\LengthAwarePaginator') || $methodTypeName === 'Illuminate\Pagination\LengthAwarePaginator'
                                || is_subclass_of($methodTypeName, 'Illuminate\Contracts\Pagination\CursorPaginator') || $methodTypeName === 'Illuminate\Contracts\Pagination\CursorPaginator'
                            ) {
                                $methodSchemas = $methodDocsSchemas;

                                $this->info("> > > Native method return paginator => Applied " . count($methodSchemas) . " method return(s) from DocBlock");
                            } else if (
                                is_subclass_of($methodTypeName, 'TiMacDonald\JsonApi\JsonApiResource')
                                || is_subclass_of($methodTypeName, 'TiMacDonald\JsonApi\JsonApiResourceCollection')
                                || is_subclass_of($methodTypeName, 'Illuminate\Http\Resources\Json\ResourceCollection') || $methodTypeName === 'Illuminate\Http\Resources\Json\ResourceCollection'
                                || is_subclass_of($methodTypeName, 'Illuminate\Http\Resources\Json\JsonResource') || $methodTypeName === 'Illuminate\Http\Resources\Json\JsonResource'
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
                                ) {
                                    $methodSchemas = array_map(function ($schema) {
                                        return new ObjectSchema(
                                            properties: [
                                                'data' => $schema
                                            ]
                                        );
                                    }, $methodDocsSchemas);


                                    $this->info("> > > Native method return generic json api collection resource => Applied " . count($methodSchemas) . " method return(s) with filters from DocBlock");
                                } elseif (
                                    $classFullname === 'TiMacDonald\JsonApi\JsonApiResourceCollection'
                                ) {
                                    $methodSchemas = array_map(function ($schema) {
                                        return new ObjectSchema(
                                            properties: [
                                                'data' => new ArraySchema(
                                                    items: $schema
                                                )
                                            ]
                                        );
                                    }, $methodDocsSchemas);

                                    $this->info("> > > Native method return generic json api resource => Applied " . count($methodSchemas) . " method return(s) with filters from DocBlock");
                                } else if (
                                    $classFullname === 'Illuminate\Http\Resources\Json\JsonResource'
                                    || $classFullname === 'Illuminate\Http\Resources\Json\ResourceCollection'
                                ) {
                                    $methodSchemas = array_map(function ($schema) {
                                        return new ObjectSchema(
                                            properties: [
                                                'data' => $schema
                                            ]
                                        );
                                    }, $methodDocsSchemas);


                                    $this->info("> > > Native method return generic json api resource => Applied " . count($methodSchemas) . " method return(s) with filters from DocBlock");
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

                            } else if (class_exists($methodTypeName)) {
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

                            } else {
                                throw new Exception("Cannot understand route method return type {$route->getName()}-{$route->getActionMethod()}");
                            }
                        }
                    }

                    if (count($methodSchemas) === 0) {
                        $this->warn("> > > > Nothing can be found for route method return type {$route->getName()}-{$route->getActionMethod()}");
                        continue;
                    }


                    $op->putParameters(array_values($parameters));

                    $this->info("> > > Recorded " . count($parameters) . " parameter(s)");


                    $methodSchema = SchemaHelper::mergeSchemas($methodSchemas, $methodNullable);
                    $contentItem = new ContentItem(
                        contentType: 'application/json',
                        schema: $methodSchema
                    );
                    $op->putResponse(new ResponseItem(
                        code: '200',
                        response: (new Response(
                            description: $methodReflection->getName(),
                        ))->putContent($contentItem)
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
