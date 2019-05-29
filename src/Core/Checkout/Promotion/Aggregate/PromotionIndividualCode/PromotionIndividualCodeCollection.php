<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Promotion\Aggregate\PromotionIndividualCode;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @method void                               add(PromotionIndividualCodeEntity $entity)
 * @method void                               set(string $key, PromotionIndividualCodeEntity $entity)
 * @method PromotionIndividualCodeEntity[]    getIterator()
 * @method PromotionIndividualCodeEntity[]    getElements()
 * @method PromotionIndividualCodeEntity|null get(string $key)
 * @method PromotionIndividualCodeEntity|null first()
 * @method PromotionIndividualCodeEntity|null last()
 */
class PromotionIndividualCodeCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return PromotionIndividualCodeEntity::class;
    }
}
