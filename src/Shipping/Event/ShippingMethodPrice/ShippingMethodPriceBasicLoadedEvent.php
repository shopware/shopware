<?php declare(strict_types=1);

namespace Shopware\Shipping\Event\ShippingMethodPrice;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Shipping\Collection\ShippingMethodPriceBasicCollection;

class ShippingMethodPriceBasicLoadedEvent extends NestedEvent
{
    const NAME = 'shipping_method_price.basic.loaded';

    /**
     * @var TranslationContext
     */
    protected $context;

    /**
     * @var ShippingMethodPriceBasicCollection
     */
    protected $shippingMethodPrices;

    public function __construct(ShippingMethodPriceBasicCollection $shippingMethodPrices, TranslationContext $context)
    {
        $this->context = $context;
        $this->shippingMethodPrices = $shippingMethodPrices;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): TranslationContext
    {
        return $this->context;
    }

    public function getShippingMethodPrices(): ShippingMethodPriceBasicCollection
    {
        return $this->shippingMethodPrices;
    }
}
