<?php declare(strict_types=1);

namespace Shopware\Api\Order\Event\OrderAddress;

use Shopware\Api\Country\Event\Country\CountryBasicLoadedEvent;
use Shopware\Api\Country\Event\CountryState\CountryStateBasicLoadedEvent;
use Shopware\Api\Order\Collection\OrderAddressBasicCollection;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\Event\NestedEventCollection;

class OrderAddressBasicLoadedEvent extends NestedEvent
{
    public const NAME = 'order_address.basic.loaded';

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
