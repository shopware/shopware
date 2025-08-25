<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product\SalesChannel\Listing\Filter;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\SalesChannel\Listing\Filter;
use Shopware\Core\Content\Product\SalesChannel\Listing\Filter\ManufacturerListingFilterHandler;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Metric\EntityAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[CoversClass(ManufacturerListingFilterHandler::class)]
class ManufacturerFilterHandlerTest extends TestCase
{
    private ManufacturerListingFilterHandler $handler;

    protected function setUp(): void
    {
        $this->handler = new ManufacturerListingFilterHandler();
    }

    public function testCreateWithManufacturerFilterDisabled(): void
    {
        $request = new Request();
        $request->request->set('manufacturer-filter', false);

        $context = $this->createMock(SalesChannelContext::class);

        $filter = $this->handler->create($request, $context);

        static::assertNull($filter);
    }

    public function testCreateWithManufacturerFilterEnabled(): void
    {
        $manufacturerIds = ['1', '2', '3'];

        $request = new Request();
        $request->query->set('manufacturer', \implode('|', $manufacturerIds));

        $context = $this->createMock(SalesChannelContext::class);

        $filter = $this->handler->create($request, $context);

        static::assertInstanceOf(Filter::class, $filter);
        static::assertSame('manufacturer', $filter->getName());
        static::assertTrue($filter->isFiltered());

        $aggregations = $filter->getAggregations();
        static::assertCount(1, $aggregations);
        static::assertInstanceOf(EntityAggregation::class, $aggregations[0]);
        static::assertSame('manufacturer', $aggregations[0]->getName());
        static::assertSame('product.manufacturerId', $aggregations[0]->getField());
        static::assertSame('product_manufacturer', $aggregations[0]->getEntity());

        $criteriaFilter = $filter->getFilter();
        static::assertInstanceOf(EqualsAnyFilter::class, $criteriaFilter);
        static::assertSame('product.manufacturerId', $criteriaFilter->getField());
        static::assertSame($manufacturerIds, $criteriaFilter->getValue());
        static::assertSame($manufacturerIds, $filter->getValues());
    }

    public function testCreateWithEmptyManufacturerIds(): void
    {
        $request = new Request();
        $request->request->set('manufacturer-filter', true);
        $request->request->set('manufacturer', '');

        $context = $this->createMock(SalesChannelContext::class);

        $filter = $this->handler->create($request, $context);

        static::assertInstanceOf(Filter::class, $filter);
        static::assertSame('manufacturer', $filter->getName());
        static::assertFalse($filter->isFiltered());

        $aggregations = $filter->getAggregations();
        static::assertCount(1, $aggregations);
        static::assertInstanceOf(EntityAggregation::class, $aggregations[0]);
        static::assertSame('manufacturer', $aggregations[0]->getName());
        static::assertSame('product.manufacturerId', $aggregations[0]->getField());
        static::assertSame('product_manufacturer', $aggregations[0]->getEntity());

        $criteriaFilter = $filter->getFilter();
        static::assertInstanceOf(EqualsAnyFilter::class, $criteriaFilter);
        static::assertSame('product.manufacturerId', $criteriaFilter->getField());
        static::assertEmpty($criteriaFilter->getValue());

        static::assertEmpty($filter->getValues());
    }
}
