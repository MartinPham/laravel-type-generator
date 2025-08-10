<?php
namespace MartinPham\TypeGenerator\Writers;

use MartinPham\TypeGenerator\Definitions\Spec;

interface Writer {
    public function __construct(
        Spec $spec,
        array $options
    );
    public function output(): string;
}
