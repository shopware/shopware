<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product\SalesChannel\Listing\Filter;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\SalesChannel\Listing\Filter;
use Shopware\Core\Content\Product\SalesChannel\Listing\Filter\ShippingFreeListingFilterHandler;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Bucket\FilterAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Metric\MaxAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[CoversClass(ShippingFreeListingFilterHandler::class)]
class ShippingFreeFilterHandlerTest extends TestCase
{
    public function testFilterCanBeSkipped(): void
    {
        $result = (new ShippingFreeListingFilterHandler())->create(
            new Request([], ['shipping-free-filter' => false]),
            $this->createMock(SalesChannelContext::class)
        );

        static::assertNull($result);
    }

    #[DataProvider('filterProvider')]
    public function testFilterCanBeCreated(bool $input): void
    {
        $result = (new ShippingFreeListingFilterHandler())->create(
            new Request(['shipping-free' => $input]),
            $this->createMock(SalesChannelContext::class)
        );

        $expected = new Filter(
            'shipping-free',
            $input,
            [
                new FilterAggregation(
                    'shipping-free-filter',
                    new MaxAggregation('shipping-free', 'product.shippingFree'),
                    [new EqualsFilter('product.shippingFree', true)]
                ),
            ],
            new EqualsFilter('product.shippingFree', true),
            $input
        );

        static::assertEquals($expected, $result);
    }

    public static function filterProvider(): \Generator
    {
        yield 'shipping free' => [true];
        yield 'not shipping free' => [false];
    }
}
