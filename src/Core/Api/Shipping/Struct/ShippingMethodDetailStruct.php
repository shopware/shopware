<?php declare(strict_types=1);

namespace Shopware\Api\Shipping\Struct;

use Shopware\Api\Shipping\Collection\ShippingMethodTranslationBasicCollection;

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
