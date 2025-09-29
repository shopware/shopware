<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\SalesChannel\Listing\Filter;

use Shopware\Core\Content\Product\SalesChannel\Listing\Filter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Metric\StatsAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\CriteriaParameterResolver;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

#[Package('inventory')]
class PriceListingFilterHandler extends AbstractListingFilterHandler
{
    final public const FILTER_ENABLED_REQUEST_PARAM = 'price-filter';

    /**
     * @internal
     */
    public function __construct(
        private readonly CriteriaParameterResolver $parameterResolver
    ) {
    }

    public function getDecorated(): AbstractListingFilterHandler
    {
        throw new DecorationPatternException(self::class);
    }

    public function create(Request $request, SalesChannelContext $context): ?Filter
    {
        $filterEnabled = $this->parameterResolver->getParameter($request, self::FILTER_ENABLED_REQUEST_PARAM, true);

        if (!$filterEnabled) {
            return null;
        }

        $min = $this->parameterResolver->getParameter($request, 'min-price');
        $max = $this->parameterResolver->getParameter($request, 'max-price');

        $range = [];
        if ($min !== null && $min >= 0) {
            $range[RangeFilter::GTE] = $min;
        }
        if ($max !== null && $max >= 0) {
            $range[RangeFilter::LTE] = $max;
        }

        return new Filter(
            'price',
            !empty($range),
            [new StatsAggregation('price', 'product.cheapestPrice', true, true, false, false)],
            new RangeFilter('product.cheapestPrice', $range),
            [
                'min' => (float) $min,
                'max' => (float) $max,
            ]
        );
    }
}
