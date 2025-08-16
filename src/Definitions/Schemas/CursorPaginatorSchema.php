<?php

namespace MartinPham\TypeGenerator\Definitions\Schemas;

class CursorPaginatorSchema
{
    public string $type = 'object';
    public array $properties = [];

    public function __construct(
        $schema,
        bool $nullable = false
    )
    {
        /*
            'data' => $this->items->toArray(),
            'path' => $this->path(),
            'per_page' => $this->perPage(),
            'next_cursor' => $this->nextCursor()?->encode(),
            'next_page_url' => $this->nextPageUrl(),
            'prev_cursor' => $this->previousCursor()?->encode(),
            'prev_page_url' => $this->previousPageUrl(),
        */

        $this->properties = [
            'path' => new Schema(
                type: 'string'
            ),
            'per_page' => new Schema(
                type: 'integer'
            ),
            'next_cursor' => new Schema(
                type: 'string'
            ),
            'next_page_url' => new Schema(
                type: 'string'
            ),
            'prev_cursor' => new Schema(
                type: 'integer'
            ),
            'prev_page_url' => new Schema(
                type: 'string'
            ),
            'data' => new ArraySchema(
                items: $schema
            )
        ];


        if ($nullable) {
            $this->nullable = $nullable;
        }
    }
}
