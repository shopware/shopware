<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Account;

use Shopware\Context\Struct\StorefrontContext;

class CustomerPageLoader
{
    public function __construct()
    {
    }

    public function load(StorefrontContext $context): CustomerPageStruct
    {
        return new CustomerPageStruct($context->getCustomer());
    }
}
