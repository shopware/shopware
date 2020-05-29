<?php declare(strict_types=1);

namespace Shopware\Core\System\Country\SalesChannel;

use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * This route can be used to load all countries of the authenticated sales channel.
 * With this route it is also possible to send the standard API parameters such as: 'page', 'limit', 'filter', etc.
 */
abstract class AbstractCountryRoute
{
    abstract public function load(Criteria $criteria, SalesChannelContext $context): CountryRouteResponse;

    abstract protected function getDecorated(): AbstractCountryRoute;
}
