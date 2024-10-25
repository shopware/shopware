<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\DataAbstractionLayer\fixture;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @internal
 *
 * @extends EntityCollection<AttributeEntity>
 */
class AttributeEntityCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return AttributeEntity::class;
    }
}
