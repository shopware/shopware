<?php declare(strict_types=1);

namespace Shopware\OrderAddress\Event;

use Shopware\AreaCountry\Event\AreaCountryBasicLoadedEvent;
use Shopware\AreaCountryState\Event\AreaCountryStateBasicLoadedEvent;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\Event\NestedEventCollection;
use Shopware\OrderAddress\Struct\OrderAddressBasicCollection;

class OrderAddressBasicLoadedEvent extends NestedEvent
{
    const NAME = 'order_address.basic.loaded';

    /**
     * @var OrderAddressBasicCollection
     */
    protected $orderAddresses;

    /**
     * @var TranslationContext
     */
    protected $context;

    public function __construct(OrderAddressBasicCollection $orderAddresses, TranslationContext $context)
    {
        $this->orderAddresses = $orderAddresses;
        $this->context = $context;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getOrderAddresses(): OrderAddressBasicCollection
    {
        return $this->orderAddresses;
    }

    public function getContext(): TranslationContext
    {
        return $this->context;
    }

    public function getEvents(): ?NestedEventCollection
    {
        $events = [];
        if ($this->orderAddresses->getCountries()->count() > 0) {
            $events[] = new AreaCountryBasicLoadedEvent($this->orderAddresses->getCountries(), $this->context);
        }
        if ($this->orderAddresses->getStates()->count() > 0) {
            $events[] = new AreaCountryStateBasicLoadedEvent($this->orderAddresses->getStates(), $this->context);
        }

        return new NestedEventCollection($events);
    }
}
