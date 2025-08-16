<?php

namespace MartinPham\TypeGenerator\Definitions\Schemas;

use AllowDynamicProperties;
use MartinPham\TypeGenerator\Definitions\Items\PropertyItem;

#[AllowDynamicProperties]
class ObjectSchema
{
    public string $type = 'object';

    public function __construct(
        public array $properties = [],
        ?array $required = null,
        bool         $nullable = false
    )
    {
        if ($nullable) {
            $this->nullable = $nullable;
        }

        if ($required !== null) {
            $this->required = $required;
        }
    }

    public function putPropertyItem(PropertyItem $item): static
    {
        $this->properties[$item->id] = $item->schema;
        return $this;
    }
}
