<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Order\Event;

use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Content\MailTemplate\Exception\MailEventConfigurationException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\EventData\EntityType;
use Shopware\Core\Framework\Event\EventData\EventDataCollection;
use Shopware\Core\Framework\Event\EventData\MailRecipientStruct;
use Shopware\Core\Framework\Event\MailActionInterface;
use Shopware\Core\Framework\Event\SalesChannelAware;
use Symfony\Contracts\EventDispatcher\Event;

class OrderStateMachineStateChangeEvent extends Event implements MailActionInterface, SalesChannelAware
{
    /**
     * @var OrderEntity
     */
    private $order;

    /**
     * @deprecated tag:v6.4.0 - will be removed
     *
     * @var string|null
     */
    private $salesChannelId;

    /**
     * @var Context
     */
    private $context;

    /**
     * @var string
     */
    private $name;

    /**
     * @var MailRecipientStruct
     */
    private $mailRecipientStruct;

    /**
     * @deprecated tag:v6.4.0 - parameter $salesChannelId will be removed
     */
    public function __construct(string $eventName, OrderEntity $order, ?string $salesChannelId, Context $context)
    {
        $this->order = $order;
        $this->salesChannelId = $salesChannelId;
        $this->context = $context;
        $this->name = $eventName;
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

    public function getMailStruct(): MailRecipientStruct
    {
        if (!$this->mailRecipientStruct instanceof MailRecipientStruct) {
            if ($this->order->getOrderCustomer() === null) {
                throw new MailEventConfigurationException('Data for mailRecipientStruct not available.', self::class);
            }

            $this->mailRecipientStruct = new MailRecipientStruct([
                $this->order->getOrderCustomer()->getEmail() => $this->order->getOrderCustomer()->getFirstName() . ' ' . $this->order->getOrderCustomer()->getLastName(),
            ]);
        }

        return $this->mailRecipientStruct;
    }

    public function getSalesChannelId(): string
    {
        return $this->order->getSalesChannelId();
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    public function getName(): string
    {
        return (string) $this->name;
    }
}
