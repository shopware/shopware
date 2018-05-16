<?php declare(strict_types=1);

namespace Shopware\Checkout\Shipping\Aggregate\ShippingMethodTranslation\Event;

use Shopware\Application\Language\Event\Language\LanguageBasicLoadedEvent;
use Shopware\Checkout\Shipping\Aggregate\ShippingMethodTranslation\Collection\ShippingMethodTranslationDetailCollection;
use Shopware\Checkout\Shipping\Event\ShippingMethodBasicLoadedEvent;
use Shopware\Application\Context\Struct\ApplicationContext;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\Event\NestedEventCollection;

class ShippingMethodTranslationDetailLoadedEvent extends NestedEvent
{
    public const NAME = 'shipping_method_translation.detail.loaded';

    /**
     * @var ApplicationContext
     */
    protected $context;

    /**
     * @var ShippingMethodTranslationDetailCollection
     */
    protected $shippingMethodTranslations;

    public function __construct(ShippingMethodTranslationDetailCollection $shippingMethodTranslations, ApplicationContext $context)
    {
        $this->context = $context;
        $this->shippingMethodTranslations = $shippingMethodTranslations;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): ApplicationContext
    {
        return $this->context;
    }

    public function getShippingMethodTranslations(): ShippingMethodTranslationDetailCollection
    {
        return $this->shippingMethodTranslations;
    }

    public function getEvents(): ?NestedEventCollection
    {
        $events = [];
        if ($this->shippingMethodTranslations->getShippingMethods()->count() > 0) {
            $events[] = new ShippingMethodBasicLoadedEvent($this->shippingMethodTranslations->getShippingMethods(), $this->context);
        }
        if ($this->shippingMethodTranslations->getLanguages()->count() > 0) {
            $events[] = new LanguageBasicLoadedEvent($this->shippingMethodTranslations->getLanguages(), $this->context);
        }

        return new NestedEventCollection($events);
    }
}
