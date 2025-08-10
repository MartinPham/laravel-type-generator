<?php
namespace MartinPham\TypeGenerator\Definitions\Items;

class PropertyItem
{
    public function __construct(
        public string $id,
        public $schema
    ) {}
}
