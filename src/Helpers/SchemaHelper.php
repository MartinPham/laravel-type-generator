<?php

namespace MartinPham\TypeGenerator\Helpers;

use MartinPham\TypeGenerator\Definitions\Schemas\OneOfSchema;

class SchemaHelper {
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

    public static function containsBinaryString($data): bool {
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
}
