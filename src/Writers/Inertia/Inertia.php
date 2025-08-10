<?php
namespace MartinPham\TypeGenerator\Writers\Inertia;

use MartinPham\TypeGenerator\Definitions\Spec;
use MartinPham\TypeGenerator\Writers\Writer;

class Inertia implements Writer
{
    public array $paths;
    public $components;

    public function __construct(
        Spec $spec,
        array $options
    ) {
        $this->paths = $spec->paths ?? [];
        $this->components = $spec->components ?? [];
    }


    private array $processedSchemas = [];
    private array $pendingSchemas = [];

    public function output(): string
    {
        $output = "// Generated TypeScript types from Spec specification\n\n";

        // Generate schema interfaces with proper dependency resolution
        if (isset($this->components['schemas'])) {
            $output .= "// Schema \n";

            // First pass: collect all schema names
            foreach ($this->components['schemas'] as $schemaName => $schema) {
                $this->pendingSchemas[] = $schemaName;
            }

            // Generate interfaces, resolving dependencies
            while (!empty($this->pendingSchemas)) {
                $initialCount = count($this->pendingSchemas);

                foreach ($this->pendingSchemas as $index => $schemaName) {
                    if ($this->canGenerateSchema($schemaName)) {
                        $output .= $this->generateInterface($schemaName, $this->components['schemas'][$schemaName]);
                        unset($this->pendingSchemas[$index]);
                        $this->pendingSchemas = array_values($this->pendingSchemas); // reindex
                        break; // Start over to maintain order
                    }
                }

                // Prevent infinite loop - generate remaining schemas even with circular refs
                if (count($this->pendingSchemas) === $initialCount) {
                    $schemaName = array_shift($this->pendingSchemas);
                    $output .= $this->generateInterface($schemaName, $this->components['schemas'][$schemaName]);
                }
            }
            $output .= "\n";
        }

        // Generate API response types
        if (isset($this->paths)) {
            $output .= "// Route & Response Types\n";
            foreach ($this->paths as $path => $methods) {
                foreach ($methods as $method => $operation) {
                    $output .= $this->generateApiTypes($path, $method, $operation);
                }
            }
        }

        return $output;
    }

    private function canGenerateSchema(string $schemaName): bool
    {
        if (in_array($schemaName, $this->processedSchemas)) {
            return false; // Already processed
        }

        $schema = $this->components['schemas'][$schemaName];
        $dependencies = $this->extractSchemaDependencies($schema);

        // Check if all dependencies are already processed
        foreach ($dependencies as $dependency) {
            if (!in_array($dependency, $this->processedSchemas) && $dependency !== $schemaName) {
                return false;
            }
        }

        return true;
    }

    private function extractSchemaDependencies($schema): array
    {
        $dependencies = [];
        $this->findReferences($schema, $dependencies);
        return array_unique($dependencies);
    }

    private function findReferences($schema, array &$dependencies): void
    {
        if (isset($schema->{'$ref'})) {
            $refName = basename($schema->{'$ref'});
            $dependencies[] = $refName;
            return;
        }

        if (isset($schema->oneOf)) {
            foreach ($schema->oneOf as $oneOfSchema) {
                $this->findReferences($oneOfSchema, $dependencies);
            }
        }

        if (isset($schema->items)) {
            $this->findReferences($schema->items, $dependencies);
        }

        if (isset($schema->properties)) {
            foreach ($schema->properties as $property) {
                $this->findReferences($property, $dependencies);
            }
        }
    }

    private function generateInterface(string $name, $schema): string
    {
        if (in_array($name, $this->processedSchemas)) {
            return '';
        }

        $this->processedSchemas[] = $name;
        $output = "export type {$name} = {\n";

        if (isset($schema->properties)) {
            foreach ($schema->properties as $propName => $propSchema) {
                $optional = @$propSchema->nullable ? '' : '?';
                $tsType = $this->convertToTypeScript($propSchema);
                $output .= "  {$propName}{$optional}: {$tsType};\n";
            }
        }

        $output .= "}\n\n";
        return $output;
    }

