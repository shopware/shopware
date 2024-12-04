<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Event;

use Shopware\Core\Checkout\Cart\CartException;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\CustomerAware;
use Shopware\Core\Framework\Event\CustomerGroupAware;
use Shopware\Core\Framework\Event\EventData\EntityType;
use Shopware\Core\Framework\Event\EventData\EventDataCollection;
use Shopware\Core\Framework\Event\EventData\MailRecipientStruct;
use Shopware\Core\Framework\Event\FlowEventAware;
use Shopware\Core\Framework\Event\MailAware;
use Shopware\Core\Framework\Event\OrderAware;
use Shopware\Core\Framework\Event\SalesChannelAware;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Script\Execution\Awareness\SalesChannelContextAware;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Contracts\EventDispatcher\Event;

#[Package('checkout')]
class CheckoutOrderPlacedEvent extends Event implements SalesChannelAware, SalesChannelContextAware, OrderAware, MailAware, CustomerAware, CustomerGroupAware, FlowEventAware
{
    final public const EVENT_NAME = 'checkout.order.placed';

    /**
     * @deprecated tag:v6.7.0 - Parameter $context will be typed as SalesChannelContext
     * @deprecated tag:v6.7.0 - Parameter $salesChannelId will be removed
     * @deprecated tag:v6.7.0 - Parameter $salesChannelContext will be removed
     */
    public function __construct(
        private readonly Context $context,
        private readonly OrderEntity $order,
        private readonly string $salesChannelId,
        private ?MailRecipientStruct $mailRecipientStruct = null,
        private readonly ?SalesChannelContext $salesChannelContext = null
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

    public function getOrderId(): string
    {
        return $this->order->getId();
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

    public function getSalesChannelContext(): SalesChannelContext
    {
        if ($this->salesChannelContext === null) {
            throw CartException::missingSalesChannelContext();
        }

        return $this->salesChannelContext;
    }

    public function getMailStruct(): MailRecipientStruct
    {
        if (!$this->mailRecipientStruct instanceof MailRecipientStruct) {
            $this->mailRecipientStruct = new MailRecipientStruct([
                $this->order->getOrderCustomer()?->getEmail() => $this->order->getOrderCustomer()?->getFirstName() . ' ' . $this->order->getOrderCustomer()?->getLastName(),
            ]);
        }

        return $this->mailRecipientStruct;
    }

    public function getSalesChannelId(): string
    {
        return $this->salesChannelId;
    }

    public function getCustomerId(): string
    {
        $customerId = $this->getOrder()->getOrderCustomer()?->getCustomerId();

        if (!$customerId && $this->salesChannelContext) {
            $customerId = $this->salesChannelContext->getCustomerId();
        }

        if (!$customerId) {
            throw CartException::orderCustomerDeleted($this->getOrderId());
        }

        return $customerId;
    }

    public function getCustomerGroupId(): string
    {
        $customerGroupId = $this->getOrder()->getOrderCustomer()?->getCustomer()?->getGroupId();

        if (!$customerGroupId && $this->salesChannelContext) {
            $customerGroupId = $this->salesChannelContext->getCustomerGroupId();
        }

        if (!$customerGroupId) {
            throw CartException::orderCustomerDeleted($this->getOrderId());
        }

        return $customerGroupId;
    }
}
