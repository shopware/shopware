<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Shipping\Aggregate\ShippingMethodTranslation\Collection;

use Shopware\Core\Checkout\Shipping\Aggregate\ShippingMethodTranslation\Struct\ShippingMethodTranslationDetailStruct;
use Shopware\Core\Checkout\Shipping\Collection\ShippingMethodBasicCollection;
use Shopware\Core\System\Language\Collection\LanguageBasicCollection;

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
