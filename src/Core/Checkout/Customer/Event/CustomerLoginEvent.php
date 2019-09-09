<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Event;

use Shopware\Core\Checkout\Customer\CustomerDefinition;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\BusinessEventInterface;
use Shopware\Core\Framework\Event\EventData\EntityType;
use Shopware\Core\Framework\Event\EventData\EventDataCollection;
use Shopware\Core\Framework\Event\EventData\ScalarValueType;
use Symfony\Contracts\EventDispatcher\Event;

class CustomerLoginEvent extends Event implements BusinessEventInterface
{
    public const EVENT_NAME = 'checkout.customer.login';

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
    private $contextToken;

    /**
     * @var string
     */
    private $salesChannelId;

    public function __construct(Context $context, CustomerEntity $customer, string $contextToken, string $salesChannelId)
    {
        $this->customer = $customer;
        $this->context = $context;
        $this->contextToken = $contextToken;
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

    public function getContextToken(): string
    {
        return $this->contextToken;
    }

    public function getSalesChannelId(): string
    {
        return $this->salesChannelId;
    }

    public static function getAvailableData(): EventDataCollection
    {
        return (new EventDataCollection())
            ->add('customer', new EntityType(CustomerDefinition::class))
            ->add('contextToken', new ScalarValueType(ScalarValueType::TYPE_STRING));
    }
}
