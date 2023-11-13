<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Payment\Cart;

use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Payment\Cart\Recurring\RecurringDataStruct;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\CloneTrait;
use Shopware\Core\Framework\Struct\ExtendableInterface;
use Shopware\Core\Framework\Struct\ExtendableTrait;
use Shopware\Core\Framework\Struct\JsonSerializableTrait;

#[Package('checkout')]
class SyncPaymentTransactionStruct implements \JsonSerializable, ExtendableInterface
{
    use CloneTrait;
    use ExtendableTrait;
    use JsonSerializableTrait;

    /**
     * @deprecated tag:v6.6.0 - Will be strongly typed
     *
     * @var OrderTransactionEntity
     */
    protected $orderTransaction;

    /**
     * @deprecated tag:v6.6.0 - Will be strongly typed
     *
     * @var OrderEntity
     */
    protected $order;

    public function __construct(
        OrderTransactionEntity $orderTransaction,
        OrderEntity $order,
        protected ?RecurringDataStruct $recurring = null
    ) {
        $this->orderTransaction = $orderTransaction;
        $this->order = $order;
    }

    public function getOrderTransaction(): OrderTransactionEntity
    {
        return $this->orderTransaction;
    }

    public function getOrder(): OrderEntity
    {
        return $this->order;
    }

    public function getRecurring(): ?RecurringDataStruct
    {
        return $this->recurring;
    }

    public function isRecurring(): bool
    {
        return $this->recurring !== null;
    }
}
