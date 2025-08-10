<?php
namespace MartinPham\TypeGenerator\Definitions\Items;

class ComponentSchemaItem
{
    public function __construct(
        public string $id,
        public $schema
    ) {}
}
