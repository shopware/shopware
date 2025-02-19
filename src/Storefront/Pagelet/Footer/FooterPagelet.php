<?php declare(strict_types=1);

namespace Shopware\Storefront\Pagelet\Footer;

use Shopware\Core\Checkout\Payment\PaymentMethodCollection;
use Shopware\Core\Checkout\Shipping\ShippingMethodCollection;
use Shopware\Core\Content\Category\CategoryCollection;
use Shopware\Core\Content\Category\Tree\Tree;
use Shopware\Core\Framework\Log\Package;
use Shopware\Storefront\Pagelet\NavigationPagelet;

/**
 * @codeCoverageIgnore
 */
#[Package('framework')]
class FooterPagelet extends NavigationPagelet
{
    /**
     * @internal
     */
    public function __construct(
        ?Tree $navigation,
        protected CategoryCollection $serviceMenu,
        protected PaymentMethodCollection $paymentMethods,
        protected ShippingMethodCollection $shippingMethods,
    ) {
        parent::__construct($navigation);
    }

    public function getServiceMenu(): CategoryCollection
    {
        return $this->serviceMenu;
    }

    public function getPaymentMethods(): PaymentMethodCollection
    {
        return $this->paymentMethods;
    }

    public function getShippingMethods(): ShippingMethodCollection
    {
        return $this->shippingMethods;
    }
}
