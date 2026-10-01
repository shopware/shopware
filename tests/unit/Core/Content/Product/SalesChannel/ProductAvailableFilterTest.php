<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product\SalesChannel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Content\Product\SalesChannel\ProductAvailableFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\Filter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\TestDefaults;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(ProductAvailableFilter::class)]
class ProductAvailableFilterTest extends TestCase
{
    public function testCreatesProductAvailableFilter(): void
    {
        $salesChannelId = TestDefaults::SALES_CHANNEL;
        $visibility = ProductVisibilityDefinition::VISIBILITY_ALL;

        $filter = new ProductAvailableFilter($salesChannelId, $visibility);

        static::assertSame($salesChannelId, $filter->getSalesChannelId());
        static::assertSame($visibility, $filter->getVisibility());
        static::assertCount(3, $filter->getQueries());
        static::assertSame([
            (new RangeFilter('product.visibilities.visibility', [RangeFilter::GTE => $visibility]))->jsonSerialize(),
            (new EqualsFilter('product.visibilities.salesChannelId', $salesChannelId))->jsonSerialize(),
            (new EqualsFilter('product.active', true))->jsonSerialize(),
        ], array_map(
            static fn (Filter $filter): array => $filter->jsonSerialize(),
            $filter->getQueries()
        ));
    }
}
