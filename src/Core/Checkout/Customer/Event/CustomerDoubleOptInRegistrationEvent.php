<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Event;

use Shopware\Core\Checkout\Customer\CustomerDefinition;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\EventData\EntityType;
use Shopware\Core\Framework\Event\EventData\EventDataCollection;
use Shopware\Core\Framework\Event\EventData\MailRecipientStruct;
use Shopware\Core\Framework\Event\EventData\ScalarValueType;
use Shopware\Core\Framework\Event\MailActionInterface;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Contracts\EventDispatcher\Event;

class CustomerDoubleOptInRegistrationEvent extends Event implements MailActionInterface
{
    public const EVENT_NAME = 'checkout.customer.double_opt_in_registration';

    /**
     * @var CustomerEntity
     */
    private $customer;

    /**
     * @var SalesChannelContext
     */
    private $salesChannelContext;

    /**
     * @var string
     */
    private $confirmUrl;

    /**
     * @var MailRecipientStruct
     */
    private $mailRecipientStruct;

    public function __construct(
        CustomerEntity $customer,
        SalesChannelContext $salesChannelContext,
        string $confirmUrl
    ) {
        $this->customer = $customer;
        $this->salesChannelContext = $salesChannelContext;
        $this->confirmUrl = $confirmUrl;
    }

    public static function getAvailableData(): EventDataCollection
    {
        return (new EventDataCollection())
            ->add('customer', new EntityType(CustomerDefinition::class))
            ->add('confirmUrl', new ScalarValueType(ScalarValueType::TYPE_STRING));
    }

    public function getName(): string
    {
        return self::EVENT_NAME;
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

    public function getCustomer(): CustomerEntity
    {
        return $this->customer;
    }

    public function getConfirmUrl(): string
    {
        return $this->confirmUrl;
    }

    public function getSalesChannelId(): ?string
    {
        return $this->salesChannelContext->getSalesChannel()->getId();
    }

    public function getContext(): Context
    {
        return $this->salesChannelContext->getContext();
    }
}
