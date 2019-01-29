<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Attribute;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

class AttributeCollection extends EntityCollection
{
    public function filterByType(string $type): self
    {
        return $this->filter(function (AttributeEntity $attribute) use ($type) {
            return $attribute->getType() === $type;
        });
    }

    protected function getExpectedClass(): string
    {
        return AttributeEntity::class;
    }
}
