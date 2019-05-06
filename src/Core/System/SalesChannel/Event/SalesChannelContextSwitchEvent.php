<?php declare(strict_types=1);

namespace Shopware\Core\System\SalesChannel\Event;

use Shopware\Core\Checkout\Customer\CustomerDefinition;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\BusinessEventInterface;
use Shopware\Core\Framework\Event\EventData\EntityType;
use Shopware\Core\Framework\Event\EventData\EventDataCollection;
use Shopware\Core\Framework\Validation\DataBag\DataBag;
use Symfony\Component\EventDispatcher\Event;

class SalesChannelContextSwitchEvent extends Event implements BusinessEventInterface
{
    public const EVENT_NAME = 'system.sales-channel.context-switched';

    /**
     * @var Context
     */
    private $context;

    /**
     * @var DataBag
     */
    private $requestDataBag;

    /**
     * @var CustomerEntity|null
     */
    private $customer;

    /**
     * @var string
     */
    private $salesChannelId;

    public function __construct(Context $context, DataBag $requestDataBag, ?CustomerEntity $customer = null, string $salesChannelId)
    {
        $this->context = $context;
        $this->requestDataBag = $requestDataBag;
        $this->customer = $customer;
        $this->salesChannelId = $salesChannelId;
    }

    public function getName(): string
    {
        return self::EVENT_NAME;
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    public function getRequestDataBag(): DataBag
    {
        return $this->requestDataBag;
    }

    public function getCustomer(): ?CustomerEntity
    {
        return $this->customer;
    }

    public static function getAvailableData(): EventDataCollection
    {
        return (new EventDataCollection())
            ->add('customer', new EntityType(CustomerDefinition::class));
    }
}
