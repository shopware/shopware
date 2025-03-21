<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Order\Event;

use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Order\OrderException;
use Shopware\Core\Content\Flow\Dispatching\Action\SendMailAction;
use Shopware\Core\Content\MailTemplate\Exception\MailEventConfigurationException;
use Shopware\Core\Content\MailTemplate\Subscriber\MailSendSubscriberConfig;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\A11yRenderedDocumentAware;
use Shopware\Core\Framework\Event\CustomerAware;
use Shopware\Core\Framework\Event\EventData\EntityType;
use Shopware\Core\Framework\Event\EventData\EventDataCollection;
use Shopware\Core\Framework\Event\EventData\MailRecipientStruct;
use Shopware\Core\Framework\Event\FlowEventAware;
use Shopware\Core\Framework\Event\MailAware;
use Shopware\Core\Framework\Event\OrderAware;
use Shopware\Core\Framework\Event\SalesChannelAware;
use Shopware\Core\Framework\Log\Package;
use Symfony\Contracts\EventDispatcher\Event;

#[Package('checkout')]
class OrderStateMachineStateChangeEvent extends Event implements SalesChannelAware, OrderAware, MailAware, CustomerAware, A11yRenderedDocumentAware, FlowEventAware
{
    private ?MailRecipientStruct $mailRecipientStruct = null;

    public function __construct(
        private readonly string $name,
        private readonly OrderEntity $order,
        private readonly Context $context
    ) {
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
            $orderCustomer = $this->order->getOrderCustomer();
            if (!$orderCustomer) {
                throw new MailEventConfigurationException('Data for mailRecipientStruct not available.', self::class);
            }

            $this->mailRecipientStruct = new MailRecipientStruct([
                $orderCustomer->getEmail() => $orderCustomer->getFirstName() . ' ' . $orderCustomer->getLastName(),
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
        return $this->name;
    }

    public function getOrderId(): string
    {
        return $this->order->getId();
    }

    public function getCustomerId(): string
    {
        $orderCustomer = $this->order->getOrderCustomer();

        if (!$orderCustomer?->getCustomerId()) {
            throw OrderException::orderCustomerDeleted($this->order->getId());
        }

        return $orderCustomer->getCustomerId();
    }

    /**
     * @return array<string>
     */
    public function getA11yDocumentIds(): array
    {
        $extension = $this->context->getExtension(SendMailAction::MAIL_CONFIG_EXTENSION);
        if (!$extension instanceof MailSendSubscriberConfig) {
            return [];
        }

        return array_filter($extension->getDocumentIds());
    }
}
