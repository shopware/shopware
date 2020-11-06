<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Event;

use Shopware\Core\Checkout\Customer\CustomerDefinition;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\BusinessEventInterface;
use Shopware\Core\Framework\Event\EventData\EntityType;
use Shopware\Core\Framework\Event\EventData\EventDataCollection;
use Shopware\Core\Framework\Event\EventData\ScalarValueType;
use Shopware\Core\Framework\Event\SalesChannelAware;
use Shopware\Core\Framework\Event\ShopwareSalesChannelEvent;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Contracts\EventDispatcher\Event;

class CustomerLoginEvent extends Event implements BusinessEventInterface, SalesChannelAware, ShopwareSalesChannelEvent
{
    public const EVENT_NAME = 'checkout.customer.login';

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
    private $contextToken;

    public function __construct(SalesChannelContext $salesChannelContext, CustomerEntity $customer, string $contextToken)
    {
        $this->customer = $customer;
        $this->salesChannelContext = $salesChannelContext;
        $this->contextToken = $contextToken;
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

    public function getContextToken(): string
    {
        return $this->contextToken;
    }

    public function getSalesChannelId(): string
    {
        return $this->salesChannelContext->getSalesChannel()->getId();
    }

    public static function getAvailableData(): EventDataCollection
    {
        return (new EventDataCollection())
            ->add('customer', new EntityType(CustomerDefinition::class))
            ->add('contextToken', new ScalarValueType(ScalarValueType::TYPE_STRING));
    }
}
