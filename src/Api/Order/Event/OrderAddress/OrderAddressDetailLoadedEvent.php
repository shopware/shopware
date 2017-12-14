<?php declare(strict_types=1);

namespace Shopware\Api\Order\Event\OrderAddress;

use Shopware\Api\Country\Event\Country\CountryBasicLoadedEvent;
use Shopware\Api\Country\Event\CountryState\CountryStateBasicLoadedEvent;
use Shopware\Api\Order\Collection\OrderAddressDetailCollection;
use Shopware\Api\Order\Event\Order\OrderBasicLoadedEvent;
use Shopware\Api\Order\Event\OrderDelivery\OrderDeliveryBasicLoadedEvent;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\Event\NestedEventCollection;

class OrderAddressDetailLoadedEvent extends NestedEvent
{
    const NAME = 'order_address.detail.loaded';

    /**
     * @var TranslationContext
     */
    protected $context;

    /**
     * @var OrderAddressDetailCollection
     */
    protected $orderAddresses;

    public function __construct(OrderAddressDetailCollection $orderAddresses, TranslationContext $context)
    {
        $this->context = $context;
        $this->orderAddresses = $orderAddresses;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): TranslationContext
    {
        return $this->context;
    }

    public function getOrderAddresses(): OrderAddressDetailCollection
    {
        return $this->orderAddresses;
    }

    public function getEvents(): ?NestedEventCollection
    {
        $events = [];
        if ($this->orderAddresses->getCountries()->count() > 0) {
            $events[] = new CountryBasicLoadedEvent($this->orderAddresses->getCountries(), $this->context);
        }
        if ($this->orderAddresses->getCountryStates()->count() > 0) {
            $events[] = new CountryStateBasicLoadedEvent($this->orderAddresses->getCountryStates(), $this->context);
        }
        if ($this->orderAddresses->getOrders()->count() > 0) {
            $events[] = new OrderBasicLoadedEvent($this->orderAddresses->getOrders(), $this->context);
        }
        if ($this->orderAddresses->getOrderDeliveries()->count() > 0) {
            $events[] = new OrderDeliveryBasicLoadedEvent($this->orderAddresses->getOrderDeliveries(), $this->context);
        }

        return new NestedEventCollection($events);
    }
}
