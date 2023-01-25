<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\SalesChannel\Suggest;

use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

#[Package('system-settings
This route is used for the product suggest in the page header')]
abstract class AbstractProductSuggestRoute
{
    abstract public function getDecorated(): AbstractProductSuggestRoute;

    abstract public function load(Request $request, SalesChannelContext $context, Criteria $criteria): ProductSuggestRouteResponse;
}
