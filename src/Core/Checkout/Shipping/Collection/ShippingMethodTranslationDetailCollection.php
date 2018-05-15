<?php declare(strict_types=1);

namespace Shopware\Checkout\Shipping\Collection;

use Shopware\Api\Language\Collection\LanguageBasicCollection;
use Shopware\Checkout\Shipping\Struct\ShippingMethodTranslationDetailStruct;

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

    public function getLanguages(): LanguageBasicCollection
    {
        return new LanguageBasicCollection(
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
