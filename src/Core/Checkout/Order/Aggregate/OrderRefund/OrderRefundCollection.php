<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Order\Aggregate\OrderRefund;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @method void                   add(OrderRefundEntity $entity)
 * @method void                   set(string $key, OrderRefundEntity $entity)
 * @method OrderRefundEntity[]    getIterator()
 * @method OrderRefundEntity[]    getElements()
 * @method OrderRefundEntity|null get(string $key)
 * @method OrderRefundEntity|null first()
 * @method OrderRefundEntity|null last()
 */
class OrderRefundCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'order_refund_collection';
    }

    protected function getExpectedClass(): string
    {
        return OrderRefundEntity::class;
    }
}
