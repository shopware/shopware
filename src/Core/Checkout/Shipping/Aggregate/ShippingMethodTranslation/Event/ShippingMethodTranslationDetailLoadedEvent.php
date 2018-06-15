<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Shipping\Aggregate\ShippingMethodTranslation\Event;

use Shopware\Core\Checkout\Shipping\Aggregate\ShippingMethodTranslation\Collection\ShippingMethodTranslationDetailCollection;
use Shopware\Core\Checkout\Shipping\Event\ShippingMethodBasicLoadedEvent;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\NestedEvent;
use Shopware\Core\Framework\Event\NestedEventCollection;
use Shopware\Core\System\Language\Event\LanguageBasicLoadedEvent;

class ShippingMethodTranslationDetailLoadedEvent extends NestedEvent
{
    public const NAME = 'shipping_method_translation.detail.loaded';

    /**
     * @var \Shopware\Core\Framework\Context
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
