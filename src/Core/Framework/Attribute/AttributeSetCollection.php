<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Attribute;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @method void                    add(AttributeSetEntity $entity)
 * @method void                    set(string $key, AttributeSetEntity $entity)
 * @method AttributeSetEntity[]    getIterator()
 * @method AttributeSetEntity[]    getElements()
 * @method AttributeSetEntity|null get(string $key)
 * @method AttributeSetEntity|null first()
 * @method AttributeSetEntity|null last()
 */
class AttributeSetCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return AttributeSetEntity::class;
    }
}
