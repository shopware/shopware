<?php declare(strict_types=1);

namespace Shopware\Storefront\Account\PageLoader;

use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Storefront\Account\Page\CustomerPageStruct;

class CustomerPageLoader
{
    public function __construct()
    {
    }

    public function load(CheckoutContext $context): CustomerPageStruct
    {
        return new CustomerPageStruct($context->getCustomer());
    }
}
