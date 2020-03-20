<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Promotion\Aggregate\PromotionDiscount;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @method void                         add(PromotionDiscountEntity $entity)
 * @method void                         set(string $key, PromotionDiscountEntity $entity)
 * @method PromotionDiscountEntity[]    getIterator()
 * @method PromotionDiscountEntity[]    getElements()
 * @method PromotionDiscountEntity|null get(string $key)
 * @method PromotionDiscountEntity|null first()
 * @method PromotionDiscountEntity|null last()
 */
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
