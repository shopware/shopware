<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product\DataAbstractionLayer\StockUpdate;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\DataAbstractionLayer\StockUpdate\StockUpdateFilterProvider;
use Shopware\Core\Framework\Context;
use Shopware\Tests\Unit\Common\Stubs\DataAbstractionLayer\StockUpdate\TestStockUpdateFilter;

/**
 * @internal
 *
 * @covers \Shopware\Core\Content\Product\DataAbstractionLayer\StockUpdate\StockUpdateFilterProvider
 */
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
