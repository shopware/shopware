<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Promotion\Aggregate\PromotionSetGroup;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @method void                         add(PromotionSetGroupEntity $entity)
 * @method void                         set(string $key, PromotionSetGroupEntity $entity)
 * @method PromotionSetGroupEntity[]    getIterator()
 * @method PromotionSetGroupEntity[]    getElements()
 * @method PromotionSetGroupEntity|null get(string $key)
 * @method PromotionSetGroupEntity|null first()
 * @method PromotionSetGroupEntity|null last()
 */
class PromotionSetGroupCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return PromotionSetGroupEntity::class;
    }
}
