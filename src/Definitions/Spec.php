<?php
namespace MartinPham\TypeGenerator\Definitions;

use MartinPham\TypeGenerator\Definitions\Items\PathItem;

#[\AllowDynamicProperties]
class Spec
{
    private array $_componentSchemaResolvers = [];

    public function __construct(
    ) {}

    public function putPath(PathItem $item)
    {
        $this->paths[$item->path][$item->method] = $item->operation;
        return $this;
    }

    public function putComponentSchema($id, $resolver)
    {
        if (!isset($this->components['schemas'][$id])) {
            $this->_componentSchemaResolvers[$id] = $resolver;
        }
        return $this;
    }

    public function resolveSchemas()
    {
        while (count($this->_componentSchemaResolvers) > 0) {
            foreach ($this->_componentSchemaResolvers as $id => $resolver) {
                $result = $resolver();
                $this->components['schemas'][$id] = $result->schema;
                unset($this->_componentSchemaResolvers[$id]);
            }
        }
    }
}
