<?php

namespace MartinPham\TypeGenerator\Definitions;

use AllowDynamicProperties;
use MartinPham\TypeGenerator\Definitions\Items\PathItem;

#[AllowDynamicProperties]
class Spec
{

    public function __construct()
    {
    }

    public function putPath(PathItem $item): static
    {
        $this->paths[$item->path][$item->method] = $item->operation;
        return $this;
    }

}
