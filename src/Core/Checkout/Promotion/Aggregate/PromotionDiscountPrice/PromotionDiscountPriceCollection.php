<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Promotion\Aggregate\PromotionDiscountPrice;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<PromotionDiscountPriceEntity>
 */
#[Package('checkout')]
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
