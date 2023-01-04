<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\SalesChannel;

use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

#[Package('customer-order')]
abstract class AbstractLoadWishlistRoute
{
    abstract public function getDecorated(): AbstractLoadWishlistRoute;

    abstract public function load(Request $request, SalesChannelContext $context, Criteria $criteria, CustomerEntity $customer): LoadWishlistRouteResponse;
}
