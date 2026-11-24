<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Order\Event;

use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionDefinition;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Content\Flow\Dispatching\Aware\OrderTransactionAware;
use Shopware\Core\Content\Flow\Dispatching\Aware\ScalarValuesAware;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\EventData\EntityType;
use Shopware\Core\Framework\Event\EventData\EventDataCollection;
use Shopware\Core\Framework\Event\EventData\ScalarValueType;
use Shopware\Core\Framework\Log\Package;

/**
 * @final
 */
#[Package('checkout')]
class OrderTransactionStateMachineStateChangeEvent extends OrderStateMachineStateChangeEvent implements OrderTransactionAware, ScalarValuesAware
{
    public function __construct(
        string $name,
        OrderEntity $order,
        private readonly OrderTransactionEntity $orderTransaction,
        Context $context
    ) {
        parent::__construct($name, $order, $context);
    }

    public static function getAvailableData(): EventDataCollection
    {
        return parent::getAvailableData()
            ->add(OrderTransactionAware::ORDER_TRANSACTION, new EntityType(OrderTransactionDefinition::class), [EventDataCollection::HIDDEN_FROM_WEBHOOK => true])
            ->add(OrderTransactionAware::ORDER_TRANSACTION_ID, new ScalarValueType(ScalarValueType::TYPE_STRING));
    }

    public function getOrderTransaction(): OrderTransactionEntity
    {
        return $this->orderTransaction;
    }

    public function getOrderTransactionId(): string
    {
        return $this->orderTransaction->getId();
    }

    public function getValues(): array
    {
        return [OrderTransactionAware::ORDER_TRANSACTION_ID => $this->getOrderTransactionId()];
    }
}
