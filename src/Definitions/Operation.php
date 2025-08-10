<?php
namespace MartinPham\TypeGenerator\Definitions;

use MartinPham\TypeGenerator\Definitions\Items\ResponseItem;

class Operation
{
    public function __construct(
        public string $operationId,
        public string $summary = '',
        public string $description = '',
        public array $parameters = [],
        public array $responses = []
    ) {}

    public function putResponse(ResponseItem $item)
    {
        $this->responses[$item->code] = $item->response;
        return $this;
    }
}
