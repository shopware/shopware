<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Event;

use Shopware\Core\Checkout\Customer\CustomerDefinition;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Content\Flow\Dispatching\Aware\ScalarValuesAware;
use Shopware\Core\Content\MailTemplate\Exception\MailEventConfigurationException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\CustomerAware;
use Shopware\Core\Framework\Event\EventData\ArrayType;
use Shopware\Core\Framework\Event\EventData\EntityType;
use Shopware\Core\Framework\Event\EventData\EventDataCollection;
use Shopware\Core\Framework\Event\EventData\MailRecipientStruct;
use Shopware\Core\Framework\Event\EventData\ScalarValueType;
use Shopware\Core\Framework\Event\FlowEventAware;
use Shopware\Core\Framework\Event\MailAware;
use Shopware\Core\Framework\Log\Package;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Fires once per customer update — profile/email/password routes, Admin API, sync, and
 * import all converge on the customer write this event reports. changedFields names
 * the written fields; it is a delta hint, not a value diff.
 */
#[Package('checkout')]
class CustomerUpdatedEvent extends Event implements CustomerAware, ScalarValuesAware, FlowEventAware, MailAware
{
    final public const EVENT_NAME = 'checkout.customer.updated';

    private ?CustomerEntity $customer = null;

    /**
     * @param \Closure(): CustomerEntity $customerLoader
     * @param list<string> $changedFields
     */
    public function __construct(
        private readonly Context $context,
        private readonly string $customerId,
        private readonly \Closure $customerLoader,
        private readonly array $changedFields,
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
            ->add(CustomerAware::CUSTOMER_ID, new ScalarValueType(ScalarValueType::TYPE_STRING))
            ->add('changedFields', new ArrayType(new ScalarValueType(ScalarValueType::TYPE_STRING)));
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
     * @return list<string>
     */
    public function getChangedFields(): array
    {
        return $this->changedFields;
    }

    /**
     * @return array<string, scalar|array<mixed>|null>
     */
    public function getValues(): array
    {
        return [
            CustomerAware::CUSTOMER_ID => $this->customerId,
            'changedFields' => $this->changedFields,
        ];
    }
}
