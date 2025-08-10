<?php
namespace MartinPham\TypeGenerator\Definitions\Schemas;

#[\AllowDynamicProperties]
class Schema
{
    public function __construct(
        public string $type,
        bool $nullable = false
    ) {
        if ($nullable) {
            $this->nullable = $nullable;
        }
    }
}
