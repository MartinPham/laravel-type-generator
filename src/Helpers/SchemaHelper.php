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
}
