<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Event;

use Shopware\Core\Checkout\Customer\CustomerDefinition;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Content\Flow\Dispatching\Aware\ScalarValuesAware;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\EventData\EntityType;
use Shopware\Core\Framework\Event\EventData\EventDataCollection;
use Shopware\Core\Framework\Event\EventData\MailRecipientStruct;
use Shopware\Core\Framework\Event\FlowEventAware;
use Shopware\Core\Framework\Event\MailAware;
use Shopware\Core\Framework\Event\ShopwareSalesChannelEvent;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Contracts\EventDispatcher\Event;

#[Package('checkout')]
class CustomerDeletedEvent extends Event implements ShopwareSalesChannelEvent, MailAware, ScalarValuesAware, FlowEventAware
{
    final public const EVENT_NAME = 'checkout.customer.deleted';

    private ?MailRecipientStruct $mailRecipientStruct = null;

    /**
     * @param array<string, mixed> $serializedCustomer
     */
    public function __construct(
        private readonly SalesChannelContext $salesChannelContext,
        private readonly CustomerEntity $customer,
        private readonly array $serializedCustomer = []
    ) {
    }

    public function getName(): string
    {
        return self::EVENT_NAME;
    }

    public function getCustomer(): CustomerEntity
    {
        return $this->customer;
    }

    public function getSalesChannelContext(): SalesChannelContext
    {
        return $this->salesChannelContext;
    }

    public function getContext(): Context
    {
        return $this->salesChannelContext->getContext();
    }

    public function getSalesChannelId(): ?string
    {
        return $this->salesChannelContext->getSalesChannel()->getId();
    }

    public function getMailStruct(): MailRecipientStruct
    {
        if (!$this->mailRecipientStruct instanceof MailRecipientStruct) {
            $this->mailRecipientStruct = new MailRecipientStruct([
                $this->customer->getEmail() => $this->customer->getFirstName() . ' ' . $this->customer->getLastName(),
            ]);
        }

        return $this->mailRecipientStruct;
    }

    public static function getAvailableData(): EventDataCollection
    {
        return (new EventDataCollection())
            ->add('customer', new EntityType(CustomerDefinition::class));
    }

    public function getValues(): array
    {
        if (Feature::isActive('v6.7.0.0')) {
            return [
                'customer' => $this->serializedCustomer,
            ];
        }

        return [
            'customer' => $this->serializedCustomer,
            'customerId' => $this->customer->getId(),
            'customerNumber' => $this->customer->getCustomerNumber(),
            'customerEmail' => $this->customer->getEmail(),
            'customerFirstName' => $this->customer->getFirstName(),
            'customerLastName' => $this->customer->getLastName(),
            'customerCompany' => $this->customer->getCompany(),
            'customerSalutationId' => $this->customer->getSalutationId(),
        ];
    }
}
