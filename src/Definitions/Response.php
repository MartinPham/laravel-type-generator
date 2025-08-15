<?php

namespace MartinPham\TypeGenerator\Definitions;

use MartinPham\TypeGenerator\Definitions\Items\ContentItem;

class Response
{
    public function __construct(
        public string $description,
        public array  $content = [],
    )
    {
    }

    public function putContent(ContentItem $item): static
    {
        $this->content[$item->contentType]['schema'] = $item->schema;

        if (count($item->examples) > 0) {
            $this->content[$item->contentType]['examples'] = $item->examples;
        }
        return $this;
    }
}
