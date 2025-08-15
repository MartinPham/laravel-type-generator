<?php

namespace MartinPham\TypeGenerator\Definitions\Items;

use MartinPham\TypeGenerator\Definitions\Response;

class ResponseItem
{
    public function __construct(
        public string   $code,
        public Response $response,
    )
    {
    }
}
