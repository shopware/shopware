<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Account\PaymentMethod;

use Shopware\Core\Checkout\Payment\PaymentMethodCollection;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Storefront\Page\Page;

/**
 * @deprecated tag:v6.7.0 - this page is removed as customer default payment method will be removed
 */
#[Package('storefront')]
class AccountPaymentMethodPage extends Page
{
    /**
     * @var PaymentMethodCollection
     */
    protected $paymentMethods;

    public function getPaymentMethods(): PaymentMethodCollection
    {
        Feature::triggerDeprecationOrThrow('v6.7.0.0', 'The default payment method will be removed and the last used payment method is prioritized.');

        return $this->paymentMethods;
    }

    public function setPaymentMethods(PaymentMethodCollection $paymentMethods): void
    {
        Feature::triggerDeprecationOrThrow('v6.7.0.0', 'The default payment method will be removed and the last used payment method is prioritized.');
        $this->paymentMethods = $paymentMethods;
    }
}
