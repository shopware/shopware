<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Order\Aggregate\OrderAddress\Event;

use Shopware\Core\Checkout\Order\Aggregate\OrderAddress\Collection\OrderAddressBasicCollection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\NestedEvent;
use Shopware\Core\Framework\Event\NestedEventCollection;
use Shopware\Core\System\Country\Aggregate\CountryState\Event\CountryStateBasicLoadedEvent;
use Shopware\Core\System\Country\Event\CountryBasicLoadedEvent;

class OrderAddressBasicLoadedEvent extends NestedEvent
{
    public const NAME = 'order_address.basic.loaded';

    /**
     * @var Context
     */
    protected $context;

    /**
     * @var OrderAddressBasicCollection
     */
    protected $orderAddresses;

    public function __construct(OrderAddressBasicCollection $orderAddresses, Context $context)
    {
        $this->context = $context;
        $this->orderAddresses = $orderAddresses;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    public function getOrderAddresses(): OrderAddressBasicCollection
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

        return new NestedEventCollection($events);
    }
}
