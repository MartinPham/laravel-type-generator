<?php
namespace MartinPham\TypeGenerator\Definitions\Items;

class ContentExampleItem
{
    public function __construct(
        public string $name,
        public $value,
    ) {}
}
