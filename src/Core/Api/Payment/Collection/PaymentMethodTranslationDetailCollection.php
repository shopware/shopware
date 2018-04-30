<?php declare(strict_types=1);

namespace Shopware\Api\Payment\Collection;

use Shopware\Api\Language\Collection\LanguageBasicCollection;
use Shopware\Api\Payment\Struct\PaymentMethodTranslationDetailStruct;

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

    public function getLanguages(): LanguageBasicCollection
    {
        return new LanguageBasicCollection(
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
