<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Order\Event;

use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Order\OrderException;
use Shopware\Core\Content\Flow\Dispatching\Aware\OrderTransactionAware;
use Shopware\Core\Content\Flow\Dispatching\Storer\CustomerStorer;
use Shopware\Core\Content\Flow\Dispatching\Storer\MailStorer;
use Shopware\Core\Content\Flow\Dispatching\Storer\OrderStorer;
use Shopware\Core\Content\Flow\Dispatching\Storer\OrderTransactionStorer;
use Shopware\Core\Content\Flow\Dispatching\Storer\TimezoneStorer;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\CustomerAware;
use Shopware\Core\Framework\Event\EventData\EventDataCollection;
use Shopware\Core\Framework\Event\EventData\MailRecipientStruct;
use Shopware\Core\Framework\Event\FlowEventAware;
use Shopware\Core\Framework\Event\MailAware;
use Shopware\Core\Framework\Event\OrderAware;
use Shopware\Core\Framework\Event\SalesChannelAware;
use Shopware\Core\Framework\Log\Package;
use Symfony\Contracts\EventDispatcher\Event;

#[Package('checkout')]
class OrderPaymentMethodChangedEvent extends Event implements SalesChannelAware, OrderAware, CustomerAware, MailAware, OrderTransactionAware, FlowEventAware
{
    final public const EVENT_NAME = 'checkout.order.payment_method.changed';

    public function __construct(
        private readonly OrderEntity $order,
        private readonly OrderTransactionEntity $orderTransaction,
        private readonly Context $context,
        private readonly string $salesChannelId,
        private ?MailRecipientStruct $mailRecipientStruct = null
    ) {
    }

    public function getName(): string
    {
        return self::EVENT_NAME;
    }

    public function getOrder(): OrderEntity
    {
        return $this->order;
    }

    public function getOrderTransaction(): OrderTransactionEntity
    {
        return $this->orderTransaction;
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    public function getMailStruct(): MailRecipientStruct
    {
        if (!$this->mailRecipientStruct instanceof MailRecipientStruct) {
            $orderCustomer = $this->order->getOrderCustomer();
            if (!$orderCustomer) {
                throw OrderException::associationNotFound('orderCustomer');
            }

            $this->mailRecipientStruct = new MailRecipientStruct([
                $orderCustomer->getEmail() => $orderCustomer->getFirstName() . ' ' . $orderCustomer->getLastName(),
            ]);
        }

        return $this->mailRecipientStruct;
    }

    public function getSalesChannelId(): string
    {
        return $this->salesChannelId;
    }

    public function getOrderId(): string
    {
        return $this->order->getId();
    }

    public static function getAvailableData(): EventDataCollection
    {
        return (new EventDataCollection())
            ->merge(OrderStorer::getStoreData())
            ->merge(CustomerStorer::getStoreData())
            ->merge(MailStorer::getStoreData())
            ->merge(TimezoneStorer::getStoreData())
            ->merge(OrderTransactionStorer::getStoreData());
    }

    public function getCustomerId(): string
    {
        $orderCustomer = $this->order->getOrderCustomer();

        if (!$orderCustomer?->getCustomerId()) {
            throw OrderException::orderCustomerDeleted($this->order->getId());
        }

        return $orderCustomer->getCustomerId();
    }

    public function getOrderTransactionId(): string
    {
        return $this->orderTransaction->getId();
    }
}
