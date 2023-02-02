<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Payment\Payload\Struct;

use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Struct\CloneTrait;
use Shopware\Core\Framework\Struct\JsonSerializableTrait;
use Shopware\Core\Framework\Struct\Struct;

/**
 * @internal only for use by the app-system
 */
class CapturePayload implements PaymentPayloadInterface
{
    use CloneTrait;
    use JsonSerializableTrait;
    use RemoveAppTrait;

    protected Source $source;

    protected OrderTransactionEntity $orderTransaction;

    protected OrderEntity $order;

    protected Struct $preOrderPayment;

    public function __construct(OrderTransactionEntity $orderTransaction, OrderEntity $order, Struct $preOrderPayment)
    {
        $this->orderTransaction = $this->removeApp($orderTransaction);
        $this->order = $order;
        $this->preOrderPayment = $preOrderPayment;
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

    public function getPreOrderPayment(): Struct
    {
        return $this->preOrderPayment;
    }
}
