<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Cart;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartBehavior;
use Shopware\Core\Checkout\Cart\CartProcessorInterface;
use Shopware\Core\Checkout\Cart\Delivery\Struct\Delivery;
use Shopware\Core\Checkout\Cart\Error\Error;
use Shopware\Core\Checkout\Cart\LineItem\CartDataCollection;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\Checkout\Cart\Price\Struct\AbsolutePriceDefinition;
use Shopware\Core\Checkout\Cart\Price\Struct\QuantityPriceDefinition;
use Shopware\Core\Checkout\Cart\Processor;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTax;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRule;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Promotion\Cart\Error\AutoPromotionNotFoundError;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Struct\Struct;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\TaxAddToSalesChannelTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseHelper\ReflectionHelper;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Context\AbstractSalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Test\TestDefaults;

class ProcessorTest extends TestCase
{
    use IntegrationTestBehaviour;
    use TaxAddToSalesChannelTestBehaviour;

    /**
     * @var AbstractSalesChannelContextFactory
     */
    private $factory;

    /**
     * @var SalesChannelContext
     */
    private $context;

    /**
     * @var Processor
     */
    private $processor;

    protected function setUp(): void
    {
        $this->processor = $this->getContainer()->get(Processor::class);
        $this->factory = $this->getContainer()->get(SalesChannelContextFactory::class);
        $this->context = $this->factory->create(Uuid::randomHex(), TestDefaults::SALES_CHANNEL);
    }

    public function testDeliveryCreatedForDeliverableLineItem(): void
    {
        $cart = new Cart('test', 'test');

        $id = Uuid::randomHex();
        $tax = ['id' => Uuid::randomHex(), 'taxRate' => 19, 'name' => 'test'];

        $product = [
            'id' => $id,
            'name' => 'test',
            'price' => [
                ['currencyId' => Defaults::CURRENCY, 'gross' => 119.99, 'net' => 99.99, 'linked' => false],
            ],
            'productNumber' => Uuid::randomHex(),
            'manufacturer' => ['name' => 'test'],
            'tax' => $tax,
            'stock' => 10,
            'active' => true,
            'visibilities' => [
                ['salesChannelId' => TestDefaults::SALES_CHANNEL, 'visibility' => ProductVisibilityDefinition::VISIBILITY_ALL],
            ],
        ];

        $this->getContainer()->get('product.repository')
            ->create([$product], Context::createDefaultContext());

        $this->addTaxDataToSalesChannel($this->context, $tax);

        $cart->add(
            (new LineItem($id, LineItem::PRODUCT_LINE_ITEM_TYPE, $id, 1))
                ->setStackable(true)
                ->setRemovable(true)
        );

        $calculated = $this->processor->process($cart, $this->context, new CartBehavior());

        static::assertCount(1, $calculated->getLineItems());
        static::assertTrue($calculated->has($id));
        static::assertSame(119.99, $calculated->get($id)->getPrice()->getTotalPrice());

        static::assertCount(1, $calculated->getDeliveries());

        /** @var Delivery $delivery */
        $delivery = $calculated->getDeliveries()->first();
        static::assertTrue($delivery->getPositions()->getLineItems()->has($id));
    }

    public function testExtensionsAreMergedEarly(): void
    {
        $extension = new class() extends Struct {
        };
        $cart = new Cart('foo', 'bar');
        $cart->addExtension('unit-test', $extension);

        $processorProperty = ReflectionHelper::getProperty(Processor::class, 'processors');
        $originalProcessors = $processorProperty->getValue($this->processor);

        try {
            $processorProperty->setValue($this->processor, [
                new class() implements CartProcessorInterface {
                    public function process(CartDataCollection $data, Cart $original, Cart $toCalculate, SalesChannelContext $context, CartBehavior $behavior): void
                    {
                        TestCase::assertNotEmpty($original->getExtension('unit-test'));
                        TestCase::assertNotEmpty($toCalculate->getExtension('unit-test'));
                        TestCase::assertSame($original->getExtension('unit-test'), $toCalculate->getExtension('unit-test'));
                    }
                },
            ]);

            $newCart = $this->processor->process($cart, $this->context, new CartBehavior());

            static::assertSame($extension, $newCart->getExtension('unit-test'));
        } finally {
            $processorProperty->setValue($this->processor, $originalProcessors);
        }
    }

