<?php declare(strict_types=1);

namespace Shopware\Api\Payment\Struct;

use Shopware\Api\Shop\Struct\ShopBasicStruct;

class PaymentMethodTranslationDetailStruct extends PaymentMethodTranslationBasicStruct
{
    /**
     * @var PaymentMethodBasicStruct
     */
    protected $paymentMethod;

    /**
     * @var ShopBasicStruct
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

    public function getLanguage(): ShopBasicStruct
    {
        return $this->language;
    }

    public function setLanguage(ShopBasicStruct $language): void
    {
        $this->language = $language;
    }
}
