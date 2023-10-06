<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\SalesChannel\Listing\Filter;

use Shopware\Core\Content\Product\SalesChannel\Listing\Filter;
use Shopware\Core\Content\Product\SalesChannel\Listing\ProductListingResult;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

#[Package('inventory')]
abstract class AbstractListingFilterHandler
{
    abstract public function getDecorated(): AbstractListingFilterHandler;

    /**
     * The `create` function is used, to generate a new `Filter` object. This filter object represents the full
     * declaration how the aggregation should be executed, if the filter should be applied to the criteria
     * and the values which are used for the filter.
     *
     * How the filter should be added to the criteria, is handled by the `AggregationProcessor`, which handles
     * the global behavior, how the filters should be calculated.
     */
    abstract public function create(Request $request, SalesChannelContext $context): ?Filter;

    /**
     * The `process` function allows the developer, to post-process the calculated listing result and further process
     * the determined aggregation values to a more user readable state.
     */
    public function process(Request $request, ProductListingResult $result, SalesChannelContext $context): void
    {
    }
}
