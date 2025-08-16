<?php

namespace MartinPham\TypeGenerator\Definitions\Schemas;

use AllowDynamicProperties;

#[AllowDynamicProperties]
class OneOfSchema
{
    public function __construct(
        public array $oneOf = [],
        bool         $nullable = false
    )
    {
        if ($nullable) {
            $this->nullable = $nullable;
        }
    }
}
