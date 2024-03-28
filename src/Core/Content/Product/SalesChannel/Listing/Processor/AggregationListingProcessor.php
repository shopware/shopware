<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\SalesChannel\Listing\Processor;

use Shopware\Core\Content\Product\Events\ProductListingCollectFilterEvent;
use Shopware\Core\Content\Product\SalesChannel\Listing\Filter\AbstractListingFilterHandler;
use Shopware\Core\Content\Product\SalesChannel\Listing\FilterCollection;
use Shopware\Core\Content\Product\SalesChannel\Listing\ProductListingResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Aggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Bucket\FilterAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Metric\EntityResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Metric\MaxResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Metric\StatsResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[Package('inventory')]
class AggregationListingProcessor extends AbstractListingProcessor
{
    /**
     * @param iterable<AbstractListingFilterHandler> $factories
     *
     * @internal
     */
    public function __construct(
        private readonly iterable $factories,
        private readonly EventDispatcherInterface $dispatcher
    ) {
    }

    public function getDecorated(): AbstractListingProcessor
    {
        throw new DecorationPatternException(self::class);
    }

    public function prepare(Request $request, Criteria $criteria, SalesChannelContext $context): void
    {
        $filters = $this->getFilters($request, $context);

        $aggregations = $this->getAggregations($request, $filters);

        foreach ($aggregations as $aggregation) {
            $criteria->addAggregation($aggregation);
        }

        foreach ($filters as $filter) {
            if ($filter->isFiltered()) {
                $criteria->addPostFilter($filter->getFilter());
            }
        }

        $criteria->addExtension('filters', $filters);
    }

    public function process(Request $request, ProductListingResult $result, SalesChannelContext $context): void
    {
        $this->addCurrentFilters($result);

        foreach ($this->factories as $factory) {
            $factory->process($request, $result, $context);
        }

        $this->filterAggregations($result);
    }

    private function filterAggregations(ProductListingResult $result): void
    {
        $aggregations = $result->getAggregations();
        foreach ($aggregations as $aggregationName => $aggregation) {
            if ($aggregation instanceof MaxResult
                && $aggregation->getMax() === null) {
                $aggregations->remove($aggregationName);

                continue;
            }

            if ($aggregation instanceof EntityResult
                && $aggregation->getEntities()->count() === 0) {
                $aggregations->remove($aggregationName);

                continue;
            }

            if ($aggregation instanceof StatsResult
                && $aggregation->getMin() === null && $aggregation->getMax() === null) {
                $aggregations->remove($aggregationName);

                continue;
            }
        }
    }

    private function addCurrentFilters(ProductListingResult $result): void
    {
        $filters = $result->getCriteria()->getExtension('filters');
        if (!$filters instanceof FilterCollection) {
            return;
        }

        foreach ($filters as $filter) {
            $result->addCurrentFilter($filter->getName(), $filter->getValues());
        }
    }

    private function getFilters(Request $request, SalesChannelContext $context): FilterCollection
    {
        $filters = new FilterCollection();

        foreach ($this->factories as $factory) {
            $filter = $factory->create($request, $context);

            if ($filter !== null) {
                $filters->add($filter);
            }
        }

        $event = new ProductListingCollectFilterEvent($request, $filters, $context);
        $this->dispatcher->dispatch($event);

        return $filters;
    }

    /**
     * @return array<Aggregation>
     */
    private function getAggregations(Request $request, FilterCollection $filters): array
    {
        $aggregations = [];

        if ($request->get('reduce-aggregations') === null) {
            foreach ($filters as $filter) {
                $aggregations = array_merge($aggregations, $filter->getAggregations());
            }

            return $aggregations;
        }

        foreach ($filters as $filter) {
            $excluded = $filters->filtered();

            if ($filter->exclude()) {
                $excluded = $excluded->blacklist($filter->getName());
            }

            foreach ($filter->getAggregations() as $aggregation) {
                if ($aggregation instanceof FilterAggregation) {
                    $aggregation->addFilters($excluded->getFilters());

                    $aggregations[] = $aggregation;

                    continue;
                }

                $aggregation = new FilterAggregation(
                    $aggregation->getName(),
                    $aggregation,
                    $excluded->getFilters()
                );

                $aggregations[] = $aggregation;
            }
        }

        return $aggregations;
    }
}
