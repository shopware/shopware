<?php declare(strict_types=1);

namespace Shopware\Checkout\Payment\Struct;

use Shopware\Application\Language\Struct\LanguageBasicStruct;

class PaymentMethodTranslationDetailStruct extends PaymentMethodTranslationBasicStruct
{
    /**
     * @var PaymentMethodBasicStruct
     */
    protected $paymentMethod;

    /**
     * @var LanguageBasicStruct
     */
    protected $language;

    public function getPaymentMethod(): PaymentMethodBasicStruct
    {
        return $this->paymentMethod;
    }

    public function setPaymentMethod(PaymentMethodBasicStruct $paymentMethod): void
    {
        $this->paymentMethod = $paymentMethod;
    }

    public function getLanguage(): LanguageBasicStruct
    {
        return $this->language;
    }

    public function setLanguage(LanguageBasicStruct $language): void
    {
        $this->language = $language;
    }
}
