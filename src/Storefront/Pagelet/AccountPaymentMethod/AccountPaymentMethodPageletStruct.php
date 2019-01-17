<?php declare(strict_types=1);

namespace Shopware\Storefront\Pagelet\AccountPaymentMethod;

use Shopware\Core\Checkout\Payment\PaymentMethodCollection;
use Shopware\Storefront\Framework\Page\PageletStruct;

class AccountPaymentMethodPageletStruct extends PageletStruct
{
    /**
     * @var PaymentMethodCollection
     */
    protected $PaymentMethod;

    /**
     * @return PaymentMethodCollection
     */
    public function getPaymentMethod(): PaymentMethodCollection
    {
        return $this->PaymentMethod;
    }

    /**
     * @param PaymentMethodCollection $PaymentMethod
     */
    public function setPaymentMethod(PaymentMethodCollection $PaymentMethod): void
    {
        $this->PaymentMethod = $PaymentMethod;
    }
}
