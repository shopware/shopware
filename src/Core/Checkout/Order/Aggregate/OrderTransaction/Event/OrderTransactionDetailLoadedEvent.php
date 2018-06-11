<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\Event;

use Shopware\Core\Framework\Context;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\Collection\OrderTransactionDetailCollection;
use Shopware\Core\Checkout\Payment\Event\PaymentMethodBasicLoadedEvent;
use Shopware\Core\Framework\Event\NestedEvent;
use Shopware\Core\Framework\Event\NestedEventCollection;

class OrderTransactionDetailLoadedEvent extends NestedEvent
{
    public const NAME = 'order_transaction.detail.loaded';

    /**
     * @var \Shopware\Core\Framework\Context
     */
    protected $context;

    /**
     * @var OrderTransactionDetailCollection
     */
    protected $orderTransactions;

    public function __construct(OrderTransactionDetailCollection $orderTransactions, Context $context)
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

    public function getOrderTransactions(): OrderTransactionDetailCollection
    {
        return $this->orderTransactions;
    }

    public function getEvents(): ?NestedEventCollection
    {
        $events = [];
        if ($this->orderTransactions->getPaymentMethods()->count() > 0) {
            $events[] = new PaymentMethodBasicLoadedEvent($this->orderTransactions->getPaymentMethods(), $this->context);
        }

        return new NestedEventCollection($events);
    }
}
