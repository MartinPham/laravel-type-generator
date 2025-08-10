<?php
namespace MartinPham\TypeGenerator\Writers\OpenAPI;

class Info
{
    public function __construct(
        public string $title,
        public string $version,
    ) {}
}
