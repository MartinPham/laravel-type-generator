<?php

namespace MartinPham\TypeGenerator\Definitions\Items;

class RequestBodyItem
{
    public function __construct(
        public string $contentType,
        public        $schema
    )
    {
    }
}
