<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Promotion\Aggregate\PromotionDiscount;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<PromotionDiscountEntity>
 */
#[Package('checkout')]
class PromotionDiscountCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'promotion_discount_collection';
    }

    protected function getExpectedClass(): string
    {
        return PromotionDiscountEntity::class;
    }
}
