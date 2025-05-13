<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\DataAbstractionLayer\Facade;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\Aggregate\ProductManufacturer\ProductManufacturerEntity;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Content\Test\Product\ProductBuilder;
use Shopware\Core\Framework\Api\Exception\MissingPrivilegeException;
use Shopware\Core\Framework\App\AppCollection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Facade\RepositoryFacade;
use Shopware\Core\Framework\DataAbstractionLayer\Facade\RepositoryFacadeHookFactory;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Metric\SumResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Script\Execution\Script;
use Shopware\Core\Framework\Script\Execution\ScriptAppInformation;
use Shopware\Core\Framework\Script\Execution\ScriptExecutor;
use Shopware\Core\Framework\Struct\ArrayStruct;
use Shopware\Core\Framework\Test\Script\Execution\TestHook;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Test\AppSystemTestBehaviour;
use Shopware\Core\Test\Stub\Framework\IdsCollection;

/**
 * @internal
 */
class RepositoryFacadeTest extends TestCase
{
    use AppSystemTestBehaviour;
    use IntegrationTestBehaviour;

    private IdsCollection $ids;

    private RepositoryFacadeHookFactory $factory;

    protected function setUp(): void
    {
        $this->factory = static::getContainer()->get(RepositoryFacadeHookFactory::class);
        $this->ids = new IdsCollection();
        $this->createProducts();
    }

    public function testEmptySearchWithoutApp(): void
    {
        $result = $this->createFacade()->search('product', []);

        static::assertCount(4, $result);
        static::assertCount(4, $result->getIds());
    }

    public function testSearchFilterWithoutApp(): void
    {
        $result = $this->createFacade()->search(
            'product',
            [
                'filter' => [
                    ['type' => 'equals', 'field' => 'active', 'value' => true],
                ],
            ]
        );

        static::assertCount(3, $result);
        static::assertNotContains($this->ids->get('p2'), $result->getIds());
    }

    public function testSearchReadWithoutApp(): void
    {
        $result = $this->createFacade()->search(
            'product',
            [
                'ids' => [$this->ids->get('p1'), $this->ids->get('p2')],
            ]
        );

        static::assertCount(2, $result);
        static::assertContains($this->ids->get('p1'), $result->getIds());
        static::assertContains($this->ids->get('p2'), $result->getIds());
    }

    public function testSearchAggregationWithoutApp(): void
    {
        $result = $this->createFacade()->search(
            'product',
            [
                'aggregations' => [
                    ['name' => 'sum', 'type' => 'sum', 'field' => 'price.gross'],
                ],
            ]
        );

        static::assertCount(4, $result);
        $agg = $result->getAggregations()->get('sum');
        static::assertInstanceOf(SumResult::class, $agg);
        static::assertSame(600.0, $agg->getSum());
    }

    public function testSearchSortWithoutApp(): void
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

    public function testEmptySearchIdsWithoutApp(): void
    {
        $result = $this->createFacade()->search(
            'product',
            [
                'ids' => [$this->ids->get('p1'), $this->ids->get('p2')],
            ]
        );

        static::assertCount(2, $result);
        static::assertContains($this->ids->get('p1'), $result->getIds());
        static::assertContains($this->ids->get('p2'), $result->getIds());
    }

    public function testEmptyIdsWithoutApp(): void
    {
        $result = $this->createFacade()->ids('product', []);

        static::assertCount(4, $result->getIds());
    }

    public function testIdsFilterWithoutApp(): void
    {
        $result = $this->createFacade()->ids(
            'product',
            [
                'filter' => [
                    ['type' => 'equals', 'field' => 'active', 'value' => true],
                ],
            ]
        );

        static::assertCount(3, $result->getIds());
        static::assertNotContains($this->ids->get('p2'), $result->getIds());
    }

    public function testEmptyAggregationWithoutApp(): void
    {
        $result = $this->createFacade()->aggregate('product', []);

        static::assertCount(0, $result);
    }

    public function testAggregationWithoutApp(): void
    {
        $result = $this->createFacade()->aggregate(
            'product',
            [
                'aggregations' => [
                    ['name' => 'sum', 'type' => 'sum', 'field' => 'price.gross'],
                ],
            ]
        );

        static::assertCount(1, $result);
        $agg = $result->get('sum');
        static::assertInstanceOf(SumResult::class, $agg);
        static::assertSame(600.0, $agg->getSum());
    }

    public function testWithApp(): void
    {
        $facade = $this->createFacade(
            $this->installApp(__DIR__ . '/_fixtures/apps/withProductPermission')
        );

        $result = $facade->search('product', []);
        static::assertCount(4, $result);

        $result = $facade->ids('product', []);
        static::assertCount(4, $result->getIds());

        $result = $facade->aggregate('product', [
            'aggregations' => [
                ['name' => 'sum', 'type' => 'sum', 'field' => 'price.gross'],
            ],
        ]);
        $agg = $result->get('sum');
        static::assertInstanceOf(SumResult::class, $agg);
        static::assertSame(600.0, $agg->getSum());
    }

