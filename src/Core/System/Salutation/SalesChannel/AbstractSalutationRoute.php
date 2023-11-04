<?php declare(strict_types=1);

namespace Shopware\Core\System\Salutation\SalesChannel;

use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * This route can be used to load all salutation of the authenticated sales channel.
 * With this route it is also possible to send the standard API parameters such as: 'page', 'limit', 'filter', etc.
 */
#[Package('customer-order')]
abstract class AbstractSalutationRoute
{
    abstract public function getDecorated(): AbstractSalutationRoute;

    abstract public function load(Request $request, SalesChannelContext $context, Criteria $criteria): SalutationRouteResponse;
}