    private function convertToTypeScript($schema): string
    {
        // Handle nullable types
        $nullable = $schema->nullable ?? false;
        $nullSuffix = $nullable ? ' | null' : '';

        // Handle references
        if (isset($schema->{'$ref'})) {
            $refName = basename($schema->{'$ref'});
            // Ensure referenced schema is processed
            if (isset($this->components['schemas'][$refName]) && !in_array($refName, $this->processedSchemas)) {
                // Add to pending if not already there
                if (!in_array($refName, $this->pendingSchemas)) {
                    $this->pendingSchemas[] = $refName;
                }
            }
            return $refName . $nullSuffix;
        }

        // Handle oneOf
        if (isset($schema->oneOf)) {
            $types = array_map([$this, 'convertToTypeScript'], $schema->oneOf);
            return '(' . implode(' | ', $types) . ')' . $nullSuffix;
        }

        // Handle arrays
        if (isset($schema->type) && $schema->type === 'array') {
            if (isset($schema->items)) {
                $itemType = $this->convertToTypeScript($schema->items);
                return $itemType . '[]' . $nullSuffix;
            }
            return 'any[]' . $nullSuffix;
        }

        // Handle objects with inline properties
        if (isset($schema->type) && $schema->type === 'object') {
            if (isset($schema->properties)) {
                $props = [];
                foreach ($schema->properties as $propName => $propSchema) {
                    $propType = $this->convertToTypeScript($propSchema);
                    $isRequired = @$propSchema->nullable;
                    $optional = $isRequired ? '' : '?';
                    $props[] = "{$propName}{$optional}: {$propType}";
                }
                return '{ ' . implode('; ', $props) . ' }' . $nullSuffix;
            }
            return 'Record<string, any>' . $nullSuffix;
        }

        // Handle primitive types
        switch ($schema->type ?? 'any') {
            case 'string':
                if (isset($schema->format)) {
                    switch ($schema->format) {
                        case 'date-time':
                        case 'date':
                            return 'string' . $nullSuffix; // Could be Date if preferred
                        default:
                            return 'string' . $nullSuffix;
                    }
                }
                return 'string' . $nullSuffix;
            case 'integer':
                return 'number' . $nullSuffix;
            case 'number':
                return 'number' . $nullSuffix;
            case 'boolean':
                return 'boolean' . $nullSuffix;
            default:
                return 'any' . $nullSuffix;
        }
    }

    private function generateApiTypes(string $path, string $method, $operation): string
    {
        $operationId = $operation->operationId ?? $this->pathToOperationId($path, $method);
        $output = '';

        // Generate response types
        if (isset($operation->responses)) {
            foreach ($operation->responses as $statusCode => $response) {
                if ($statusCode == 200) {
                    $responseTypeName = str_replace('.', '_', $operationId);

                    if (isset($response->content['application/json']['schema'])) {
                        $schema = $response->content['application/json']['schema'];
                        $tsType = $this->convertToTypeScript($schema);
                        $description = isset($response->description) ? " // {$response->description}" : '';
                        $output .= "export type {$responseTypeName} = {$tsType};{$description}\n";
                    } else {
                        $description = isset($response->description) ? " // {$response->description}" : '';
                        $output .= "export type {$responseTypeName} = void;{$description}\n";
                    }

                    $output .= "export const route_{$responseTypeName} = '$operationId';{$description}\n";
                }
            }
            $output .= "\n";
        }

        return $output;
    }

    private function pathToOperationId(string $path, string $method): string
    {
        $path = preg_replace('/\{([^}]+)\}/', 'By$1', $path);
        $path = str_replace(['/', '-'], '_', $path);
        $path = trim($path, '_');
        return strtolower($method) . ucfirst(str_replace('_', '', ucwords($path, '_')));
    }
}
