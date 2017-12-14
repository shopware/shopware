<?php declare(strict_types=1);

namespace Shopware\Api\Payment\Collection;

use Shopware\Api\Payment\Struct\PaymentMethodTranslationDetailStruct;
use Shopware\Api\Shop\Collection\ShopBasicCollection;

class PaymentMethodTranslationDetailCollection extends PaymentMethodTranslationBasicCollection
{
    /**
     * @var PaymentMethodTranslationDetailStruct[]
     */
    protected $elements = [];

    public function getPaymentMethods(): PaymentMethodBasicCollection
    {
        return new PaymentMethodBasicCollection(
            $this->fmap(function (PaymentMethodTranslationDetailStruct $paymentMethodTranslation) {
                return $paymentMethodTranslation->getPaymentMethod();
            })
        );
    }

    public function getLanguages(): ShopBasicCollection
    {
        return new ShopBasicCollection(
            $this->fmap(function (PaymentMethodTranslationDetailStruct $paymentMethodTranslation) {
                return $paymentMethodTranslation->getLanguage();
            })
        );
    }

    protected function getExpectedClass(): string
    {
        return PaymentMethodTranslationDetailStruct::class;
    }
}
