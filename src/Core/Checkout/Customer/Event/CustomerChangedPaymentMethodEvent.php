<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Event;

use Shopware\Core\Checkout\Customer\CustomerDefinition;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\CustomerAware;
use Shopware\Core\Framework\Event\EventData\EntityType;
use Shopware\Core\Framework\Event\EventData\EventDataCollection;
use Shopware\Core\Framework\Event\EventData\MailRecipientStruct;
use Shopware\Core\Framework\Event\FlowEventAware;
use Shopware\Core\Framework\Event\MailAware;
use Shopware\Core\Framework\Event\SalesChannelAware;
use Shopware\Core\Framework\Event\ShopwareSalesChannelEvent;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * @deprecated tag:v6.7.0 - will be removed, customer has no default payment method anymore
 */
#[Package('checkout')]
class CustomerChangedPaymentMethodEvent extends Event implements SalesChannelAware, ShopwareSalesChannelEvent, CustomerAware, MailAware, FlowEventAware
{
    final public const EVENT_NAME = 'checkout.customer.changed-payment-method';

    public function __construct(
        private readonly SalesChannelContext $salesChannelContext,
        private readonly CustomerEntity $customer,
        private readonly RequestDataBag $requestDataBag
    ) {
    }

    public function getName(): string
    {
        Feature::triggerDeprecationOrThrow('v6.7.0.0', 'customer has no default payment method anymore');

        return self::EVENT_NAME;
    }

    public function getCustomer(): CustomerEntity
    {
        Feature::triggerDeprecationOrThrow('v6.7.0.0', 'customer has no default payment method anymore');

        return $this->customer;
    }

    public function getSalesChannelContext(): SalesChannelContext
    {
        Feature::triggerDeprecationOrThrow('v6.7.0.0', 'customer has no default payment method anymore');

        return $this->salesChannelContext;
    }

    public function getSalesChannelId(): string
    {
        Feature::triggerDeprecationOrThrow('v6.7.0.0', 'customer has no default payment method anymore');

        return $this->salesChannelContext->getSalesChannel()->getId();
    }

    public function getContext(): Context
    {
        Feature::triggerDeprecationOrThrow('v6.7.0.0', 'customer has no default payment method anymore');

        return $this->salesChannelContext->getContext();
    }

    public function getRequestDataBag(): RequestDataBag
    {
        Feature::triggerDeprecationOrThrow('v6.7.0.0', 'customer has no default payment method anymore');

        return $this->requestDataBag;
    }

    public static function getAvailableData(): EventDataCollection
    {
        Feature::triggerDeprecationOrThrow('v6.7.0.0', 'customer has no default payment method anymore');

        return (new EventDataCollection())
            ->add('customer', new EntityType(CustomerDefinition::class));
    }

    public function getCustomerId(): string
    {
        Feature::triggerDeprecationOrThrow('v6.7.0.0', 'customer has no default payment method anymore');

        return $this->getCustomer()->getId();
    }

    public function getMailStruct(): MailRecipientStruct
    {
        Feature::triggerDeprecationOrThrow('v6.7.0.0', 'customer has no default payment method anymore');

        return new MailRecipientStruct(
            [
                $this->customer->getEmail() => $this->customer->getFirstName() . ' ' . $this->customer->getLastName(),
            ]
        );
    }
}
