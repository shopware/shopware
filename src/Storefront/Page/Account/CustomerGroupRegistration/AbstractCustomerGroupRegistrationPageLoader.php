<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Account\CustomerGroupRegistration;

use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * Loads the customer group registration page
 */
abstract class AbstractCustomerGroupRegistrationPageLoader
{
    abstract public function getDecorated(): AbstractCustomerGroupRegistrationPageLoader;

    abstract public function load(Request $request, SalesChannelContext $salesChannelContext): CustomerGroupRegistrationPage;
}
