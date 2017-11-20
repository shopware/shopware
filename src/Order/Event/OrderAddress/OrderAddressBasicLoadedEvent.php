<?php declare(strict_types=1);

namespace Shopware\Order\Event\OrderAddress;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Country\Event\Country\CountryBasicLoadedEvent;
use Shopware\Country\Event\CountryState\CountryStateBasicLoadedEvent;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\Event\NestedEventCollection;
use Shopware\Order\Collection\OrderAddressBasicCollection;

class OrderAddressBasicLoadedEvent extends NestedEvent
{
    const NAME = 'order_address.basic.loaded';

    /**
     * @var TranslationContext
     */
    protected $context;

    /**
     * @var OrderAddressBasicCollection
     */
    protected $orderAddresses;

    public function __construct(OrderAddressBasicCollection $orderAddresses, TranslationContext $context)
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
