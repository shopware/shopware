<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Order\Aggregate\OrderTransactionCapture;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<OrderTransactionCaptureEntity>
 */
#[Package('customer-order')]
class OrderTransactionCaptureCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'order_transaction_capture_collection';
    }

    protected function getExpectedClass(): string
    {
        return OrderTransactionCaptureEntity::class;
    }
}
