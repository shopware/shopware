<?php declare(strict_types=1);

namespace Shopware\Storefront\Checkout\Page;

use Shopware\Core\Checkout\Payment\PaymentMethodCollection;
use Shopware\Storefront\Framework\Page\PageletStruct;

class PaymentMethodPageletStruct extends PageletStruct
{
    /**
     * @var PaymentMethodCollection
     */
    protected $paymentMethods;

    public function __construct(PaymentMethodCollection $paymentMethods = null)
    {
        $this->paymentMethods = $paymentMethods;
    }

    /**
     * @return PaymentMethodCollection
     */
    public function getPaymentMethods(): PaymentMethodCollection
    {
        return $this->paymentMethods;
    }

    /**
     * @param PaymentMethodCollection $paymentMethods
     */
    public function setPaymentMethods(PaymentMethodCollection $paymentMethods): void
    {
        $this->paymentMethods = $paymentMethods;
    }
}
