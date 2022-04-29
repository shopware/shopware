<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Order\Aggregate\OrderTransactionCapture;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

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
