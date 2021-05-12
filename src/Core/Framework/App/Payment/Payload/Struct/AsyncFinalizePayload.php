<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Payment\Payload\Struct;

use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Framework\Struct\CloneTrait;
use Shopware\Core\Framework\Struct\JsonSerializableTrait;

/**
 * @internal only for use by the app-system
 */
class AsyncFinalizePayload implements PaymentPayloadInterface
{
    use CloneTrait;
    use JsonSerializableTrait;

    protected Source $source;

    protected OrderTransactionEntity $orderTransaction;

    public function __construct(OrderTransactionEntity $orderTransaction)
    {
        $this->orderTransaction = $orderTransaction;
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
}
