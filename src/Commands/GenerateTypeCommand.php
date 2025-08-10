<?php

namespace MartinPham\TypeGenerator\Commands;

use Closure;
use Illuminate\Console\Command;

use Illuminate\Routing\Route;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route as FacadeRoute;
use MartinPham\TypeGenerator\Definitions\Schemas\ArraySchema;
use MartinPham\TypeGenerator\Definitions\Items\ContentItem;
use MartinPham\TypeGenerator\Definitions\Items\ComponentSchemaItem;
use MartinPham\TypeGenerator\Definitions\Items\ContentExampleItem;
use MartinPham\TypeGenerator\Definitions\Schemas\ObjectSchema;
use MartinPham\TypeGenerator\Definitions\Operation;
use MartinPham\TypeGenerator\Definitions\Parameter;
use MartinPham\TypeGenerator\Definitions\Items\PathItem;
use MartinPham\TypeGenerator\Definitions\Items\PropertyItem;
use MartinPham\TypeGenerator\Definitions\Response;
use MartinPham\TypeGenerator\Definitions\Items\ResponseItem;
use MartinPham\TypeGenerator\Definitions\Schemas\PaginatorSchema;
use MartinPham\TypeGenerator\Definitions\Schemas\RefSchema;
use MartinPham\TypeGenerator\Definitions\Schemas\Schema;
use MartinPham\TypeGenerator\Definitions\Spec;
use MartinPham\TypeGenerator\Helpers\ClassHelper;
use MartinPham\TypeGenerator\Helpers\DocBlockHelper;
use MartinPham\TypeGenerator\Helpers\SchemaHelper;
use phpDocumentor\Reflection\DocBlock\Tags\Param;
use phpDocumentor\Reflection\DocBlock\Tags\Return_;
use phpDocumentor\Reflection\DocBlock\Tags\Throws;
use phpDocumentor\Reflection\DocBlockFactory;
use ReflectionClass;
use ReflectionFunction;
use ReflectionUnionType;

class GenerateTypeCommand extends Command
{
    protected $signature   = 'type:generate';
    protected $description = 'Generates types';

