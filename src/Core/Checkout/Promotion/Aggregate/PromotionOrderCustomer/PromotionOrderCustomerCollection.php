<?php
declare(strict_types=1);

namespace Shopware\Core\Checkout\Promotion\Aggregate\PromotionOrderCustomer;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @method void                              add(PromotionOrderCustomerEntity $entity)
 * @method void                              set(string $key, PromotionOrderCustomerEntity $entity)
 * @method PromotionOrderCustomerEntity[]    getIterator()
 * @method PromotionOrderCustomerEntity[]    getElements()
 * @method PromotionOrderCustomerEntity|null get(string $key)
 * @method PromotionOrderCustomerEntity|null first()
 * @method PromotionOrderCustomerEntity|null last()
 */
class PromotionOrderCustomerCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return PromotionOrderCustomerEntity::class;
    }
}
