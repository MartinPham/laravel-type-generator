<?php

namespace MartinPham\TypeGenerator\Definitions\Schemas;

use MartinPham\TypeGenerator\Definitions\Items\PropertyItem;

class LengthAwarePaginatorSchema
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
            'first_page_url' => $this->url(1),
            'from' => $this->firstItem(),
            'last_page' => $this->lastPage(),
            'last_page_url' => $this->url($this->lastPage()),
            'links' => $this->linkCollection()->toArray(),
            'next_page_url' => $this->nextPageUrl(),
            'path' => $this->path(),
            'per_page' => $this->perPage(),
            'prev_page_url' => $this->previousPageUrl(),
            'to' => $this->lastItem(),
            'total' => $this->total(),
            'data' => $this->items->toArray(),
        */

        $this->properties = [
            'current_page' => new Schema(
                type: 'integer'
            ),
            'first_page_url' => new Schema(
                type: 'string'
            ),
            'from' => new Schema(
                type: 'integer'
            ),
            'last_page' => new Schema(
                type: 'integer'
            ),
            'last_page_url' => new Schema(
                type: 'string'
            ),
            'links' => new ArraySchema(
                items: (new ObjectSchema())
                    ->putPropertyItem(new PropertyItem(
                        id: 'url',
                        schema: new Schema(
                            type: 'string'
                        )
                    ))
                    ->putPropertyItem(new PropertyItem(
                        id: 'label',
                        schema: new Schema(
                            type: 'string'
                        )
                    ))
                    ->putPropertyItem(new PropertyItem(
                        id: 'active',
                        schema: new Schema(
                            type: 'boolean'
                        )
                    ))
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
            'total' => new Schema(
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
