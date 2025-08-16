<?php

namespace MartinPham\TypeGenerator\Definitions;

class Parameter
{
    public function __construct(
        public string $name,
        public string $in,
        public bool   $required,
        public        $schema,
        public string $description = ''
    )
    {
    }
}
