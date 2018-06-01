<?php declare(strict_types=1);

namespace Shopware\Checkout\Shipping\Aggregate\ShippingMethodTranslation\Event;

use Shopware\Framework\Context;
use Shopware\System\Language\Event\LanguageBasicLoadedEvent;
use Shopware\Checkout\Shipping\Aggregate\ShippingMethodTranslation\Collection\ShippingMethodTranslationDetailCollection;
use Shopware\Checkout\Shipping\Event\ShippingMethodBasicLoadedEvent;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\Event\NestedEventCollection;

class ShippingMethodTranslationDetailLoadedEvent extends NestedEvent
{
    public const NAME = 'shipping_method_translation.detail.loaded';

    /**
     * @var \Shopware\Framework\Context
     */
    protected $context;

    /**
     * @var ShippingMethodTranslationDetailCollection
     */
    protected $shippingMethodTranslations;

    public function __construct(ShippingMethodTranslationDetailCollection $shippingMethodTranslations, Context $context)
    {
        $this->context = $context;
        $this->shippingMethodTranslations = $shippingMethodTranslations;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): Context
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
