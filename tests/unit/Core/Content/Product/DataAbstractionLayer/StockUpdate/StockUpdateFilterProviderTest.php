<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product\DataAbstractionLayer\StockUpdate;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\DataAbstractionLayer\StockUpdate\StockUpdateFilterProvider;
use Shopware\Core\Framework\Context;

/**
 * @internal
 */
#[CoversClass(StockUpdateFilterProvider::class)]
class StockUpdateFilterProviderTest extends TestCase
{
    public function testHandlesFilter(): void
    {
        $ids = ['id1', 'id2', 'id3'];

        $filter = new TestStockUpdateFilter(['id1', 'id2']);

        $provider = new StockUpdateFilterProvider([$filter]);

        static::assertEquals(['id3'], $provider->filterProductIdsForStockUpdates($ids, Context::createDefaultContext()));
    }
}