    public function testSearchWithAppWithoutNeededPermissions(): void
    {
        $facade = $this->createFacade(
            $this->installApp(__DIR__ . '/_fixtures/apps/withoutProductPermission')
        );

        static::expectException(MissingPrivilegeException::class);
        $facade->search('product', []);
    }

    public function testIdsWithAppWithoutNeededPermissions(): void
    {
        $facade = $this->createFacade(
            $this->installApp(__DIR__ . '/_fixtures/apps/withoutProductPermission')
        );

        static::expectException(MissingPrivilegeException::class);
        $facade->ids('product', []);
    }

    public function testAggregateIdsWithAppWithoutNeededPermissions(): void
    {
        $facade = $this->createFacade(
            $this->installApp(__DIR__ . '/_fixtures/apps/withoutProductPermission')
        );

        static::expectException(MissingPrivilegeException::class);
        $facade->aggregate('product', []);
    }

    public function testSearchByIdIntegration(): void
    {
        $this->installApp(__DIR__ . '/_fixtures/apps/pageLoadedExample');

        $page = new ArrayStruct();
        $hook = new TestHook(
            'repository-search-by-id',
            Context::createDefaultContext(),
            [
                'productId' => $this->ids->get('p1'),
                'page' => $page,
            ],
            [
                RepositoryFacadeHookFactory::class,
            ]
        );

        static::getContainer()->get(ScriptExecutor::class)->execute($hook);

        static::assertTrue($page->hasExtension('myProduct'));
        $product = $page->getExtension('myProduct');
        static::assertInstanceOf(ProductEntity::class, $product);
        static::assertSame($this->ids->get('p1'), $product->getId());
    }

    public function testSearchWithFilterIntegration(): void
    {
        $this->installApp(__DIR__ . '/_fixtures/apps/pageLoadedExample');

        $page = new ArrayStruct();
        $hook = new TestHook(
            'repository-filter',
            Context::createDefaultContext(),
            [
                'page' => $page,
            ],
            [
                RepositoryFacadeHookFactory::class,
            ]
        );

        static::getContainer()->get(ScriptExecutor::class)->execute($hook);

        static::assertTrue($page->hasExtension('myProduct'));
        $product = $page->getExtension('myProduct');
        static::assertInstanceOf(ProductEntity::class, $product);
        static::assertSame($this->ids->get('p1'), $product->getId());
    }

    public function testSearchWithAssociationIntegration(): void
    {
        $this->installApp(__DIR__ . '/_fixtures/apps/pageLoadedExample');

        $page = new ArrayStruct();
        $hook = new TestHook(
            'repository-association',
            Context::createDefaultContext(),
            [
                'productId' => $this->ids->get('p1'),
                'page' => $page,
            ],
            [
                RepositoryFacadeHookFactory::class,
            ]
        );

        static::getContainer()->get(ScriptExecutor::class)->execute($hook);

        static::assertTrue($page->hasExtension('myProduct'));
        $product = $page->getExtension('myProduct');
        static::assertInstanceOf(ProductEntity::class, $product);
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
        $hook = new TestHook(
            'repository-search-ids',
            Context::createDefaultContext(),
            [
                'page' => $page,
            ],
            [
                RepositoryFacadeHookFactory::class,
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
        $hook = new TestHook(
            'repository-aggregate',
            Context::createDefaultContext(),
            [
                'page' => $page,
            ],
            [
                RepositoryFacadeHookFactory::class,
            ]
        );

        static::getContainer()->get(ScriptExecutor::class)->execute($hook);

        static::assertTrue($page->hasExtension('myProductAggregations'));
        $extension = $page->getExtension('myProductAggregations');
        static::assertInstanceOf(ArrayStruct::class, $extension);
        static::assertSame(600.0, $extension->get('sum'));
    }

    private function createProducts(): void
    {
        $product1 = (new ProductBuilder($this->ids, 'p1'))
            ->price(100)
            ->manufacturer('m1')
            ->variant(
                (new ProductBuilder($this->ids, 'v1.1'))
                ->build()
            );

        $product2 = (new ProductBuilder($this->ids, 'p2'))
            ->price(200)
            ->active(false);

        $product3 = (new ProductBuilder($this->ids, 'p3'))
            ->price(300);

        static::getContainer()->get('product.repository')->create([
            $product1->build(),
            $product2->build(),
            $product3->build(),
        ], Context::createDefaultContext());
    }

    private function installApp(string $appDir): ScriptAppInformation
    {
        $this->loadAppsFromDir($appDir);

        /** @var EntityRepository<AppCollection> $repository */
        $repository = static::getContainer()->get('app.repository');
        $app = $repository
            ->search(new Criteria(), Context::createDefaultContext())
            ->getEntities()
            ->first();

        static::assertNotNull($app);

        return new ScriptAppInformation(
            $app->getId(),
            $app->getName(),
            $app->getIntegrationId()
        );
    }

    private function createFacade(?ScriptAppInformation $scriptAppInformation = null): RepositoryFacade
    {
        return $this->factory->factory(
            new TestHook('test', Context::createDefaultContext()),
            new Script('test', '', new \DateTimeImmutable(), $scriptAppInformation)
        );
    }
}
