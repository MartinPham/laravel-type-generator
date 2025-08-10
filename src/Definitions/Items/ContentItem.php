<?php
namespace MartinPham\TypeGenerator\Definitions\Items;

class ContentItem
{
    public function __construct(
        public string $contentType,
        public $schema,
        public array $examples = []
    ) {}


    public function putExample(ContentExampleItem $item)
    {
        $this->examples[$item->name]['value'] = $item->value;
        return $this;
    }
}
