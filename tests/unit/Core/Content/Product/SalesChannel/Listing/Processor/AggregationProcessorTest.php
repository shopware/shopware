<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product\SalesChannel\Listing\Processor;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\Events\ProductListingCollectFilterEvent;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\SalesChannel\Listing\Filter;
use Shopware\Core\Content\Product\SalesChannel\Listing\Filter\AbstractListingFilterHandler;
use Shopware\Core\Content\Product\SalesChannel\Listing\Processor\AggregationListingProcessor;
use Shopware\Core\Content\Product\SalesChannel\Listing\ProductListingResult;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Bucket\FilterAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Bucket\TermsAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(AggregationListingProcessor::class)]
class AggregationProcessorTest extends TestCase
{
    public function testByPassPrepare(): void
    {
        $processor = new AggregationListingProcessor(
            [$foo = new FooListingFilterHandler()],
            static::createStub(EventDispatcherInterface::class)
        );

        $context = static::createStub(SalesChannelContext::class);

        $criteria = new Criteria();
        $processor->prepare(new Request(), $criteria, $context);

        static::assertCount(1, $criteria->getAggregations());
        static::assertCount(1, $criteria->getPostFilters());
        static::assertInstanceOf(TermsAggregation::class, $criteria->getAggregation('foo'));
        static::assertTrue($foo->called);
    }

    public function testByPassProcess(): void
    {
        $processor = new AggregationListingProcessor(
            [$foo = new FooListingFilterHandler()],
            static::createStub(EventDispatcherInterface::class)
        );

        $context = static::createStub(SalesChannelContext::class);

        $result = new ProductListingResult('test', 0, new ProductCollection(), null, new Criteria(), Context::createDefaultContext());

        $processor->process(new Request(), $result, $context);

        static::assertTrue($foo->called);
    }

    public function testCurrentFiltersAttached(): void
    {
        $processor = new AggregationListingProcessor(
            [new FooListingFilterHandler()],
            static::createStub(EventDispatcherInterface::class)
        );

        $context = static::createStub(SalesChannelContext::class);
        $criteria = new Criteria();

        $processor->prepare(new Request(), $criteria, $context);

        $result = new ProductListingResult('test', 0, new ProductCollection(), null, $criteria, Context::createDefaultContext());

        $processor->process(new Request(), $result, $context);

        static::assertSame(['foo'], $result->getCurrentFilter('foo'));
    }

    public function testReduceAggregationBehavior(): void
    {
        $processor = new AggregationListingProcessor(
            [new FooListingFilterHandler(), new BarListingFilterHandler()],
            static::createStub(EventDispatcherInterface::class)
        );

        $processor->prepare(
            new Request(['reduce-aggregations' => true]),
            $criteria = new Criteria(),
            static::createStub(SalesChannelContext::class)
        );

        static::assertCount(2, $criteria->getAggregations());
        static::assertCount(2, $criteria->getPostFilters());

        $agg = $criteria->getAggregation('foo');
        static::assertInstanceOf(FilterAggregation::class, $agg);
        static::assertCount(1, $agg->getFilter());
        $filter = $agg->getFilter();
        $filter = \array_shift($filter);
        static::assertInstanceOf(EqualsFilter::class, $filter);
        static::assertSame('bar', $filter->getField());

        $agg = $criteria->getAggregation('bar');
        static::assertInstanceOf(FilterAggregation::class, $agg);

        // filter is set to excluded and should not be removed by own list (property filter scenario where you also have to add the property filter to calculate the property filter)
        static::assertCount(2, $agg->getFilter());
    }

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
        $processor->prepare(new Request(), $criteria, static::createStub(SalesChannelContext::class));

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
        $processor->prepare(new Request(), $criteria, static::createStub(SalesChannelContext::class));

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
            static::createStub(SalesChannelContext::class)
        );

        $aggregations = $criteria->getAggregations();

        static::assertCount(2, $aggregations);

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

/**
 * @internal
 */
class FooListingFilterHandler extends AbstractListingFilterHandler
{
    public bool $called = false;

    public function getDecorated(): AbstractListingFilterHandler
    {
        throw new DecorationPatternException(self::class);
    }

    public function create(Request $request, SalesChannelContext $context): ?Filter
    {
        $this->called = true;

        return new Filter('foo', true, [new TermsAggregation('foo', 'foo')], new EqualsFilter('foo', true), ['foo']);
    }

    public function process(Request $request, ProductListingResult $result, SalesChannelContext $context): void
    {
        $this->called = true;
    }
}

/**
 * @internal
 */
class BarListingFilterHandler extends AbstractListingFilterHandler
{
    public function getDecorated(): AbstractListingFilterHandler
    {
        throw new DecorationPatternException(self::class);
    }

    public function create(Request $request, SalesChannelContext $context): ?Filter
    {
        return new Filter('bar', true, [new TermsAggregation('bar', 'bar')], new EqualsFilter('bar', true), [], false);
    }
}
