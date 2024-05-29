<?php declare(strict_types=1);

namespace Shopware\Storefront\Pagelet\Footer;

use Shopware\Core\Checkout\Payment\PaymentMethodCollection;
use Shopware\Core\Checkout\Shipping\ShippingMethodCollection;
use Shopware\Core\Content\Category\CategoryCollection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Storefront\Pagelet\NavigationPagelet;

#[Package('storefront')]
class FooterPagelet extends NavigationPagelet
{
    public ?PaymentMethodCollection $paymentMethods = null;

    public ?ShippingMethodCollection $shippingMethods = null;

    public ?CategoryCollection $service = null;
}
