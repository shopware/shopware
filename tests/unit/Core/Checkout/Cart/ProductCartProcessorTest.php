<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Cart;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartBehavior;
use Shopware\Core\Checkout\Cart\LineItem\CartDataCollection;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\Checkout\Cart\Price\QuantityPriceCalculator;
use Shopware\Core\Checkout\Cart\Price\Struct\PriceCollection;
use Shopware\Core\Content\Product\Cart\ProductCartProcessor;
use Shopware\Core\Content\Product\Cart\ProductFeatureBuilder;
use Shopware\Core\Content\Product\Cart\ProductGateway;
use Shopware\Core\Content\Product\SalesChannel\Price\ProductPriceCalculator;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Cache\EntityCacheKeyGenerator;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Test\Stub\Checkout\EmptyPrice;

/**
 * @internal
 *
 * @covers \Shopware\Core\Content\Product\Cart\ProductCartProcessor
 */
#[Package('checkout')]
class ProductCartProcessorTest extends TestCase
{
    public function testPriceCalculatorIsCalledInBatch(): void
    {
        $cart = new Cart('test');

        $cart->setLineItems(new LineItemCollection([
            new LineItem('A', 'product', 'A'),
            new LineItem('B', 'product', 'B'),
        ]));

        $products = [
            $a = (new SalesChannelProductEntity())->assign([
                'id' => 'A',
                'calculatedPrice' => new EmptyPrice(),
                'calculatedPrices' => new PriceCollection(),
                'calculatedMaxPurchase' => 1,
                'productNumber' => 'A',
                'stock' => 1,
            ]),
            $b = (new SalesChannelProductEntity())->assign([
                'id' => 'B',
                'calculatedPrice' => new EmptyPrice(),
                'calculatedPrices' => new PriceCollection(),
                'calculatedMaxPurchase' => 1,
                'productNumber' => 'B',
                'stock' => 1,
            ]),
        ];

        $calculator = $this->createMock(ProductPriceCalculator::class);
        $calculator->expects(static::once())
            ->method('calculate')
            ->with($products);

        $processor = new ProductCartProcessor(
            $this->createMock(ProductGateway::class),
            $this->createMock(QuantityPriceCalculator::class),
            $this->createMock(ProductFeatureBuilder::class),
            $calculator,
            $this->createMock(EntityCacheKeyGenerator::class),
            $this->createMock(Connection::class)
        );

        $context = $this->createMock(SalesChannelContext::class);

        $data = new CartDataCollection();
        $data->set('product-A', $a);
        $data->set('product-B', $b);

        $processor->collect($data, $cart, $context, new CartBehavior());
    }
}
