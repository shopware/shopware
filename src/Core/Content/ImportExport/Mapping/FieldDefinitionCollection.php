<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExport\Mapping;

use Shopware\Core\Framework\Struct\Collection;

class FieldDefinitionCollection extends Collection
{
    public static function fromArray(array $array): self
    {
        $collection = new self();
        foreach ($array as $properties) {
            $definition = new FieldDefinition();
            $definition->assign($properties);
            $collection->add($definition);
        }

        return $collection;
    }

    protected function getExpectedClass(): ?string
    {
        return FieldDefinition::class;
    }
}
