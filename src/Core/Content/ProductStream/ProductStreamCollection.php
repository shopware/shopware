<?php declare(strict_types=1);

namespace Shopware\Core\Content\ProductStream;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

class ProductStreamCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return ProductStreamEntity::class;
    }
}
