<?php declare(strict_types=1);

namespace Shopware\Application\Application\Event;

use Shopware\Application\Application\Collection\ApplicationDetailCollection;
use Shopware\Application\Context\Struct\ApplicationContext;
use Shopware\Checkout\Payment\Event\PaymentMethodBasicLoadedEvent;
use Shopware\Checkout\Shipping\Event\ShippingMethodBasicLoadedEvent;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\Event\NestedEventCollection;
use Shopware\System\Country\Event\CountryBasicLoadedEvent;

class ApplicationDetailLoadedEvent extends NestedEvent
{
    public const NAME = 'application.detail.loaded';

    /**
     * @var ApplicationContext
     */
    protected $context;

    /**
     * @var ApplicationDetailCollection
     */
    protected $applications;

    public function __construct(ApplicationDetailCollection $applications, ApplicationContext $context)
    {
        $this->context = $context;
        $this->applications = $applications;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): ApplicationContext
    {
        return $this->context;
    }

    public function getApplications(): ApplicationDetailCollection
    {
        return $this->applications;
    }

    public function getEvents(): ?NestedEventCollection
    {
        $events = [];
        if ($this->applications->getPaymentMethods()->count() > 0) {
            $events[] = new PaymentMethodBasicLoadedEvent($this->applications->getPaymentMethods(), $this->context);
        }
        if ($this->applications->getShippingMethods()->count() > 0) {
            $events[] = new ShippingMethodBasicLoadedEvent($this->applications->getShippingMethods(), $this->context);
        }
        if ($this->applications->getCountries()->count() > 0) {
            $events[] = new CountryBasicLoadedEvent($this->applications->getCountries(), $this->context);
        }

        return new NestedEventCollection($events);
    }
}
