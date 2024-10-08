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
use Shopware\Core\Content\Product\ProductCollection;
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
            new LineItem('C', 'product', 'C'),
            (new LineItem('D', 'product', 'D'))->setStackable(true),
            (new LineItem('E', 'product', 'E', 3))->setStackable(true),
            (new LineItem('F', 'product', 'F', 3))->setStackable(true),
            new LineItem('G', 'product', ''),
        ]));

        $products = [
            // normal
            $a = (new SalesChannelProductEntity())->assign([
                'id' => 'A',
                'calculatedPrice' => new EmptyPrice(),
                'calculatedPrices' => new PriceCollection(),
                'calculatedMaxPurchase' => 1,
                'productNumber' => 'A',
                'stock' => 1,
            ]),
            // normal
            $b = (new SalesChannelProductEntity())->assign([
                'id' => 'B',
                'calculatedPrice' => new EmptyPrice(),
                'calculatedPrices' => new PriceCollection(),
                'calculatedMaxPurchase' => 1,
                'productNumber' => 'B',
                'stock' => 1,
            ]),
            // out of stock
            $c = (new SalesChannelProductEntity())->assign([
                'id' => 'C',
                'calculatedPrice' => new EmptyPrice(),
                'calculatedPrices' => new PriceCollection(),
                'calculatedMaxPurchase' => 1,
                'productNumber' => 'C',
                'stock' => 1,
                'minPurchase' => 2,
            ]),
            // min purchase
            $d = (new SalesChannelProductEntity())->assign([
                'id' => 'D',
                'calculatedPrice' => new EmptyPrice(),
                'calculatedPrices' => new PriceCollection(),
                'calculatedMaxPurchase' => 4,
                'productNumber' => 'D',
                'stock' => 4,
                'minPurchase' => 2,
            ]),
            // purchase step
            $e = (new SalesChannelProductEntity())->assign([
                'id' => 'E',
                'calculatedPrice' => new EmptyPrice(),
                'calculatedPrices' => new PriceCollection(),
                'calculatedMaxPurchase' => 4,
                'productNumber' => 'E',
                'stock' => 4,
                'minPurchase' => 2,
                'purchaseSteps' => 2,
            ]),
            // no reference id
            $f = (new SalesChannelProductEntity())->assign([
                'id' => 'F',
                'calculatedPrice' => new EmptyPrice(),
                'calculatedPrices' => new PriceCollection(),
                'calculatedMaxPurchase' => 2,
                'productNumber' => 'F',
                'stock' => 2,
                'maxPurchase' => 2,
            ]),
        ];

        $calculator = $this->createMock(ProductPriceCalculator::class);
        $calculator->expects(static::once())
            ->method('calculate')
            ->with($products);

        $gateway = $this->createMock(ProductGateway::class);
        $gateway
            ->expects(static::once())
            ->method('get')
            ->with(['C', 'D', 'E', 'F'])
            ->willReturn(new ProductCollection([$c, $d, $e, $f]));

        $processor = new ProductCartProcessor(
            $gateway,
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
        $errors = $cart->getErrors();
        $lineItems = $cart->getLineItems();

        static::assertCount(5, $lineItems);

        static::assertNotNull($lineItems->get('A'));
        static::assertNotNull($lineItems->get('B'));
        static::assertNotNull($lineItems->get('D'));
        static::assertNotNull($lineItems->get('E'));
        static::assertNotNull($lineItems->get('F'));

        static::assertNotNull($data->get('product-C'));
        static::assertNotNull($data->get('product-D'));
        static::assertNotNull($data->get('product-E'));
        static::assertNotNull($data->get('product-F'));

        static::assertNull($lineItems->get('C'));
        static::assertNull($lineItems->get('G'));

        static::assertNotNull($errors->get('product-out-of-stockC'));
        static::assertNotNull($errors->get('min-order-quantityD'));
        static::assertNotNull($errors->get('purchase-steps-quantityE'));
        static::assertNotNull($errors->get('product-stock-reachedF'));
        static::assertNotNull($errors->get('product-not-foundG'));

        static::assertSame(2, $lineItems->get('D')->getQuantity());
        static::assertSame(2, $lineItems->get('E')->getQuantity());
        static::assertSame(2, $lineItems->get('F')->getQuantity());
    }

    public function testPayloadIsReplacedCorrectly(): void
    {
        $cart = new Cart('test');
        $lineItem = new LineItem('A', 'product', 'A');

        $cart->setLineItems(new LineItemCollection([$lineItem]));

        $product = (new SalesChannelProductEntity())->assign([
            'id' => 'A',
            'calculatedPrice' => new EmptyPrice(),
            'calculatedPrices' => new PriceCollection(),
            'calculatedMaxPurchase' => 1,
            'productNumber' => 'A',
            'stock' => 1,
            'categoryTree' => ['a', 'b'],
        ]);

        $calculator = $this->createMock(ProductPriceCalculator::class);
        $calculator->expects(static::exactly(2))
            ->method('calculate')
            ->with([$product]);

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
        $data->set('product-A', $product);

        $processor->collect($data, $cart, $context, new CartBehavior());
        static::assertSame($lineItem->getPayloadValue('categoryIds'), ['a', 'b']);

        $product->setCategoryTree(['a']);
        $processor->collect($data, $cart, $context, new CartBehavior());
        static::assertSame($lineItem->getPayloadValue('categoryIds'), ['a']);
    }

    public function testPayloadIsTranslated(): void
    {
        $cart = new Cart('test');
        $lineItem = new LineItem('A', 'product', 'A');

        $cart->setLineItems(new LineItemCollection([$lineItem]));

        $product = (new SalesChannelProductEntity())->assign([
            'id' => 'A',
            'calculatedPrice' => new EmptyPrice(),
            'calculatedPrices' => new PriceCollection(),
            'calculatedMaxPurchase' => 1,
            'productNumber' => 'A',
            'stock' => 1,
            'categoryTree' => ['a', 'b'],
            'customFields' => [
                'foo' => 'bar',
            ],
            'translated' => [
                'customFields' => [
                    'foo' => 'baz',
                ],
            ],
        ]);

        $processor = new ProductCartProcessor(
            $this->createMock(ProductGateway::class),
            $this->createMock(QuantityPriceCalculator::class),
            $this->createMock(ProductFeatureBuilder::class),
            $this->createMock(ProductPriceCalculator::class),
            $this->createMock(EntityCacheKeyGenerator::class),
            $this->createMock(Connection::class)
        );

        $context = $this->createMock(SalesChannelContext::class);

        $data = new CartDataCollection();
        $data->set('product-A', $product);

        $processor->collect($data, $cart, $context, new CartBehavior());

        static::assertTrue($lineItem->hasPayloadValue('customFields'));
        $field = $lineItem->getPayloadValue('customFields');
        static::assertArrayHasKey('foo', $field);
        static::assertSame('baz', $field['foo']);
    }
}
