<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product\Stock;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\Stock\StockLoadRequest;

/**
 * @internal
 *
 * @covers \Shopware\Core\Content\Product\Stock\StockLoadRequest
 */
class StockLoadRequestTest extends TestCase
{
    public function testStockRequest(): void
    {
        $stockRequest = new StockLoadRequest(['product-1', 'product-2']);

        static::assertEquals(['product-1', 'product-2'], $stockRequest->productIds);
    }
}
