<?php declare(strict_types=1);

namespace Shopware\Checkout\Shipping\Aggregate\ShippingMethodTranslation\Event;

use Shopware\Framework\Context;
use Shopware\Checkout\Shipping\Aggregate\ShippingMethodTranslation\Collection\ShippingMethodTranslationBasicCollection;
use Shopware\Framework\Event\NestedEvent;

class ShippingMethodTranslationBasicLoadedEvent extends NestedEvent
{
    public const NAME = 'shipping_method_translation.basic.loaded';

    /**
     * @var Context
     */
    protected $context;

    /**
     * @var ShippingMethodTranslationBasicCollection
     */
    protected $shippingMethodTranslations;

    public function __construct(ShippingMethodTranslationBasicCollection $shippingMethodTranslations, Context $context)
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

    public function getShippingMethodTranslations(): ShippingMethodTranslationBasicCollection
    {
        return $this->shippingMethodTranslations;
    }
}
