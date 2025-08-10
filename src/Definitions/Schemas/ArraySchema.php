<?php
namespace MartinPham\TypeGenerator\Definitions\Schemas;

#[\AllowDynamicProperties]
class ArraySchema
{
    public string $type = 'array';

    public function __construct(
        public $items,
        bool $nullable = false
    ) {
        if ($nullable) {
            $this->nullable = $nullable;
        }
    }
}
