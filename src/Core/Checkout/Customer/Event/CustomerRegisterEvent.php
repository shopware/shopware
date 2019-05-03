<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Event;

use Shopware\Core\Checkout\Customer\CustomerDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\BusinessEventInterface;
use Shopware\Core\Framework\Event\EventData\EntityType;
use Shopware\Core\Framework\Event\EventData\EventDataCollection;
use Symfony\Component\EventDispatcher\Event;

class CustomerRegisterEvent extends Event implements BusinessEventInterface
{
    public const EVENT_NAME = 'checkout.customer.register';

    /**
     * @var array
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

    public function __construct(Context $context, array $customer, string $salesChannelId)
    {
        $this->customer = $customer;
        $this->context = $context;
        $this->salesChannelId = $salesChannelId;
    }

    public function getName(): string
    {
        return self::EVENT_NAME;
    }

    public function getCustomer(): array
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

    public function getSalesChannelId(): string
    {
        return $this->salesChannelId;
    }
}
