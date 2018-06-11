<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Payment\Aggregate\PaymentMethodTranslation\Collection;

use Shopware\Core\System\Language\Collection\LanguageBasicCollection;
use Shopware\Core\Checkout\Payment\Aggregate\PaymentMethodTranslation\Struct\PaymentMethodTranslationDetailStruct;
use Shopware\Core\Checkout\Payment\Collection\PaymentMethodBasicCollection;

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
