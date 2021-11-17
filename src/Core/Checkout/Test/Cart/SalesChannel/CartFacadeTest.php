<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Cart\SalesChannel;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\SalesChannel\CartFacade;
use Shopware\Core\Content\Test\Product\ProductBuilder;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Script\Exception\HookInjectionException;
use Shopware\Core\Framework\Script\Execution\Script;
use Shopware\Core\Framework\Script\Execution\ScriptExecutor;
use Shopware\Core\Framework\Test\App\AppSystemTestBehaviour;
use Shopware\Core\Framework\Test\IdsCollection;
use Shopware\Core\Framework\Test\Script\Execution\SalesChannelTestHook;
use Shopware\Core\Framework\Test\Script\Execution\TestHook;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\Test\TestDefaults;

class CartFacadeTest extends TestCase
{
    use IntegrationTestBehaviour;
    use AppSystemTestBehaviour;

    private IdsCollection $ids;

    private Script $script;

    protected function setUp(): void
    {
        Feature::skipTestIfInActive('FEATURE_NEXT_17441', $this);

        parent::setUp();

        $this->init();
        $this->script = new Script('test', '', new \DateTimeImmutable(), null);
    }

    /**
     * @dataProvider addProductProvider
     */
    public function testAddProduct(string $input, ?string $expected): void
    {
        $context = $this->getContainer()->get(SalesChannelContextFactory::class)
            ->create(Uuid::randomHex(), TestDefaults::SALES_CHANNEL, []);

        $service = $this->getContainer()->get(CartFacade::class);
        $service->inject(new SalesChannelTestHook('test', $context), $this->script);

        $item = $service->addProduct($this->ids->get($input));

        if ($expected === null) {
            static::assertNull($item);

            return;
        }

        static::assertInstanceOf(LineItem::class, $item);
        static::assertEquals($this->ids->get($expected), $item->getReferencedId());
        static::assertEquals(LineItem::PRODUCT_LINE_ITEM_TYPE, $item->getType());
    }

    public function testRemove(): void
    {
        $context = $this->getContainer()->get(SalesChannelContextFactory::class)
            ->create(Uuid::randomHex(), TestDefaults::SALES_CHANNEL, []);

        $service = $this->getContainer()->get(CartFacade::class);
        $service->inject(new SalesChannelTestHook('test', $context), $this->script);

        $item = $service->addProduct($this->ids->get('p1'));

        static::assertInstanceOf(LineItem::class, $item);
        static::assertEquals($this->ids->get('p1'), $item->getReferencedId());
        static::assertEquals(LineItem::PRODUCT_LINE_ITEM_TYPE, $item->getType());

        static::assertTrue($service->remove($item->getId()));

        $item = $service->cart()->get($this->ids->get('p1'));
        static::assertNull($item);

        static::assertFalse($service->remove('not-existing'));
    }

    /**
     * @dataProvider addProductProvider
     */
    public function testAddProductInScript(string $input, ?string $expected): void
    {
        $this->loadAppsFromDir(__DIR__ . '/_fixtures');

        $context = $this->getContainer()->get(SalesChannelContextFactory::class)
            ->create(Uuid::randomHex(), TestDefaults::SALES_CHANNEL, []);

        $hook = new SalesChannelTestHook('add-product-case', $context, ['productId' => $this->ids->get($input)], [CartFacade::class]);
        $this->getContainer()->get(ScriptExecutor::class)->execute($hook);

        $cart = $this->getContainer()->get(CartFacade::class)->cart();

        if ($expected === null) {
            static::assertEquals(0, $cart->getLineItems()->count());
        } else {
            static::assertTrue($cart->has($this->ids->get($expected)));
        }
    }

    public function testRemoveInScript(): void
    {
        $this->loadAppsFromDir(__DIR__ . '/_fixtures');

        $context = $this->getContainer()->get(SalesChannelContextFactory::class)
            ->create(Uuid::randomHex(), TestDefaults::SALES_CHANNEL, []);

        $hook = new SalesChannelTestHook('remove-case', $context, ['productId' => $this->ids->get('p1')], [CartFacade::class]);

        $service = $this->getContainer()->get(CartFacade::class);
        $service->inject($hook, $this->script);

        $item = $service->addProduct($this->ids->get('p1'));

        static::assertInstanceOf(LineItem::class, $item);
        static::assertEquals($this->ids->get('p1'), $item->getReferencedId());
        static::assertEquals(LineItem::PRODUCT_LINE_ITEM_TYPE, $item->getType());

        $this->getContainer()->get(ScriptExecutor::class)->execute($hook);

        $item = $service->cart()->get($this->ids->get('p1'));
        static::assertNull($item);
    }

    public function testDependency(): void
    {
        $this->expectException(HookInjectionException::class);

        $service = $this->getContainer()->get(CartFacade::class);
        $service->inject(new TestHook('test', Context::createDefaultContext()), $this->script);
    }

    public function addProductProvider(): \Generator
    {
        yield 'Test with simple product' => ['p1', 'p1'];
        yield 'Test variant support' => ['v2.1', 'v2.1'];
        yield 'Test parents will not be added' => ['p2', null];
    }

    private function init(): IdsCollection
    {
        $this->ids = new IdsCollection();
        $products = [
            (new ProductBuilder($this->ids, 'p1'))
                ->price(100)
                ->visibility()
                ->build(),
            (new ProductBuilder($this->ids, 'p2'))
                ->price(100)
                ->variant(
                    (new ProductBuilder($this->ids, 'v2.1'))
                        ->option('red', 'color')
                        ->build()
                )
                ->visibility()
                ->build(),
        ];

        $this->getContainer()->get('product.repository')
            ->create($products, Context::createDefaultContext());

        return $this->ids;
    }
}
