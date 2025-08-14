<?php

namespace MartinPham\TypeGenerator\Definitions\Schemas;

use MartinPham\TypeGenerator\Definitions\Items\PropertyItem;

class DataPaginatorSchema
{
    public string $type = 'object';
    public array $properties = [];

    public function __construct(
        $schema,
        bool $nullable = false
    )
    {
        $lengthAwareSchema = new LengthAwarePaginatorSchema($schema, $nullable);
        $links = $lengthAwareSchema->properties['links'];
        $data = $lengthAwareSchema->properties['data'];
        unset($lengthAwareSchema->properties['data']);
        unset($lengthAwareSchema->properties['links']);
        $simpleSchema = new PaginatorSchema($schema, $nullable);
        unset($simpleSchema->properties['data']);
        unset($simpleSchema->properties['links']);

        $this->properties = [
                'links' => $links,
                'data' => $data,
                'meta' => new OneOfSchema(
                    oneOf: [
                        new ObjectSchema(
                            properties: $lengthAwareSchema->properties
                        ),
                        new ObjectSchema(
                            properties: $simpleSchema->properties
                        )
                    ]
                )
            ];


        if ($nullable) {
            $this->nullable = $nullable;
        }
    }
}
