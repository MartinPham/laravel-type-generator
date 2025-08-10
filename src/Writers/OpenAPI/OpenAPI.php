<?php

namespace MartinPham\TypeGenerator\Writers\OpenAPI;

use MartinPham\TypeGenerator\Definitions\Spec;
use MartinPham\TypeGenerator\Writers\Writer;

class OpenAPI implements Writer
{
    public string $openapi;
    public Info $info;
    public array $paths;
    public array $components;

    public function __construct(
        Spec $spec,
        array $options
    ) {
        $this->openapi = $options['openapi'];
        $this->info = new Info(
            title: $options['title'],
            version: $options['version']
        );
        $this->paths = $spec->paths ?? [];
        $this->components = $spec->components ?? [];
    }

    public function output(): string {
        return json_encode($this, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    }
}
