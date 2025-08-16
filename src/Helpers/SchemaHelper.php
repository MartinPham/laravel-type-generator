<?php

namespace MartinPham\TypeGenerator\Helpers;

use MartinPham\TypeGenerator\Definitions\Schemas\OneOfSchema;

class SchemaHelper
{
    private array $_schemaResolvers = [];
    private array $_schemas = [];

    public static function mergeSchemas(array $schemas, $nullable = false)
    {
        if (count($schemas) === 1) {
            $ret = $schemas[0];
            if ($nullable) {
                $ret->nullable = $nullable;
            }

            return $ret;
        }

        return new OneOfSchema($schemas, $nullable);
    }

    public static function containsBinaryString($data): bool
    {
        if (isset($data->type, $data->format)
            && $data->type === 'string'
            && $data->format === 'binary') {
            return true;
        }

        if (is_object($data) || is_array($data)) {
            foreach ($data as $value) {
                if (self::containsBinaryString($value)) {
                    return true;
                }
            }
        }

        return false;
    }

    public function registerSchema($id, $resolver): static
    {
        if (!isset($this->_schemas[$id])) {
            $this->_schemaResolvers[$id] = $resolver;
        }
        return $this;
    }

    public function resolveAllSchemas(): array
    {
        while (count($this->_schemaResolvers) > 0) {
            foreach ($this->_schemaResolvers as $id => $resolver) {
                $result = $resolver();
                $this->_schemas[$id] = $result->schema;
                unset($this->_schemaResolvers[$id]);
            }
        }

        return $this->_schemas;
    }
}
