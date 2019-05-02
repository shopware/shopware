<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Order\Event;

use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\BusinessEventInterface;
use Shopware\Core\Framework\Event\EventData\EntityType;
use Shopware\Core\Framework\Event\EventData\EventDataCollection;
use Shopware\Core\Framework\Event\EventData\MailRecipientStruct;
use Shopware\Core\Framework\Event\MailActionInterface;
use Symfony\Component\EventDispatcher\Event;

class OrderPlacedEvent extends Event implements BusinessEventInterface, MailActionInterface
{
    public const EVENT_NAME = 'checkout.order.done';

    /**
     * @var OrderEntity
     */
    public $order;

    /**
     * @var Context
     */
    private $context;

    /**
     * @var MailRecipientStruct|null
     */
    private $mailRecipientStruct;

    public function __construct(Context $context, OrderEntity $order, ?MailRecipientStruct $mailRecipientStruct = null)
    {
        $this->order = $order;
        $this->context = $context;
        $this->mailRecipientStruct = $mailRecipientStruct;
    }

    public function getName(): string
    {
        return self::EVENT_NAME;
    }

    public function getOrder(): OrderEntity
    {
        return $this->order;
    }

    public static function getAvailableData(): EventDataCollection
    {
        return (new EventDataCollection())
            ->add('order', new EntityType(OrderDefinition::class));
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    public function getMailStruct(): MailRecipientStruct
    {
        if ($this->mailRecipientStruct) {
            return $this->mailRecipientStruct;
        }

        return new MailRecipientStruct([$this->order->getOrderCustomer()->getEmail() => $this->order->getOrderCustomer()->getEmail()]);
    }
}
