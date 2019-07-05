<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Event;

use Shopware\Core\Checkout\Customer\CustomerDefinition;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\BusinessEventInterface;
use Shopware\Core\Framework\Event\EventData\EntityType;
use Shopware\Core\Framework\Event\EventData\EventDataCollection;
use Shopware\Core\Framework\Event\EventData\MailRecipientStruct;
use Shopware\Core\Framework\Event\MailActionInterface;
use Symfony\Contracts\EventDispatcher\Event;

class CustomerRegisterEvent extends Event implements BusinessEventInterface, MailActionInterface
{
    public const EVENT_NAME = 'checkout.customer.register';

    /**
     * @var CustomerEntity
     */
    private $customer;

    /**
     * @var Context
     */
    private $context;

    /**
     * @var string
     */
    private $salesChannelId;

    /**
     * @var MailRecipientStruct|null
     */
    private $mailRecipientStruct;

    public function __construct(Context $context, CustomerEntity $customer, string $salesChannelId)
    {
        $this->customer = $customer;
        $this->context = $context;
        $this->salesChannelId = $salesChannelId;
    }

    public function getName(): string
    {
        return self::EVENT_NAME;
    }

    public function getCustomer(): CustomerEntity
    {
        return $this->customer;
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    public static function getAvailableData(): EventDataCollection
    {
        return (new EventDataCollection())
            ->add('customer', new EntityType(CustomerDefinition::class));
    }

    public function getMailStruct(): MailRecipientStruct
    {
        if ($this->mailRecipientStruct) {
            return $this->mailRecipientStruct;
        }

        return new MailRecipientStruct(
            [
                $this->customer->getEmail() => $this->customer->getFirstName() . ' ' . $this->customer->getLastName(),
            ]
        );
    }

    public function getSalesChannelId(): ?string
    {
        return $this->salesChannelId;
    }
}
