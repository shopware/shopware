<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Cart;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\Exception\InsufficientPermissionException;
use Shopware\Core\Checkout\Cart\Exception\LineItemNotStackableException;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItemFactoryRegistry;
use Shopware\Core\Checkout\Cart\Price\Struct\QuantityPriceDefinition;
use Shopware\Core\Content\Product\Cart\ProductCartProcessor;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class LineItemFactoryRegistryTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @var LineItemFactoryRegistry
     */
    private $service;

    /**
     * @var SalesChannelContext
     */
    private $context;

    public function setUp(): void
    {
        $this->service = $this->getContainer()->get(LineItemFactoryRegistry::class);
        $this->context = $this->getContainer()->get(SalesChannelContextFactory::class)->create(Uuid::randomHex(), Defaults::SALES_CHANNEL);
    }

    public function testCreateProduct(): void
    {
        $lineItem = $this->service->create(['type' => 'product', 'referencedId' => 'test'], $this->context);
        static::assertSame('test', $lineItem->getReferencedId());
        static::assertSame(LineItem::PRODUCT_LINE_ITEM_TYPE, $lineItem->getType());
        static::assertSame(1, $lineItem->getQuantity());
    }

    public function testCreateProductWithPriceDefinition(): void
    {
        static::expectException(InsufficientPermissionException::class);

        $this->service->create([
            'type' => 'product',
            'referencedId' => 'test',
            'priceDefinition' => [
                'price' => 100.0,
                'type' => 'quantity',
                'precision' => 1,
                'taxRules' => [
                    [
                        'taxRate' => 5,
                        'percentage' => 100,
                    ],
                ],
            ],
        ], $this->context);
    }

    public function testCreateProductWithPriceDefinitionWithPermissions(): void
    {
        $this->context->setPermissions([ProductCartProcessor::ALLOW_PRODUCT_PRICE_OVERWRITES => true]);

        $lineItem = $this->service->create([
            'type' => 'product',
            'referencedId' => 'test',
            'priceDefinition' => [
                'price' => 100.0,
                'type' => 'quantity',
                'precision' => 1,
                'taxRules' => [
                    [
                        'taxRate' => 5,
                        'percentage' => 100,
                    ],
                ],
            ],
        ], $this->context);

        static::assertSame('test', $lineItem->getReferencedId());
        static::assertSame(LineItem::PRODUCT_LINE_ITEM_TYPE, $lineItem->getType());
        static::assertSame(1, $lineItem->getQuantity());
        static::assertInstanceOf(QuantityPriceDefinition::class, $lineItem->getPriceDefinition());
        static::assertSame(100.0, $lineItem->getPriceDefinition()->getPrice());
    }

    public function testUpdateDisabledStackable(): void
    {
        $id = Uuid::randomHex();
        $lineItem = new LineItem($id, LineItem::PRODUCT_LINE_ITEM_TYPE, Uuid::randomHex(), 1);
        $lineItem->setStackable(false);

        $cart = new Cart('test', 'test');
        $cart->add($lineItem);

        static::expectException(LineItemNotStackableException::class);

        $this->service->update($cart, ['id' => $id, 'quantity' => 2], $this->context);
    }

    public function testChangeQuantity(): void
    {
        $id = Uuid::randomHex();
        $lineItem = new LineItem($id, LineItem::PRODUCT_LINE_ITEM_TYPE, Uuid::randomHex(), 1);
        $lineItem->setStackable(true);

        $cart = new Cart('test', 'test');
        $cart->add($lineItem);

        $this->service->update($cart, ['id' => $id, 'quantity' => 2], $this->context);
        static::assertSame(2, $lineItem->getQuantity());
    }

    public function testCreatePromotion(): void
    {
        $lineItem = $this->service->create(['type' => 'promotion', 'referencedId' => 'test'], $this->context);
        static::assertInstanceOf(LineItem::class, $lineItem);
        static::assertSame('test', $lineItem->getReferencedId());
        static::assertSame(1, $lineItem->getQuantity());
        static::assertSame(LineItem::PROMOTION_LINE_ITEM_TYPE, $lineItem->getType());
    }

    public function testCreateCustomWithoutPermission(): void
    {
        static::expectException(InsufficientPermissionException::class);
        $this->service->create(['type' => 'custom', 'referencedId' => 'test'], $this->context);
    }

    public function testCreateWithPermission(): void
    {
        $this->context->setPermissions([ProductCartProcessor::ALLOW_PRODUCT_PRICE_OVERWRITES => true]);

        $lineItem = $this->service->create([
            'type' => 'custom',
            'referencedId' => 'test',
        ], $this->context);

        static::assertSame('custom', $lineItem->getType());
        static::assertSame('test', $lineItem->getReferencedId());
    }
}
