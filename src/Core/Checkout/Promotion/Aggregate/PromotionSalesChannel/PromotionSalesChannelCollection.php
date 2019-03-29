<?php
declare(strict_types=1);

namespace Shopware\Core\Checkout\Promotion\Aggregate\PromotionSalesChannel;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @method void                             add(PromotionSalesChannelEntity $entity)
 * @method void                             set(string $key, PromotionSalesChannelEntity $entity)
 * @method PromotionSalesChannelEntity[]    getIterator()
 * @method PromotionSalesChannelEntity[]    getElements()
 * @method PromotionSalesChannelEntity|null get(string $key)
 * @method PromotionSalesChannelEntity|null first()
 * @method PromotionSalesChannelEntity|null last()
 */
class PromotionSalesChannelCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return PromotionSalesChannelEntity::class;
    }
}
