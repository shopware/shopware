<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Order\Event;

use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionDefinition;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\AssociationNotFoundException;
use Shopware\Core\Framework\Event\EventData\EntityType;
use Shopware\Core\Framework\Event\EventData\EventDataCollection;
use Shopware\Core\Framework\Event\EventData\MailRecipientStruct;
use Shopware\Core\Framework\Event\MailActionInterface;
use Shopware\Core\Framework\Event\SalesChannelAware;
use Symfony\Contracts\EventDispatcher\Event;

class OrderPaymentMethodChangedEvent extends Event implements MailActionInterface, SalesChannelAware
{
    public const EVENT_NAME = 'checkout.order.payment_method.changed';

    private OrderEntity $order;

    private OrderTransactionEntity $orderTransaction;

    private Context $context;

    private ?MailRecipientStruct $mailRecipientStruct;

    private string $salesChannelId;

    public function __construct(OrderEntity $order, OrderTransactionEntity $orderTransaction, Context $context, string $salesChannelId, ?MailRecipientStruct $mailRecipientStruct = null)
    {
        $this->order = $order;
        $this->orderTransaction = $orderTransaction;
        $this->context = $context;
        $this->mailRecipientStruct = $mailRecipientStruct;
        $this->salesChannelId = $salesChannelId;
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
            if ($orderCustomer === null) {
                throw new AssociationNotFoundException('orderCustomer');
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

    public static function getAvailableData(): EventDataCollection
    {
        return (new EventDataCollection())
            ->add('order', new EntityType(OrderDefinition::class))
            ->add('orderTransaction', new EntityType(OrderTransactionDefinition::class));
    }
}
