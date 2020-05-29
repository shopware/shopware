<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\SalesChannel;

use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * This route is used to get information about the current logged-in customer
 */
abstract class AbstractCustomerRoute
{
    abstract public function getDecorated(): AbstractCustomerRoute;

    /**
     * @param Criteria $criteria - Will be implemented in tag:v6.4.0, can already be used
     */
    abstract public function load(Request $request, SalesChannelContext $context/*, Criteria $criteria*/): CustomerResponse;
}
