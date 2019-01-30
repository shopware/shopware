<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Attribute;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

class AttributeSetCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return AttributeSetEntity::class;
    }
}
