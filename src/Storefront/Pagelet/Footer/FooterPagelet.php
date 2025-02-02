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
     * @deprecated tag:v6.7.0 - reason:becomes-internal - Constructor will be internal with v6.7.0
     * @deprecated tag:v6.7.0 - reason:new-optional-parameter - Parameter serviceMenu will be required
     * @deprecated tag:v6.7.0 - reason:new-optional-parameter - Parameter paymentMethods will be required
     * @deprecated tag:v6.7.0 - reason:new-optional-parameter - Parameter shippingMethods will be required
     */
    public function __construct(
        ?Tree $navigation,
        protected ?CategoryCollection $serviceMenu = null,
        protected ?PaymentMethodCollection $paymentMethods = null,
        protected ?ShippingMethodCollection $shippingMethods = null,
    ) {
        parent::__construct($navigation);
    }

    /**
     * @deprecated tag:v6.7.0 - reason:return-type-change - Will only return CategoryCollection
     */
    public function getServiceMenu(): ?CategoryCollection
    {
        return $this->serviceMenu;
    }

    /**
     * @deprecated tag:v6.7.0 - reason:return-type-change - Will only return PaymentMethodCollection
     */
    public function getPaymentMethods(): ?PaymentMethodCollection
    {
        return $this->paymentMethods;
    }

    /**
     * @deprecated tag:v6.7.0 - reason:return-type-change - Will only return ShippingMethodCollection
     */
    public function getShippingMethods(): ?ShippingMethodCollection
    {
        return $this->shippingMethods;
    }
}
