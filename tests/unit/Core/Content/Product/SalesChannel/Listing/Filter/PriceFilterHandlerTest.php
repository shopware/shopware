<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product\SalesChannel\Listing\Filter;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\SalesChannel\Listing\Filter;
use Shopware\Core\Content\Product\SalesChannel\Listing\Filter\PriceListingFilterHandler;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Metric\StatsAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\Filter as DALFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[CoversClass(PriceListingFilterHandler::class)]
class PriceFilterHandlerTest extends TestCase
{
    #[DataProvider('createProvider')]
    public function testCreate(Request $request, ?Filter $expected, string $method = Request::METHOD_GET): void
    {
        $request->setMethod($method);

        $handler = new PriceListingFilterHandler();
        $context = $this->createMock(SalesChannelContext::class);

        $result = $handler->create($request, $context);

        if (!$expected instanceof Filter) {
            static::assertNull($result);

            return;
        }

        static::assertEquals($expected, $result);
    }

    public static function createProvider(): \Generator
    {
        yield 'Test disable filter' => [
            new Request([], ['price-filter' => false]),
            null,
        ];

        yield 'Test filter will be generated' => [
            new Request(),
            self::create(false, new RangeFilter('product.cheapestPrice', []), ['min' => 0.0, 'max' => 0.0]),
        ];

        yield 'Test filter will be generated with min price' => [
            new Request([], ['min-price' => 10.0]),
            self::create(true, new RangeFilter('product.cheapestPrice', [RangeFilter::GTE => 10.0]), ['min' => 10.0, 'max' => 0.0]),
            Request::METHOD_POST,
        ];

        yield 'Test filter will be generated with max price' => [
            new Request([], ['max-price' => 10.0]),
            self::create(true, new RangeFilter('product.cheapestPrice', [RangeFilter::LTE => 10.0]), ['min' => 0.0, 'max' => 10.0]),
            Request::METHOD_POST,
        ];

        yield 'Test filter will be generated with min and max price' => [
            new Request([], ['min-price' => 10.0, 'max-price' => 20.0]),
            self::create(true, new RangeFilter('product.cheapestPrice', [RangeFilter::GTE => 10.0, RangeFilter::LTE => 20.0]), ['min' => 10.0, 'max' => 20.0]),
            Request::METHOD_POST,
        ];

        yield 'Test GET filter will be generated with min and max price' => [
            new Request(['min-price' => 10.0, 'max-price' => 20.0]),
            self::create(true, new RangeFilter('product.cheapestPrice', [RangeFilter::GTE => 10.0, RangeFilter::LTE => 20.0]), ['min' => 10.0, 'max' => 20.0]),
        ];
    }

    private static function create(bool $filtered, DALFilter $filter, mixed $values): Filter
    {
        $aggregations = [new StatsAggregation('price', 'product.cheapestPrice', true, true, false, false)];

        return new Filter('price', $filtered, $aggregations, $filter, $values);
    }
}
