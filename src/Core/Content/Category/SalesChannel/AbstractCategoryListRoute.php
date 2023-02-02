<?php declare(strict_types=1);

namespace Shopware\Core\Content\Category\SalesChannel;

use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

abstract class AbstractCategoryListRoute
{
    abstract public function getDecorated(): AbstractCategoryListRoute;

    abstract public function load(Criteria $criteria, SalesChannelContext $context): CategoryListRouteResponse;
}
