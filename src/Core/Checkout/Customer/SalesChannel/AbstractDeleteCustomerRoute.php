<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\SalesChannel;

use Shopware\Core\System\SalesChannel\NoContentResponse;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * This route can be used to delete a customer
 */
abstract class AbstractDeleteCustomerRoute
{
    abstract public function getDecorated(): AbstractDeleteCustomerRoute;

    abstract public function delete(SalesChannelContext $context): NoContentResponse;
}
