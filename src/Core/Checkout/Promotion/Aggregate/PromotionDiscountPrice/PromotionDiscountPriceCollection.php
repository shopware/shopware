<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Promotion\Aggregate\PromotionDiscountPrice;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @extends EntityCollection<PromotionDiscountPriceEntity>
 */
class PromotionDiscountPriceCollection extends EntityCollection
{
    public function getExpectedClass(): string
    {
        return PromotionDiscountPriceEntity::class;
    }

    public function getApiAlias(): string
    {
        return 'promotion_discount_price_collection';
    }
}
