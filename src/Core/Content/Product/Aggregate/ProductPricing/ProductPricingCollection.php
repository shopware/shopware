<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Aggregate\ProductPricing;

use Shopware\Core\Content\Product\Aggregate\ProductPrice\ProductPriceEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<ProductPriceEntity>
 */
#[Package('inventory')]
class ProductPricingCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return ProductPricingEntity::class;
    }
}
