<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Aggregate\ProductFeatureSet;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<ProductFeatureSetEntity>
 */
#[Package('inventory')]
class ProductFeatureSetCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return ProductFeatureSetEntity::class;
    }
}
