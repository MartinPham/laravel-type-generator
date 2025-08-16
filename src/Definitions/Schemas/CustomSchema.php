<?php

namespace MartinPham\TypeGenerator\Definitions\Schemas;

use AllowDynamicProperties;

#[AllowDynamicProperties]
class CustomSchema
{
    public function __construct(
        array $schema
    )
    {
        foreach ($schema as $key => $value) {
            $this->$key = $value;
        }
    }
}
