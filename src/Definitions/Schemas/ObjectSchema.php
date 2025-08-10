<?php
namespace MartinPham\TypeGenerator\Definitions\Schemas;

use MartinPham\TypeGenerator\Definitions\Items\PropertyItem;

#[\AllowDynamicProperties]
class ObjectSchema
{
    public string $type = 'object';

    public function __construct(
        public array $properties = [],
        bool $nullable = false
    ) {
        if ($nullable) {
            $this->nullable = $nullable;
        }
    }

    public function putPropertyItem(PropertyItem $item)
    {
        $this->properties[$item->id] = $item->schema;
        return $this;
    }
}
