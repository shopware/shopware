<?php declare(strict_types=1);

namespace Shopware\ShippingMethod\Event;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\Event\NestedEventCollection;
use Shopware\ShippingMethod\Struct\ShippingMethodBasicCollection;

class ShippingMethodBasicLoadedEvent extends NestedEvent
{
    const NAME = 'shipping_method.basic.loaded';

    /**
     * @var ShippingMethodBasicCollection
     */
    protected $shippingMethods;

    /**
     * @var TranslationContext
     */
    protected $context;

    public function __construct(ShippingMethodBasicCollection $shippingMethods, TranslationContext $context)
    {
        $this->shippingMethods = $shippingMethods;
        $this->context = $context;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getShippingMethods(): ShippingMethodBasicCollection
    {
        return $this->shippingMethods;
    }

    public function getContext(): TranslationContext
    {
        return $this->context;
    }

    public function getEvents(): ?NestedEventCollection
    {
        $events = [];

        return new NestedEventCollection($events);
    }
}
