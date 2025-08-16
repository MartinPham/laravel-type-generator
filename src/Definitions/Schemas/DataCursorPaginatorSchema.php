<?php

namespace MartinPham\TypeGenerator\Definitions\Schemas;


class DataCursorPaginatorSchema
{
    public string $type = 'object';
    public array $properties = [];

    public function __construct(
        $schema,
        bool $nullable = false
    )
    {
        $cursorSchema = new CursorPaginatorSchema($schema, $nullable);
        $data = $cursorSchema->properties['data'];
        unset($cursorSchema->properties['data']);

        $this->properties = [
            'data' => $data,
            'meta' => new ObjectSchema(
                properties: $cursorSchema->properties
            ),
        ];


        if ($nullable) {
            $this->nullable = $nullable;
        }
    }
}
