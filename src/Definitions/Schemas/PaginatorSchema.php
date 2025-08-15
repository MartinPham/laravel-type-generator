<?php

namespace MartinPham\TypeGenerator\Definitions\Schemas;

class PaginatorSchema
{
    public string $type = 'object';
    public array $properties = [];

    public function __construct(
        $schema,
        bool $nullable = false
    )
    {
        /*
            'current_page' => $this->currentPage(),
            'current_page_url' => $this->url($this->currentPage()),
            'data' => $this->items->toArray(),
            'first_page_url' => $this->url(1),
            'from' => $this->firstItem(),
            'next_page_url' => $this->nextPageUrl(),
            'path' => $this->path(),
            'per_page' => $this->perPage(),
            'prev_page_url' => $this->previousPageUrl(),
            'to' => $this->lastItem(),
        */

        $this->properties = [
            'current_page' => new Schema(
                type: 'integer'
            ),
            'current_page_url' => new Schema(
                type: 'string'
            ),
            'first_page_url' => new Schema(
                type: 'string'
            ),
            'from' => new Schema(
                type: 'integer'
            ),
            'next_page_url' => new Schema(
                type: 'string'
            ),
            'path' => new Schema(
                type: 'string'
            ),
            'per_page' => new Schema(
                type: 'integer'
            ),
            'prev_page_url' => new Schema(
                type: 'string'
            ),
            'to' => new Schema(
                type: 'integer'
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
