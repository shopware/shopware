<?php declare(strict_types=1);

namespace Shopware\Api\Shipping\Event\ShippingMethodTranslation;

use Shopware\Api\Shipping\Collection\ShippingMethodTranslationDetailCollection;
use Shopware\Api\Shipping\Event\ShippingMethod\ShippingMethodBasicLoadedEvent;
use Shopware\Api\Shop\Event\Shop\ShopBasicLoadedEvent;
use Shopware\Context\Struct\ShopContext;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\Event\NestedEventCollection;

class ShippingMethodTranslationDetailLoadedEvent extends NestedEvent
{
    public const NAME = 'shipping_method_translation.detail.loaded';

    /**
     * @var ShopContext
     */
    protected $context;

    /**
     * @var ShippingMethodTranslationDetailCollection
     */
    protected $shippingMethodTranslations;

    public function __construct(ShippingMethodTranslationDetailCollection $shippingMethodTranslations, ShopContext $context)
    {
        $this->context = $context;
        $this->shippingMethodTranslations = $shippingMethodTranslations;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): ShopContext
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
            $events[] = new ShopBasicLoadedEvent($this->shippingMethodTranslations->getLanguages(), $this->context);
        }

        return new NestedEventCollection($events);
    }
}
