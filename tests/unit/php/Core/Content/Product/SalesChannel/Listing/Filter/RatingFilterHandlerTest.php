<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product\SalesChannel\Listing\Filter;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\SalesChannel\Listing\Filter;
use Shopware\Core\Content\Product\SalesChannel\Listing\Filter\RatingFilterHandler;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Bucket\FilterAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Metric\MaxAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 *
 * @covers \Shopware\Core\Content\Product\SalesChannel\Listing\Filter\RatingFilterHandler
 */
class RatingFilterHandlerTest extends TestCase
{
    public function testFilterCanBeSkipped(): void
    {
        $result = (new RatingFilterHandler())->create(
            new Request([], ['rating-filter' => false]),
            $this->createMock(SalesChannelContext::class)
        );

        static::assertNull($result);
    }

    /**
     * @dataProvider filterProvider
     */
    public function testFilterCanBeCreated(int $input): void
    {
        $result = (new RatingFilterHandler())->create(
            new Request(['rating' => $input]),
            $this->createMock(SalesChannelContext::class)
        );

        $expected = new Filter(
            'rating',
            true,
            [
                new FilterAggregation(
                    'rating-exists',
                    new MaxAggregation('rating', 'product.ratingAverage'),
                    [new RangeFilter('product.ratingAverage', [RangeFilter::GTE => 0])]
                ),
            ],
            new RangeFilter('product.ratingAverage', [
                RangeFilter::GTE => $input,
            ]),
            $input
        );

        static::assertEquals($expected, $result);
    }

    public static function filterProvider(): \Generator
    {
        yield 'rating better than 4' => [4];
        yield 'rating better than 3' => [3];
    }
}
