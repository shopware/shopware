<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product\SalesChannel\Listing\Processor;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\Events\ProductListingCollectFilterEvent;
use Shopware\Core\Content\Product\SalesChannel\Listing\Filter;
use Shopware\Core\Content\Product\SalesChannel\Listing\Processor\AggregationListingProcessor;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Bucket\FilterAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Bucket\TermsAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[CoversClass(AggregationListingProcessor::class)]
class AggregationListingProcessorTest extends TestCase
{
    public function testAllFilterAggregationsAreCollectedInOrder(): void
    {
        $priceFilter = new Filter(
            'price',
            false,
            [new TermsAggregation('price', 'product.price')],
            new EqualsFilter('product.price', 100),
            null,
        );

        $manufacturerFilter = new Filter(
            'manufacturer',
            false,
            [new TermsAggregation('manufacturer', 'product.manufacturerId')],
            new EqualsFilter('product.manufacturerId', 'abc'),
            null,
        );

        $processor = $this->createProcessor($priceFilter, $manufacturerFilter);

        $criteria = new Criteria();
        $processor->prepare(new Request(), $criteria, $this->createMock(SalesChannelContext::class));

        $aggregations = $criteria->getAggregations();

        static::assertCount(2, $aggregations);
        static::assertSame(['price', 'manufacturer'], array_keys($aggregations));
        static::assertInstanceOf(TermsAggregation::class, $aggregations['price']);
        static::assertInstanceOf(TermsAggregation::class, $aggregations['manufacturer']);
    }

    public function testPrepareWithoutFiltersAddsNoAggregations(): void
    {
        $processor = $this->createProcessor();

        $criteria = new Criteria();
        $processor->prepare(new Request(), $criteria, $this->createMock(SalesChannelContext::class));

        static::assertSame([], $criteria->getAggregations());
    }

    public function testReduceAggregationsExcludesOwnFilterButKeepsOthers(): void
    {
        $priceFilter = new Filter(
            'price',
            true,
            [new TermsAggregation('price', 'product.price')],
            new EqualsFilter('product.price', 100),
            null,
            true,
        );

        $manufacturerFilter = new Filter(
            'manufacturer',
            true,
            [new TermsAggregation('manufacturer', 'product.manufacturerId')],
            new EqualsFilter('product.manufacturerId', 'abc'),
            null,
            true,
        );

        $processor = $this->createProcessor($priceFilter, $manufacturerFilter);

        $criteria = new Criteria();
        $processor->prepare(
            new Request(['reduce-aggregations' => '1']),
            $criteria,
            $this->createMock(SalesChannelContext::class)
        );

        $aggregations = $criteria->getAggregations();

        static::assertCount(2, $aggregations);

        // each non-filter aggregation is wrapped in a FilterAggregation carrying the post filters
        // of all *other* active filters (its own filter is blacklisted because exclude() === true)
        $price = $aggregations['price'];
        static::assertInstanceOf(FilterAggregation::class, $price);
        static::assertSame(['product.manufacturerId'], $this->fields($price));

        $manufacturer = $aggregations['manufacturer'];
        static::assertInstanceOf(FilterAggregation::class, $manufacturer);
        static::assertSame(['product.price'], $this->fields($manufacturer));
    }

    private function createProcessor(Filter ...$filters): AggregationListingProcessor
    {
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(
            ProductListingCollectFilterEvent::class,
            static function (ProductListingCollectFilterEvent $event) use ($filters): void {
                foreach ($filters as $filter) {
                    $event->getFilters()->add($filter);
                }
            }
        );

        return new AggregationListingProcessor([], $dispatcher);
    }

    /**
     * @return list<string>
     */
    private function fields(FilterAggregation $aggregation): array
    {
        $fields = [];
        foreach ($aggregation->getFilter() as $filter) {
            static::assertInstanceOf(EqualsFilter::class, $filter);
            $fields[] = $filter->getField();
        }

        return $fields;
    }
}
