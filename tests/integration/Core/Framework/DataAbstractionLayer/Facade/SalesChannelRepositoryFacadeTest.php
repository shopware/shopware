<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\DataAbstractionLayer\Facade;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\Aggregate\ProductManufacturer\ProductManufacturerEntity;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\Content\Test\Product\ProductBuilder;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\ApiProtectionException;
use Shopware\Core\Framework\DataAbstractionLayer\Facade\SalesChannelRepositoryFacade;
use Shopware\Core\Framework\DataAbstractionLayer\Facade\SalesChannelRepositoryFacadeHookFactory;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Metric\SumResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Script\Execution\Script;
use Shopware\Core\Framework\Script\Execution\ScriptExecutor;
use Shopware\Core\Framework\Struct\ArrayStruct;
use Shopware\Core\Framework\Test\Script\Execution\SalesChannelTestHook;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\Tax\TaxCollection;
use Shopware\Core\Test\AppSystemTestBehaviour;
use Shopware\Core\Test\Stub\Framework\IdsCollection;
use Shopware\Core\Test\TestDefaults;

/**
 * @internal
 */
class SalesChannelRepositoryFacadeTest extends TestCase
{
    use AppSystemTestBehaviour;
    use IntegrationTestBehaviour;

    private IdsCollection $ids;

    private SalesChannelRepositoryFacadeHookFactory $factory;

    private SalesChannelContext $context;

    protected function setUp(): void
    {
        $this->factory = static::getContainer()->get(SalesChannelRepositoryFacadeHookFactory::class);
        $this->context = static::getContainer()->get(SalesChannelContextFactory::class)->create(Uuid::randomHex(), TestDefaults::SALES_CHANNEL);
        $this->ids = new IdsCollection();
        $this->createProducts();
    }

    public function testEmptySearch(): void
    {
        $result = $this->createFacade()->search('product', []);

        static::assertCount(3, $result);
    }

    public function testSearchFilter(): void
    {
        $result = $this->createFacade()->search(
            'product',
            [
                'filter' => [
                    ['type' => 'equals', 'field' => 'childCount', 'value' => 0],
                ],
            ]
        );

        static::assertCount(1, $result);
        static::assertContains($this->ids->get('p3'), $result->getIds());
    }

    public function testSearchRead(): void
    {
        $result = $this->createFacade()->search(
            'product',
            [
                'ids' => [$this->ids->get('p1'), $this->ids->get('p2')],
            ]
        );

        static::assertCount(1, $result);
        static::assertContains($this->ids->get('p1'), $result->getIds());
    }

    public function testSearchAggregation(): void
    {
        $result = $this->createFacade()->search(
            'product',
            [
                'aggregations' => [
                    ['name' => 'sum', 'type' => 'sum', 'field' => 'childCount'],
                ],
            ]
        );

        static::assertCount(3, $result);
        $agg = $result->getAggregations()->get('sum');
        static::assertInstanceOf(SumResult::class, $agg);
        static::assertSame(1.0, $agg->getSum());
    }

    public function testSearchSort(): void
    {
        $result = $this->createFacade()->search(
            'product',
            [
                'sort' => [['field' => 'id']],
            ]
        );

        $actual = $result->getIds();

        $expected = $actual;
        sort($expected);

        static::assertSame($expected, array_values($actual));
    }

    public function testSearchIds(): void
    {
        $result = $this->createFacade()->ids('product', []);

        static::assertCount(3, $result->getIds());
    }

    public function testSearchIdsFilter(): void
    {
        $result = $this->createFacade()->ids(
            'product',
            [
                'filter' => [
                    ['type' => 'equals', 'field' => 'childCount', 'value' => 0],
                ],
            ]
        );

        static::assertCount(1, $result->getIds());
        static::assertContains($this->ids->get('p3'), $result->getIds());
    }

    public function testEmptyAggregation(): void
    {
        $result = $this->createFacade()->aggregate('product', []);

        static::assertCount(0, $result);
    }

    public function testAggregation(): void
    {
        $result = $this->createFacade()->aggregate(
            'product',
            [
                'aggregations' => [
                    ['name' => 'sum', 'type' => 'sum', 'field' => 'childCount'],
                ],
            ]
        );

        static::assertCount(1, $result);
        $agg = $result->get('sum');
        static::assertInstanceOf(SumResult::class, $agg);
        static::assertSame(1.0, $agg->getSum());
    }

    public function testIntegrationCase(): void
    {
        $this->installApp(__DIR__ . '/_fixtures/apps/pageLoadedExample');

        $page = new ArrayStruct();
        $hook = new SalesChannelTestHook(
            'store-search-by-id',
            $this->context,
            [
                'productId' => $this->ids->get('p1'),
                'page' => $page,
            ],
            [
                SalesChannelRepositoryFacadeHookFactory::class,
            ]
        );

        static::getContainer()->get(ScriptExecutor::class)->execute($hook);

        static::assertTrue($page->hasExtension('myProduct'));
        $product = $page->getExtension('myProduct');
        static::assertInstanceOf(SalesChannelProductEntity::class, $product);
        static::assertSame($this->ids->get('p1'), $product->getId());
    }

