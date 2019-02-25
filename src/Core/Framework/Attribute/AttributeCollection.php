<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Attribute;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @method void                 add(AttributeEntity $entity)
 * @method void                 set(string $key, AttributeEntity $entity)
 * @method AttributeEntity[]    getIterator()
 * @method AttributeEntity[]    getElements()
 * @method AttributeEntity|null get(string $key)
 * @method AttributeEntity|null first()
 * @method AttributeEntity|null last()
 */
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
