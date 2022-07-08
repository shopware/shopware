<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Order\Aggregate\OrderTransactionCaptureRefundPosition;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @method void                                             add(OrderTransactionCaptureRefundPositionEntity $entity)
 * @method void                                             set(string $key, OrderTransactionCaptureRefundPositionEntity $entity)
 * @method OrderTransactionCaptureRefundPositionEntity[]    getIterator()
 * @method OrderTransactionCaptureRefundPositionEntity[]    getElements()
 * @method OrderTransactionCaptureRefundPositionEntity|null get(string $key)
 * @method OrderTransactionCaptureRefundPositionEntity|null first()
 * @method OrderTransactionCaptureRefundPositionEntity|null last()
 */
class OrderTransactionCaptureRefundPositionCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'order_transaction_capture_refund_position_collection';
    }

    protected function getExpectedClass(): string
    {
        return OrderTransactionCaptureRefundPositionEntity::class;
    }
}