    public function testSearchWithFilterIntegration(): void
    {
        $this->installApp(__DIR__ . '/_fixtures/apps/pageLoadedExample');

        $page = new ArrayStruct();
        $hook = new SalesChannelTestHook(
            'store-filter',
            $this->context,
            [
                'page' => $page,
            ],
            [
                SalesChannelRepositoryFacadeHookFactory::class,
            ]
        );

        static::getContainer()->get(ScriptExecutor::class)->execute($hook);

        static::assertTrue($page->hasExtension('myProduct'));
        $product = $page->getExtension('myProduct');
        static::assertInstanceOf(SalesChannelProductEntity::class, $product);
        static::assertSame($this->ids->get('p1'), $product->getId());
    }

    public function testSearchWithAssociationIntegration(): void
    {
        $this->installApp(__DIR__ . '/_fixtures/apps/pageLoadedExample');

        $page = new ArrayStruct();
        $hook = new SalesChannelTestHook(
            'store-association',
            $this->context,
            [
                'productId' => $this->ids->get('p1'),
                'page' => $page,
            ],
            [
                SalesChannelRepositoryFacadeHookFactory::class,
            ]
        );

        static::getContainer()->get(ScriptExecutor::class)->execute($hook);

        static::assertTrue($page->hasExtension('myProduct'));
        $product = $page->getExtension('myProduct');
        static::assertInstanceOf(SalesChannelProductEntity::class, $product);
        static::assertSame($this->ids->get('p1'), $product->getId());
        static::assertInstanceOf(ProductManufacturerEntity::class, $product->getManufacturer());

        $manufacturer = $page->getExtension('myManufacturer');
        static::assertInstanceOf(ProductManufacturerEntity::class, $manufacturer);
        static::assertSame($this->ids->get('m1'), $manufacturer->getId());
    }

    public function testSearchIdsIntegration(): void
    {
        $this->installApp(__DIR__ . '/_fixtures/apps/pageLoadedExample');

        $page = new ArrayStruct();
        $hook = new SalesChannelTestHook(
            'store-search-ids',
            $this->context,
            [
                'page' => $page,
            ],
            [
                SalesChannelRepositoryFacadeHookFactory::class,
            ]
        );

        static::getContainer()->get(ScriptExecutor::class)->execute($hook);

        static::assertTrue($page->hasExtension('myProductIds'));
        $extension = $page->getExtension('myProductIds');
        static::assertInstanceOf(ArrayStruct::class, $extension);
        static::assertSame([$this->ids->get('p1')], $extension->get('ids'));
    }

    public function testAggregateIntegration(): void
    {
        $this->installApp(__DIR__ . '/_fixtures/apps/pageLoadedExample');

        $page = new ArrayStruct();
        $hook = new SalesChannelTestHook(
            'store-aggregate',
            $this->context,
            [
                'page' => $page,
            ],
            [
                SalesChannelRepositoryFacadeHookFactory::class,
            ]
        );

        static::getContainer()->get(ScriptExecutor::class)->execute($hook);

        static::assertTrue($page->hasExtension('myProductAggregations'));
        $extension = $page->getExtension('myProductAggregations');
        static::assertInstanceOf(ArrayStruct::class, $extension);
        static::assertSame(1.0, $extension->get('sum'));
    }

    public function testItThrowsForNotApiAwareField(): void
    {
        $criteria = [
            'aggregations' => [
                ['name' => 'sum', 'type' => 'sum', 'field' => 'autoIncrement'],
            ],
        ];

        static::expectException(ApiProtectionException::class);
        $this->createFacade()->search('product', $criteria);
    }

    private function createProducts(): void
    {
        $taxId = $this->getExistingTaxId();
        $this->ids->set('t1', $taxId);

        $product1 = (new ProductBuilder($this->ids, 'p1'))
            ->price(100)
            ->visibility()
            ->manufacturer('m1')
            ->variant(
                (new ProductBuilder($this->ids, 'v1.1'))
                    ->build()
            );

        $product2 = (new ProductBuilder($this->ids, 'p2'))
            ->price(200)
            ->visibility()
            ->active(false);

        $product3 = (new ProductBuilder($this->ids, 'p3'))
            ->visibility()
            ->price(300);

        static::getContainer()->get('product.repository')->create([
            $product1->build(),
            $product2->build(),
            $product3->build(),
        ], $this->context->getContext());
    }

    private function installApp(string $appDir): string
    {
        $this->loadAppsFromDir($appDir);

        $appId = static::getContainer()->get('app.repository')->searchIds(new Criteria(), Context::createDefaultContext())->firstId();
        static::assertIsString($appId);

        return $appId;
    }

    private function getExistingTaxId(): string
    {
        /** @var EntityRepository<TaxCollection> $taxRepository */
        $taxRepository = static::getContainer()->get('tax.repository');

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('name', 'Standard rate'));

        $taxId = $taxRepository->searchIds($criteria, $this->context->getContext())->firstId();
        static::assertIsString($taxId);

        return $taxId;
    }

    private function createFacade(): SalesChannelRepositoryFacade
    {
        return $this->factory->factory(
            new SalesChannelTestHook('test', $this->context),
            new Script('test', '', new \DateTimeImmutable())
        );
    }
}
