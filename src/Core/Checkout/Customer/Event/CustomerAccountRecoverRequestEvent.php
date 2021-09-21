<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Event;

use Shopware\Core\Checkout\Customer\Aggregate\CustomerRecovery\CustomerRecoveryDefinition;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerRecovery\CustomerRecoveryEntity;
use Shopware\Core\Checkout\Customer\CustomerDefinition;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\CustomerAware;
use Shopware\Core\Framework\Event\EventData\EntityType;
use Shopware\Core\Framework\Event\EventData\EventDataCollection;
use Shopware\Core\Framework\Event\EventData\MailRecipientStruct;
use Shopware\Core\Framework\Event\EventData\ScalarValueType;
use Shopware\Core\Framework\Event\MailActionInterface;
use Shopware\Core\Framework\Event\MailAware;
use Shopware\Core\Framework\Event\SalesChannelAware;
use Shopware\Core\Framework\Event\ShopwareSalesChannelEvent;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Contracts\EventDispatcher\Event;

class CustomerAccountRecoverRequestEvent extends Event implements MailActionInterface, SalesChannelAware, ShopwareSalesChannelEvent, CustomerAware, MailAware
{
    public const EVENT_NAME = 'customer.recovery.request';

    /**
     * @var CustomerRecoveryEntity
     */
    private $customerRecovery;

    /**
     * @var SalesChannelContext
     */
    private $salesChannelContext;

    /**
     * @var string
     */
    private $resetUrl;

    /**
     * @var string
     */
    private $shopName;

    /**
     * @var MailRecipientStruct
     */
    private $mailRecipientStruct;

    public function __construct(SalesChannelContext $salesChannelContext, CustomerRecoveryEntity $customerRecovery, string $resetUrl)
    {
        $this->salesChannelContext = $salesChannelContext;
        $this->customerRecovery = $customerRecovery;
        $this->resetUrl = $resetUrl;
        $this->shopName = $salesChannelContext->getSalesChannel()->getTranslation('name');
    }

    public function getName(): string
    {
        return self::EVENT_NAME;
    }

    public function getCustomerRecovery(): CustomerRecoveryEntity
    {
        return $this->customerRecovery;
    }

    public function getSalesChannelContext(): SalesChannelContext
    {
        return $this->salesChannelContext;
    }

    public function getContext(): Context
    {
        return $this->salesChannelContext->getContext();
    }

    public static function getAvailableData(): EventDataCollection
    {
        return (new EventDataCollection())
            ->add('customerRecovery', new EntityType(CustomerRecoveryDefinition::class))
            ->add('customer', new EntityType(CustomerDefinition::class))
            ->add('resetUrl', new ScalarValueType(ScalarValueType::TYPE_STRING))
            ->add('shopName', new ScalarValueType(ScalarValueType::TYPE_STRING));
    }

    public function getMailStruct(): MailRecipientStruct
    {
        if (!$this->mailRecipientStruct instanceof MailRecipientStruct) {
            $customer = $this->customerRecovery->getCustomer();

            $this->mailRecipientStruct = new MailRecipientStruct([
                $customer->getEmail() => $customer->getFirstName() . ' ' . $customer->getLastName(),
            ]);
        }

        return $this->mailRecipientStruct;
    }

    public function getSalesChannelId(): string
    {
        return $this->salesChannelContext->getSalesChannel()->getId();
    }

    public function getResetUrl(): string
    {
        return $this->resetUrl;
    }

    public function getShopName(): string
    {
        return $this->shopName;
    }

    public function getCustomer(): ?CustomerEntity
    {
        return $this->customerRecovery->getCustomer();
    }

    public function getCustomerId(): string
    {
        return $this->getCustomerRecovery()->getCustomerId();
    }
}
