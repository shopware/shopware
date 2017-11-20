<?php declare(strict_types=1);

namespace Shopware\Shipping\Collection;

use Shopware\Shipping\Struct\ShippingMethodTranslationDetailStruct;
use Shopware\Shop\Collection\ShopBasicCollection;

class ShippingMethodTranslationDetailCollection extends ShippingMethodTranslationBasicCollection
{
    /**
     * @var ShippingMethodTranslationDetailStruct[]
     */
    protected $elements = [];

    public function getShippingMethods(): ShippingMethodBasicCollection
    {
        return new ShippingMethodBasicCollection(
            $this->fmap(function (ShippingMethodTranslationDetailStruct $shippingMethodTranslation) {
                return $shippingMethodTranslation->getShippingMethod();
            })
        );
    }

    public function getLanguages(): ShopBasicCollection
    {
        return new ShopBasicCollection(
            $this->fmap(function (ShippingMethodTranslationDetailStruct $shippingMethodTranslation) {
                return $shippingMethodTranslation->getLanguage();
            })
        );
    }

    protected function getExpectedClass(): string
    {
        return ShippingMethodTranslationDetailStruct::class;
    }
}