    public function testCalculatedCreditTaxesIncludeCustomItemTax(): void
    {
        $cart = new Cart('test', 'test');

        $productId = Uuid::randomHex();
        $customItemId = Uuid::randomHex();
        $creditId = Uuid::randomHex();

        $taxForProductItem = 10;

        $tax = ['id' => Uuid::randomHex(), 'taxRate' => $taxForProductItem, 'name' => 'test'];
        $product = [
            'id' => $productId,
            'name' => 'test',
            'price' => [
                ['currencyId' => Defaults::CURRENCY, 'gross' => 220, 'net' => 200, 'linked' => false],
            ],
            'productNumber' => Uuid::randomHex(),
            'manufacturer' => ['name' => 'test'],
            'tax' => $tax,
            'stock' => 10,
            'active' => true,
            'visibilities' => [
                ['salesChannelId' => TestDefaults::SALES_CHANNEL, 'visibility' => ProductVisibilityDefinition::VISIBILITY_ALL],
            ],
        ];

        $this->getContainer()->get('product.repository')
            ->create([$product], Context::createDefaultContext());

        $this->addTaxDataToSalesChannel($this->context, $tax);

        $taxForCustomItem = 20;

        $productLineItem = new LineItem($productId, LineItem::PRODUCT_LINE_ITEM_TYPE, $productId, 1);
        $taxRulesCustomItem = new TaxRuleCollection([new TaxRule($taxForCustomItem)]);
        $customLineItem = (new LineItem($customItemId, LineItem::CUSTOM_LINE_ITEM_TYPE, $customItemId, 1))
            ->setLabel('custom')
            ->setPriceDefinition(new QuantityPriceDefinition(200, $taxRulesCustomItem, 2));

        $creditLineItem = (new LineItem($creditId, LineItem::CREDIT_LINE_ITEM_TYPE, $creditId, 1))
            ->setLabel('credit')
            ->setPriceDefinition(new AbsolutePriceDefinition(-100));

        $cart->addLineItems(new LineItemCollection([$productLineItem, $customLineItem, $creditLineItem]));

        $calculated = $this->processor->process($cart, $this->context, new CartBehavior());

        static::assertCount(3, $calculated->getLineItems());
        static::assertNotEmpty($creditLineItem = $calculated->getLineItems()->filterType(LineItem::CREDIT_LINE_ITEM_TYPE)->first());

        static::assertCount(2, $creditCalculatedTaxes = $creditLineItem->getPrice()->getCalculatedTaxes()->getElements());

        $calculatedTaxForCustomItem = array_filter($creditCalculatedTaxes, function (CalculatedTax $tax) use ($taxForCustomItem) {
            return (int) $tax->getTaxRate() === $taxForCustomItem;
        });

        static::assertNotEmpty($calculatedTaxForCustomItem);
        static::assertCount(1, $calculatedTaxForCustomItem);

        $calculatedTaxForProductItem = array_filter($creditCalculatedTaxes, function (CalculatedTax $tax) use ($taxForProductItem) {
            return (int) $tax->getTaxRate() === $taxForProductItem;
        });

        static::assertNotEmpty($calculatedTaxForProductItem);
        static::assertCount(1, $calculatedTaxForProductItem);
    }

    public function testShippingCostIsCalculatedWithCustomItemOnly(): void
    {
        $cart = new Cart('test', 'test');

        $customItemId = Uuid::randomHex();

        $tax = ['id' => Uuid::randomHex(), 'taxRate' => 10, 'name' => 'test'];

        $this->addTaxDataToSalesChannel($this->context, $tax);

        $taxForCustomItem = 20;
        $taxRulesCustomItem = new TaxRuleCollection([new TaxRule($taxForCustomItem)]);

        $customLineItem = (new LineItem($customItemId, LineItem::CUSTOM_LINE_ITEM_TYPE, $customItemId, 1))
            ->setLabel('custom')
            ->setPriceDefinition(new QuantityPriceDefinition(200, $taxRulesCustomItem, 2));

        $cart->add($customLineItem);

        $calculated = $this->processor->process($cart, $this->context, new CartBehavior());

        static::assertNotEmpty($delivery = $calculated->getDeliveries()->first());
        static::assertNotEmpty($shippingCalculatedTaxes = $delivery->getShippingCosts()->getCalculatedTaxes()->first());
        static::assertEquals($taxForCustomItem, $shippingCalculatedTaxes->getTaxRate());
    }

