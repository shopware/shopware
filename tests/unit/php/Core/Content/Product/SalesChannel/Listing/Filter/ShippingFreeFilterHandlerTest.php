<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product\SalesChannel\Listing\Filter;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\SalesChannel\Listing\Filter;
use Shopware\Core\Content\Product\SalesChannel\Listing\Filter\ShippingFreeFilterHandler;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Bucket\FilterAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Metric\MaxAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 *
 * @covers \Shopware\Core\Content\Product\SalesChannel\Listing\Filter\ShippingFreeFilterHandler
 */
class ShippingFreeFilterHandlerTest extends TestCase
{
    public function testFilterCanBeSkipped(): void
    {
        $result = (new ShippingFreeFilterHandler())->create(
            new Request([], ['shipping-free-filter' => false]),
            $this->createMock(SalesChannelContext::class)
        );

        static::assertNull($result);
    }

    /**
     * @dataProvider filterProvider
     */
    public function testFilterCanBeCreated(bool $input): void
    {
        $result = (new ShippingFreeFilterHandler())->create(
            new Request(['shipping-free' => $input]),
            $this->createMock(SalesChannelContext::class)
        );

        $expected = new Filter(
            'shipping-free',
            true,
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
