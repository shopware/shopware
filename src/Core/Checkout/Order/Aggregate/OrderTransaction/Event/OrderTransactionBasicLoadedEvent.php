<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\Event;

use Shopware\Core\Framework\Context;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\Collection\OrderTransactionBasicCollection;
use Shopware\Core\Framework\Event\NestedEvent;

class OrderTransactionBasicLoadedEvent extends NestedEvent
{
    public const NAME = 'order_transaction.basic.loaded';

    /**
     * @var \Shopware\Core\Framework\Context
     */
    protected $context;

    /**
     * @var OrderTransactionBasicCollection
     */
    protected $orderTransactions;

    public function __construct(OrderTransactionBasicCollection $orderTransactions, Context $context)
    {
        $this->context = $context;
        $this->orderTransactions = $orderTransactions;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    public function getOrderTransactions(): OrderTransactionBasicCollection
    {
        return $this->orderTransactions;
    }
}
