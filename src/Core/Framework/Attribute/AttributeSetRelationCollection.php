<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Attribute;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @method void                            add(AttributeSetRelationEntity $entity)
 * @method void                            set(string $key, AttributeSetRelationEntity $entity)
 * @method AttributeSetRelationEntity[]    getIterator()
 * @method AttributeSetRelationEntity[]    getElements()
 * @method AttributeSetRelationEntity|null get(string $key)
 * @method AttributeSetRelationEntity|null first()
 * @method AttributeSetRelationEntity|null last()
 */
class AttributeSetRelationCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return AttributeSetRelationEntity::class;
    }
}
