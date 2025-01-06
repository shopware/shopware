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
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Script\Execution\Awareness\SalesChannelContextAware;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Contracts\EventDispatcher\Event;

#[Package('checkout')]
class CheckoutOrderPlacedEvent extends Event implements SalesChannelAware, SalesChannelContextAware, OrderAware, MailAware, CustomerAware, CustomerGroupAware, FlowEventAware
{
    final public const EVENT_NAME = 'checkout.order.placed';

    /**
     * @deprecated tag:v6.7.0 - Parameter $context will be type of SalesChannelContext and readonly
     * @deprecated tag:v6.7.0 - Parameter $salesChannelId will be removed
     */
    public function __construct(
        private Context|SalesChannelContext $context,
        private readonly OrderEntity $order,
        private readonly string $salesChannelId = '',
        private ?MailRecipientStruct $mailRecipientStruct = null
    ) {
        if ($context instanceof Context) {
            Feature::triggerDeprecationOrThrow('v6.7.0.0', 'The parameter $context will be type of SalesChannelContext');

            if (!$salesChannelId) {
                Feature::throwException('v6.7.0.0', 'The parameter $salesChannelId is required when passing Context');
            }
        }
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
        /**
         * @deprecated tag:v6.7.0 - Will be removed
         */
        if ($this->context instanceof Context) {
            return $this->context;
        }

        return $this->context->getContext();
    }

    public function getSalesChannelContext(): SalesChannelContext
    {
        if ($this->context instanceof Context) {
            throw CartException::missingSalesChannelContext();
        }

        return $this->context;
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
        if ($this->context instanceof Context) {
            return $this->salesChannelId;
        }

        return $this->context->getSalesChannelId();
    }

    public function getCustomerId(): string
    {
        $customerId = $this->getOrder()->getOrderCustomer()?->getCustomerId();

        if (!$customerId) {
            throw CartException::orderCustomerDeleted($this->getOrderId());
        }

        return $customerId;
    }

    public function getCustomerGroupId(): string
    {
        /**
         * @deprecated tag:v6.7.0 - Will be removed
         */
        if ($this->context instanceof Context) {
            $customerGroupId = $this->getOrder()->getOrderCustomer()?->getCustomer()?->getGroupId();

            if (!$customerGroupId) {
                throw CartException::orderCustomerDeleted($this->getOrderId());
            }

            return $customerGroupId;
        }

        return $this->context->getCustomerGroupId();
    }
}
