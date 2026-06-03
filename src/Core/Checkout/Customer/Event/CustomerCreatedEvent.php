<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Event;

use Shopware\Core\Checkout\Customer\CustomerDefinition;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Content\Flow\Dispatching\Aware\ScalarValuesAware;
use Shopware\Core\Content\MailTemplate\Exception\MailEventConfigurationException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\CustomerAware;
use Shopware\Core\Framework\Event\EventData\EntityType;
use Shopware\Core\Framework\Event\EventData\EventDataCollection;
use Shopware\Core\Framework\Event\EventData\MailRecipientStruct;
use Shopware\Core\Framework\Event\EventData\ScalarValueType;
use Shopware\Core\Framework\Event\FlowEventAware;
use Shopware\Core\Framework\Event\MailAware;
use Shopware\Core\Framework\Log\Package;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Lifecycle fact: a customer row was inserted — Store API registration, Admin API,
 * sync, and import alike. Registration paths additionally fire the existing
 * checkout.customer.register / checkout.customer.guest_register events; this event
 * deliberately co-emits there. The customer entity is loaded lazily.
 */
#[Package('checkout')]
class CustomerCreatedEvent extends Event implements CustomerAware, ScalarValuesAware, FlowEventAware, MailAware
{
    final public const EVENT_NAME = 'checkout.customer.created';

    private ?CustomerEntity $customer = null;

    /**
     * @param \Closure(): CustomerEntity $customerLoader
     */
    public function __construct(
        private readonly Context $context,
        private readonly string $customerId,
        private readonly \Closure $customerLoader,
        private readonly ?string $salesChannelId = null
    ) {
    }

    public function getName(): string
    {
        return self::EVENT_NAME;
    }

    public static function getAvailableData(): EventDataCollection
    {
        return (new EventDataCollection())
            ->add(CustomerAware::CUSTOMER, new EntityType(CustomerDefinition::class))
            ->add(CustomerAware::CUSTOMER_ID, new ScalarValueType(ScalarValueType::TYPE_STRING));
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    public function getCustomerId(): string
    {
        return $this->customerId;
    }

    public function getCustomer(): CustomerEntity
    {
        return $this->customer ??= ($this->customerLoader)();
    }

    public function getSalesChannelId(): ?string
    {
        return $this->salesChannelId;
    }

    public function getMailStruct(): MailRecipientStruct
    {
        throw new MailEventConfigurationException('Data for mailRecipientStruct not available.', self::class);
    }

    /**
     * @return array<string, scalar|array<mixed>|null>
     */
    public function getValues(): array
    {
        return [
            CustomerAware::CUSTOMER_ID => $this->customerId,
        ];
    }
}