    public function handle(): int
    {

        $this->info("Start");

        /** @var array<string,array<string,Route>> */
        $routes = [];

        /** @var array<int,Route> */
        $filteredRoutes = array_values(array_filter(
            FacadeRoute::getRoutes()->getRoutes(),
            fn(Route $route) => ! $this->strStartsWith($route->getName() ?? '', config('type-generator.ignored_route_names', [])),
        ));

        // $filteredRoutes = FacadeRoute::getRoutes()->getRoutes();

        // dd($filteredRoutes[62]);

        $specs = config('type-generator.route_prefixes', []);

        foreach ($specs as $_prefix => $config) {
            $specs[$_prefix]['spec'] = new Spec();
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
            foreach ($specs[$prefix]['routes'] as $route) {
                $uri = '/' . $route->uri;

                $this->info("> Discovered route " . $uri . '  ' . ($route->getPrefix() ?? ''));

                if (! key_exists($uri, $routes)) {
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

                    if (is_string($uses)) {
                        $classReflection = new ReflectionClass($route->getController());
                        $methodReflection = $classReflection->getMethod($route->getActionMethod());

                        if (!isset($route->action['as'])) {
                            $usesParts = explode('@', $uses);
                            $operationId = $route->action['prefix'] . '.' . $usesParts[1];
                        }
                    } elseif ($uses instanceof Closure) {
                        $classReflection = null;
                        $methodReflection = new ReflectionFunction($uses);
                    } else {
                        throw new \Exception('Unknown uses for route ' . $operationId);
                    }

                    $op = new Operation(
                        operationId: $operationId
                    );


                    $methodType  = $methodReflection->getReturnType();
                    $methodTypes = $methodType instanceof ReflectionUnionType ? $methodType->getTypes() : [$methodType];



                    $methodNullable = false;
                    $methodDocsSchemas = [];
                    $methodDocs = $methodReflection->getDocComment();
                    $methodDocblock = null;
                    $returnTags = null;
                    $throwsTags = null;
                    $paramTags = [];

                    if ($methodDocs) {
                        $methodDocblock = DocBlockFactory::createInstance()->create($methodDocs);

                        $op->summary = $methodDocblock->getSummary();
                        $op->description = $methodDocblock->getDescription();

                        $returnTags = $methodDocblock->getTagsByName('return');

                        /** @var Return_ $returnTag */
                        foreach ($returnTags as $returnTag) {
                            $methodDocsSchemas[] = DocBlockHelper::parseTagType($returnTag->getType(), false, $spec, $classReflection);
                        }

                        $_paramTags = $methodDocblock->getTagsByName('param');
                        /** @var Param $paramTag */
                        foreach ($_paramTags as $paramTag) {
                            $paramTags[$paramTag->getVariableName()] = $paramTag;
                        }

                        $throwsTags = $methodDocblock->getTagsByName('throws');
                    }

                    $parameters = array_map(function ($parameter) use ($paramTags) {
                        /** @var Param|null $paramTag */
                        $paramTag = $paramTags[$parameter] ?? null;

                        return new Parameter(
                            name: $parameter,
                            in: 'path',
                            required: true,
                            schema: new Schema(
                                type: 'string'
                            ),
                            description: $paramTag?->getDescription() ?? ''
                        );
                    }, $route->parameterNames());

                    $op->parameters = $parameters;


                    $this->info("> > > Recorded " . count($parameters) . " parameter(s)");

                    $this->info("> > > Collected " . count($methodDocsSchemas) . " method return(s) from DocBlock");

                    $methodSchemas = [];
                    /** @var \ReflectionNamedType|null $methodType */
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
                            } else if (is_subclass_of($methodTypeName, 'Illuminate\Contracts\Pagination\LengthAwarePaginator')) {
                                foreach ($methodDocsSchemas as $schema) {
                                    $methodSchemas[] = new PaginatorSchema(
                                        schema: $schema,
                                        nullable: $methodType->allowsNull()
                                    );
                                }

                                $this->info("> > > Native method return paginator => Applied " . count($methodSchemas) . " method return(s) from DocBlock");
                            } else if (class_exists($methodTypeName)) {
                                $methodAllowNull = $methodType->allowsNull();
                                if ($methodAllowNull) {
                                    $methodNullable = true;
                                }

                                $parts = explode('\\', $methodTypeName);
                                $className = end($parts);

                                $spec->putComponentSchema($className, function () use ($className, $methodTypeName, $spec, $methodAllowNull) {
                                    return new ComponentSchemaItem(
                                        id: $className,
                                        schema: ClassHelper::parseClass($methodTypeName, $spec, false)
                                    );
                                });

                                $methodSchemas[] = new RefSchema(
                                    ref: $className,
                                    nullable: $methodAllowNull
                                );

                                $this->info("> > > Native method return class $methodTypeName ($className) => Collected " . count($methodSchemas) . " method return(s) from Reflection");
                            } else {
                                throw new \Exception("Cannot understand route method return type {$route->getName()}-{$route->getActionMethod()}");
                            }
                        }
                    }

                    if (count($methodSchemas) === 0) {
                        $this->warn("> > > > Nothing can be found for route method return type {$route->getName()}-{$route->getActionMethod()}");
                        continue;
                    }

                    $methodSchema =  SchemaHelper::mergeSchemas($methodSchemas, $methodNullable);
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
                            $namespace = $classReflection->getNamespaceName();
                            $imports = ClassHelper::getClassImports($classReflection);

                            $classFullname = $name;

                            if (isset($imports[$name])) {
                                $classFullname = $imports[$name];
                            } else if (!class_exists($name)) {
                                if (!class_exists($namespace . '\\' . $name)) {
                                    throw new \Exception("Cannot locate class $name");
                                }
                                $classFullname = $namespace . '\\' . $name;
                            }

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
                        method: $method,
                        path: $uri,
                        operation: $op
                    ));
                }
            }

            $spec->resolveSchemas();


            $location  = $config['output'];
            $directory = dirname($location);

            if (! File::isDirectory($directory)) {
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

            $this->info("Output generated at {$location}");
        }








        return Command::SUCCESS;
    }

    /**
     * @param string|string[] $needles
     */
    protected function strStartsWith(string $haystack, string|array $needles): bool
    {
        foreach ((array) $needles as $needle) {
            if ('' !== (string) $needle && str_starts_with($haystack, $needle)) {
                return true;
            }
        }

        return false;
    }
}
