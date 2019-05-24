<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Promotion\Aggregate\PromotionDiscountPrice;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @method void                              add(PromotionDiscountPriceEntity $entity)
 * @method void                              set(string $key, PromotionDiscountPriceEntity $entity)
 * @method PromotionDiscountPriceEntity[]    getIterator()
 * @method PromotionDiscountPriceEntity[]    getElements()
 * @method PromotionDiscountPriceEntity|null get(string $key)
 * @method PromotionDiscountPriceEntity|null first()
 * @method PromotionDiscountPriceEntity|null last()
 */
class PromotionDiscountPriceCollection extends EntityCollection
{
    public function getExpectedClass(): string
    {
        return PromotionDiscountPriceEntity::class;
    }
}
