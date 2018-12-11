<?php declare(strict_types=1);

namespace Shopware\Storefront\Account\Page;

use Shopware\Core\Checkout\CheckoutContext;

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