    public function testShippingCostCalculatedTaxesIncludeCustomItemTax(): void
    {
        $cart = new Cart('test', 'test');

        $productId = Uuid::randomHex();
        $customItemId = Uuid::randomHex();

        $taxForCustomItem = 20;
        $taxForProductItem = 10;

        $tax = ['id' => Uuid::randomHex(), 'taxRate' => $taxForProductItem, 'name' => 'test'];

        $product = [
            'id' => $productId,
            'name' => 'test',
            'price' => [
                ['currencyId' => Defaults::CURRENCY, 'gross' => 220, 'net' => 200, 'linked' => false],
            ],
            'productNumber' => Uuid::randomHex(),
            'manufacturer' => ['name' => 'test'],
            'tax' => $tax,
            'stock' => 10,
            'active' => true,
            'visibilities' => [
                ['salesChannelId' => TestDefaults::SALES_CHANNEL, 'visibility' => ProductVisibilityDefinition::VISIBILITY_ALL],
            ],
        ];

        $this->getContainer()->get('product.repository')
            ->create([$product], Context::createDefaultContext());

        $this->addTaxDataToSalesChannel($this->context, $tax);

        $taxRulesCustomItem = new TaxRuleCollection([new TaxRule($taxForCustomItem)]);

        $productLineItem = new LineItem($productId, LineItem::PRODUCT_LINE_ITEM_TYPE, $productId, 1);
        $customLineItem = (new LineItem($customItemId, LineItem::CUSTOM_LINE_ITEM_TYPE, $customItemId, 1))
            ->setLabel('custom')
            ->setPriceDefinition(new QuantityPriceDefinition(200, $taxRulesCustomItem, 2));

        $cart->add($productLineItem);
        $cart->add($customLineItem);

        $calculated = $this->processor->process($cart, $this->context, new CartBehavior());

        static::assertCount(1, $calculated->getDeliveries());

        $delivery = $calculated->getDeliveries()->first();

        static::assertCount(2, $shippingCalculatedTaxes = $delivery->getShippingCosts()->getCalculatedTaxes()->getElements());

        $calculatedTaxForCustomItem = array_filter($shippingCalculatedTaxes, function (CalculatedTax $tax) use ($taxForCustomItem) {
            return (int) $tax->getTaxRate() === $taxForCustomItem;
        });

        static::assertNotEmpty($calculatedTaxForCustomItem);
        static::assertCount(1, $calculatedTaxForCustomItem);

        $calculatedTaxForProductItem = array_filter($shippingCalculatedTaxes, function (CalculatedTax $tax) use ($taxForProductItem) {
            return (int) $tax->getTaxRate() === $taxForProductItem;
        });

        static::assertNotEmpty($calculatedTaxForProductItem);
        static::assertCount(1, $calculatedTaxForProductItem);
    }

    public function testPersistentErrors(): void
    {
        $cart = new Cart(Uuid::randomHex(), Uuid::randomHex());

        $cart->addErrors(new NonePersistentError(), new PersistentError());

        $cart = $this->getContainer()->get(Processor::class)
            ->process($cart, $this->context, new CartBehavior());

        static::assertCount(1, $cart->getErrors());
        static::assertInstanceOf(PersistentError::class, $cart->getErrors()->first());

        $error = $cart->getErrors()->first();
        static::assertEquals('persistent', $error->getId());
        static::assertEquals('persistent', $error->getMessageKey());
    }

    public function testCartHasErrorDataAddedFromPromotionProcessor(): void
    {
        $originalCart = new Cart(Uuid::randomHex(), Uuid::randomHex());

        $id = Uuid::randomHex();
        $tax = ['id' => Uuid::randomHex(), 'taxRate' => 19, 'name' => 'test'];

        $product = $this->createDummyProduct($id, $tax);

        $this->getContainer()->get('product.repository')
            ->create([$product], Context::createDefaultContext());

        $this->addTaxDataToSalesChannel($this->context, $tax);

        $originalCart->add(
            (new LineItem($id, LineItem::PRODUCT_LINE_ITEM_TYPE, $id, 1))
                ->setStackable(true)
                ->setRemovable(true)
        );
        $originalCart->add(
            (new LineItem(Uuid::randomHex(), LineItem::PROMOTION_LINE_ITEM_TYPE, '', 1))
            ->setLabel('Discount 15%')
        );
        $originalCart->add(
            (new LineItem(Uuid::randomHex(), LineItem::PROMOTION_LINE_ITEM_TYPE, '', 1))
                ->setLabel('Discount 10%')
        );

        $this->processor->process($originalCart, $this->context, new CartBehavior());
        foreach ($originalCart->getErrors() as $error) {
            static::assertInstanceOf(AutoPromotionNotFoundError::class, $error);
        }
    }

    private function createDummyProduct(string $id, array $tax, int $stock = 10): array
    {
        return [
            'id' => $id,
            'name' => 'test',
            'price' => [
                ['currencyId' => Defaults::CURRENCY, 'gross' => 119.99, 'net' => 99.99, 'linked' => false],
            ],
            'productNumber' => Uuid::randomHex(),
            'manufacturer' => ['name' => 'test'],
            'tax' => $tax,
            'stock' => $stock,
            'isCloseout' => true,
            'active' => true,
            'visibilities' => [
                ['salesChannelId' => TestDefaults::SALES_CHANNEL, 'visibility' => ProductVisibilityDefinition::VISIBILITY_ALL],
            ],
        ];
    }
}

class PersistentError extends Error
{
    public function getId(): string
    {
        return 'persistent';
    }

    public function getMessageKey(): string
    {
        return 'persistent';
    }

    public function getLevel(): int
    {
        return 1;
    }

    public function blockOrder(): bool
    {
        return false;
    }

    public function getParameters(): array
    {
        return [];
    }

    public function isPersistent(): bool
    {
        return true;
    }
}

class NonePersistentError extends PersistentError
{
    public function getId(): string
    {
        return 'none-' . parent::getId();
    }

    public function getMessageKey(): string
    {
        return 'none-' . parent::getMessageKey();
    }

    public function isPersistent(): bool
    {
        return false;
    }
}
