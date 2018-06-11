<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Shipping\Struct;

use Shopware\Core\Checkout\Shipping\Aggregate\ShippingMethodTranslation\Collection\ShippingMethodTranslationBasicCollection;

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
