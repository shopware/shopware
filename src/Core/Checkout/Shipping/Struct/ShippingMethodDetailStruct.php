<?php declare(strict_types=1);

namespace Shopware\Checkout\Shipping\Struct;

use Shopware\Checkout\Shipping\Aggregate\ShippingMethodTranslation\Collection\ShippingMethodTranslationBasicCollection;

class ShippingMethodDetailStruct extends ShippingMethodBasicStruct
{
    /**
     * @var ShippingMethodTranslationBasicCollection
     */
    protected $translations;

    public function __construct()
    {
        $this->translations = new ShippingMethodTranslationBasicCollection();
    }

    public function getTranslations(): ShippingMethodTranslationBasicCollection
    {
        return $this->translations;
    }

    public function setTranslations(ShippingMethodTranslationBasicCollection $translations): void
    {
        $this->translations = $translations;
    }
}
