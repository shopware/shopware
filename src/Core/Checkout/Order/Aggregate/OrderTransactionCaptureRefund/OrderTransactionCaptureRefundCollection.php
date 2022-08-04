<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Order\Aggregate\OrderTransactionCaptureRefund;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @method void                                     add(OrderTransactionCaptureRefundEntity $entity)
 * @method void                                     set(string $key, OrderTransactionCaptureRefundEntity $entity)
 * @method OrderTransactionCaptureRefundEntity[]    getIterator()
 * @method OrderTransactionCaptureRefundEntity[]    getElements()
 * @method OrderTransactionCaptureRefundEntity|null get(string $key)
 * @method OrderTransactionCaptureRefundEntity|null first()
 * @method OrderTransactionCaptureRefundEntity|null last()
 */
class OrderTransactionCaptureRefundCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'order_transaction_capture_refund_collection';
    }

    protected function getExpectedClass(): string
    {
        return OrderTransactionCaptureRefundEntity::class;
    }
}
