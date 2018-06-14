<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Order\Aggregate\OrderTransactionState\Event;

use Shopware\Core\Checkout\Order\Aggregate\OrderTransactionState\Collection\OrderTransactionStateBasicCollection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\NestedEvent;

class OrderTransactionStateBasicLoadedEvent extends NestedEvent
{
    public const NAME = 'order_transaction_state.basic.loaded';

    /**
     * @var \Shopware\Core\Framework\Context
     */
    protected $context;

    /**
     * @var OrderTransactionStateBasicCollection
     */
    protected $orderTransactionStates;

    public function __construct(OrderTransactionStateBasicCollection $orderTransactionStates, Context $context)
    {
        $this->context = $context;
        $this->orderTransactionStates = $orderTransactionStates;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    public function getOrderTransactionStates(): OrderTransactionStateBasicCollection
    {
        return $this->orderTransactionStates;
    }
}
