<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\SalesChannel\Listing\Processor;

use Shopware\Core\Content\Product\SalesChannel\Listing\ProductListingResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

#[Package('inventory')]
abstract class AbstractListingProcessor
{
    abstract public function getDecorated(): self;

    /**
     * The `prepare` function allows to take care of the request parameters and interpret the different query and post
     * parameters and apply them to the provided `Criteria` object.
     *
     * The function is used in different contexts, like search, suggest and listing. You can check the different context by checking
     * the `criteria.states` collection for:
     * - 'suggest-route-context'
     * - 'listing-route-context'
     * - 'search-route-context'
     */
    abstract public function prepare(Request $request, Criteria $criteria, SalesChannelContext $context): void;

    /**
     * The `process` function allows to post process the determined listing result and enrich the result with more
     * meta information or to further process it for more user readable data.
     *
     * The function is used in different contexts, like search, suggest and listing. You can check the different context by checking
     * the `criteria.states` collection for:
     * - 'suggest-route-context'
     * - 'listing-route-context'
     * - 'search-route-context'
     */
    public function process(Request $request, ProductListingResult $result, SalesChannelContext $context): void
    {
    }
}
