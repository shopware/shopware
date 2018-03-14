<?php declare(strict_types=1);

namespace Shopware\Api\Order\Event\OrderTransaction;

use Shopware\Api\Order\Collection\OrderTransactionDetailCollection;
use Shopware\Api\Payment\Event\PaymentMethod\PaymentMethodBasicLoadedEvent;
use Shopware\Context\Struct\ShopContext;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\Event\NestedEventCollection;

class OrderTransactionDetailLoadedEvent extends NestedEvent
{
    public const NAME = 'order_transaction.detail.loaded';

    /**
     * @var ShopContext
     */
    protected $context;

    /**
     * @var OrderTransactionDetailCollection
     */
    protected $orderTransactions;

    public function __construct(OrderTransactionDetailCollection $orderTransactions, ShopContext $context)
    {
        $this->context = $context;
        $this->orderTransactions = $orderTransactions;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): ShopContext
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
