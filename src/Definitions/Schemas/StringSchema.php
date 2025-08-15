<?php

namespace MartinPham\TypeGenerator\Definitions\Schemas;

use AllowDynamicProperties;

#[AllowDynamicProperties]
class StringSchema
{
    public string $type = 'string';

    public function __construct(
        public string $format = "",
        bool          $nullable = false
    )
    {
        if ($nullable) {
            $this->nullable = $nullable;
        }
    }
}
