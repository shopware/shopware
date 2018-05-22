<?php declare(strict_types=1);

namespace Shopware\Checkout\Order\Aggregate\OrderAddress\Event;

use Shopware\Application\Context\Struct\ApplicationContext;
use Shopware\Checkout\Order\Aggregate\OrderAddress\Collection\OrderAddressBasicCollection;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\Event\NestedEventCollection;
use Shopware\System\Country\Aggregate\CountryState\Event\CountryStateBasicLoadedEvent;
use Shopware\System\Country\Event\CountryBasicLoadedEvent;

class OrderAddressBasicLoadedEvent extends NestedEvent
{
    public const NAME = 'order_address.basic.loaded';

    /**
     * @var ApplicationContext
     */
    protected $context;

    /**
     * @var OrderAddressBasicCollection
     */
    protected $orderAddresses;

    public function __construct(OrderAddressBasicCollection $orderAddresses, ApplicationContext $context)
    {
        $this->context = $context;
        $this->orderAddresses = $orderAddresses;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): ApplicationContext
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
