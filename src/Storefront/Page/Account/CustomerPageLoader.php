<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Account;

use Shopware\Core\Checkout\CustomerContext;

class CustomerPageLoader
{
    public function __construct()
    {
    }

    public function load(CustomerContext $context): CustomerPageStruct
    {
        return new CustomerPageStruct($context->getCustomer());
    }
}
