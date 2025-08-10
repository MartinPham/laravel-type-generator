<?php
namespace MartinPham\TypeGenerator\Definitions\Schemas;

#[\AllowDynamicProperties]
class RefSchema
{
    public function __construct(
        string $ref,
        bool $nullable = false
    ) {
        $this->{'$ref'} = '#/components/schemas/' . $ref;

        if ($nullable) {
            $this->nullable = $nullable;
        }
    }
}
