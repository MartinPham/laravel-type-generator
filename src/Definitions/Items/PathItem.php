<?php
namespace MartinPham\TypeGenerator\Definitions\Items;

use MartinPham\TypeGenerator\Definitions\Operation;

class PathItem
{
    public function __construct(
        public string $path,
        public string $method,
        public Operation $operation,
    ) {}
}
