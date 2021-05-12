<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Payment\Payload\Struct;

use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Struct\CloneTrait;
use Shopware\Core\Framework\Struct\JsonSerializableTrait;

/**
 * @internal only for use by the app-system
 */
class SyncPayPayload implements PaymentPayloadInterface
{
    use CloneTrait;
    use JsonSerializableTrait;

    protected Source $source;

    protected OrderTransactionEntity $orderTransaction;

    protected OrderEntity $order;

    public function __construct(OrderTransactionEntity $orderTransaction, OrderEntity $order)
    {
        $this->orderTransaction = $orderTransaction;
        $this->order = $order;
    }

    public function setSource(Source $source): void
    {
        $this->source = $source;
    }

    public function getSource(): Source
    {
        return $this->source;
    }

    public function getOrderTransaction(): OrderTransactionEntity
    {
        return $this->orderTransaction;
    }

    public function getOrder(): OrderEntity
    {
        return $this->order;
    }
}
