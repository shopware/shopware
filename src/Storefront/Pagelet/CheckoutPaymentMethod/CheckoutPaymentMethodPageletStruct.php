<?php declare(strict_types=1);

namespace Shopware\Storefront\Pagelet\CheckoutPaymentMethod;

use Shopware\Core\Checkout\Payment\PaymentMethodCollection;
use Shopware\Storefront\Framework\Page\PageletStruct;

class CheckoutPaymentMethodPageletStruct extends PageletStruct
{
    /**
     * @var PaymentMethodCollection
     */
    protected $paymentMethod;

    /**
     * @return PaymentMethodCollection
     */
    public function getPaymentMethod(): PaymentMethodCollection
    {
        return $this->paymentMethod;
    }

    /**
     * @param PaymentMethodCollection $paymentMethod
     */
    public function setPaymentMethod(PaymentMethodCollection $paymentMethod): void
    {
        $this->paymentMethod = $paymentMethod;
    }
}
