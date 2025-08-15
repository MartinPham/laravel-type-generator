<?php

namespace MartinPham\TypeGenerator\Definitions;

use AllowDynamicProperties;
use MartinPham\TypeGenerator\Definitions\Items\RequestBodyItem;
use MartinPham\TypeGenerator\Definitions\Items\ResponseItem;

#[AllowDynamicProperties]
class Operation
{
    public function __construct(
        public string $operationId,
        public string $summary = '',
        public string $description = ''
    )
    {
    }

    public function putResponse(ResponseItem $item): static
    {
        $this->responses[$item->code] = $item->response;
        return $this;
    }

    public function putParameters(array $parameters): static
    {
        $this->parameters = $parameters;
        return $this;
    }

    public function putRequestBody(RequestBodyItem $item): static
    {
        $this->requestBody['content'][$item->contentType]['schema'] = $item->schema;
        return $this;
    }

    public function setTags(array $tags): static
    {
        $this->tags = $tags;
        return $this;
    }
}
