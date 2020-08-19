<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Order\Aggregate\OrderRefundPosition;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @method void                           add(OrderRefundPositionEntity $entity)
 * @method void                           set(string $key, OrderRefundPositionEntity $entity)
 * @method OrderRefundPositionEntity[]    getIterator()
 * @method OrderRefundPositionEntity[]    getElements()
 * @method OrderRefundPositionEntity|null get(string $key)
 * @method OrderRefundPositionEntity|null first()
 * @method OrderRefundPositionEntity|null last()
 */
class OrderRefundPositionCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'order_refund_position_collection';
    }

    protected function getExpectedClass(): string
    {
        return OrderRefundPositionEntity::class;
    }
}
