<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Order\Aggregate\OrderTransactionCaptureRefund;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<OrderTransactionCaptureRefundEntity>
 */
#[Package('customer-order')]
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
