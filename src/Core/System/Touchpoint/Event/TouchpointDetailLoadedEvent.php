<?php declare(strict_types=1);

namespace Shopware\System\Touchpoint\Event;

use Shopware\System\Touchpoint\Collection\TouchpointDetailCollection;
use Shopware\Framework\Context;
use Shopware\Checkout\Payment\Event\PaymentMethodBasicLoadedEvent;
use Shopware\Checkout\Shipping\Event\ShippingMethodBasicLoadedEvent;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\Event\NestedEventCollection;
use Shopware\System\Country\Event\CountryBasicLoadedEvent;

class TouchpointDetailLoadedEvent extends NestedEvent
{
    public const NAME = 'touchpoint.detail.loaded';

    /**
     * @var Context
     */
    protected $context;

    /**
     * @var TouchpointDetailCollection
     */
    protected $touchpoints;

    public function __construct(TouchpointDetailCollection $touchpoints, Context $context)
    {
        $this->context = $context;
        $this->touchpoints = $touchpoints;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    public function getTouchpoints(): TouchpointDetailCollection
    {
        return $this->touchpoints;
    }

    public function getEvents(): ?NestedEventCollection
    {
        $events = [];
        if ($this->touchpoints->getPaymentMethods()->count() > 0) {
            $events[] = new PaymentMethodBasicLoadedEvent($this->touchpoints->getPaymentMethods(), $this->context);
        }
        if ($this->touchpoints->getShippingMethods()->count() > 0) {
            $events[] = new ShippingMethodBasicLoadedEvent($this->touchpoints->getShippingMethods(), $this->context);
        }
        if ($this->touchpoints->getCountries()->count() > 0) {
            $events[] = new CountryBasicLoadedEvent($this->touchpoints->getCountries(), $this->context);
        }

        return new NestedEventCollection($events);
    }
}
