<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Order\Aggregate\OrderTransactionCaptureRefundPosition;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<OrderTransactionCaptureRefundPositionEntity>
 */
#[Package('customer-order')]
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
