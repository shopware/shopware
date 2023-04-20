<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\DataAbstractionLayer\Facade;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\Aggregate\ProductManufacturer\ProductManufacturerEntity;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Content\Test\Product\ProductBuilder;
use Shopware\Core\Framework\Api\Exception\MissingPrivilegeException;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Facade\RepositoryFacadeHookFactory;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\AggregationResultCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Metric\SumResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;
use Shopware\Core\Framework\Script\Execution\Script;
use Shopware\Core\Framework\Script\Execution\ScriptAppInformation;
use Shopware\Core\Framework\Script\Execution\ScriptExecutor;
use Shopware\Core\Framework\Struct\ArrayStruct;
use Shopware\Core\Framework\Test\IdsCollection;
use Shopware\Core\Framework\Test\Script\Execution\TestHook;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Tests\Integration\Core\Framework\App\AppSystemTestBehaviour;

/**
 * @internal
 */
class RepositoryFacadeTest extends TestCase
{
    use IntegrationTestBehaviour;
    use AppSystemTestBehaviour;

    private IdsCollection $ids;

    private RepositoryFacadeHookFactory $factory;

    protected function setUp(): void
    {
        $this->factory = $this->getContainer()->get(RepositoryFacadeHookFactory::class);
    }

    /**
     * @param array<string, array<int, mixed>> $criteria
     * @param callable(EntitySearchResult): void $expectation
     *
     * @dataProvider withoutAppTestCases
     */
    public function testWithoutApp(array $criteria, string $method, IdsCollection $ids, callable $expectation): void
    {
        $this->ids = $ids;
        $this->createProducts();

        $facade = $this->factory->factory(
            new TestHook('test', Context::createDefaultContext()),
            new Script('test', '', new \DateTimeImmutable())
        );

        $result = $facade->$method('product', $criteria); /* @phpstan-ignore-line */

        $expectation($result);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function withoutAppTestCases(): array
    {
        $ids = new IdsCollection();

        return [
            'testEmptySearch' => [
                [],
                'search',
                $ids,
                function (EntitySearchResult $result): void {
                    static::assertCount(4, $result);
                },
            ],
            'testSearchFilter' => [
                [
                    'filter' => [
                        ['type' => 'equals', 'field' => 'active', 'value' => true],
                    ],
                ],
                'search',
                $ids,
                function (EntitySearchResult $result) use ($ids): void {
                    static::assertCount(3, $result);
                    static::assertNotContains($ids->get('p2'), $result->getIds());
                },
            ],
            'testSearchRead' => [
                [
                    'ids' => [$ids->get('p1'), $ids->get('p2')],
                ],
                'search',
                $ids,
                function (EntitySearchResult $result) use ($ids): void {
                    static::assertCount(2, $result);
                    static::assertContains($ids->get('p1'), $result->getIds());
                    static::assertContains($ids->get('p2'), $result->getIds());
                },
            ],
            'testSearchAggregation' => [
                [
                    'aggregations' => [
                        ['name' => 'sum', 'type' => 'sum', 'field' => 'price.gross'],
                    ],
                ],
                'search',
                $ids,
                function (EntitySearchResult $result): void {
                    static::assertCount(4, $result);
                    $agg = $result->getAggregations()->get('sum');
                    static::assertInstanceOf(SumResult::class, $agg);
                    static::assertEquals(600, $agg->getSum());
                },
            ],
            'testSearchSort' => [
                [
                    'sort' => [['field' => 'id']],
                ],
                'search',
                $ids,
                function (EntitySearchResult $result): void {
                    $actual = $result->getIds();

                    $expected = $actual;
                    sort($expected);

                    static::assertEquals($expected, array_values($actual));
                },
            ],
            'testEmptySearchIds' => [
                [],
                'ids',
                $ids,
                function (IdSearchResult $result): void {
                    static::assertCount(4, $result->getIds());
                },
            ],
            'testSearchIdsFilter' => [
                [
                    'filter' => [
                        ['type' => 'equals', 'field' => 'active', 'value' => true],
                    ],
                ],
                'ids',
                $ids,
                function (IdSearchResult $result) use ($ids): void {
                    static::assertCount(3, $result->getIds());
                    static::assertNotContains($ids->get('p2'), $result->getIds());
                },
            ],
            'testEmptyAggregation' => [
                [],
                'aggregate',
                $ids,
                function (AggregationResultCollection $result): void {
                    static::assertCount(0, $result);
                },
            ],
            'testAggregation' => [
                [
                    'aggregations' => [
                        ['name' => 'sum', 'type' => 'sum', 'field' => 'price.gross'],
                    ],
                ],
                'aggregate',
                $ids,
                function (AggregationResultCollection $result): void {
                    static::assertCount(1, $result);
                    $agg = $result->get('sum');
                    static::assertInstanceOf(SumResult::class, $agg);
                    static::assertEquals(600, $agg->getSum());
                },
            ],
        ];
    }

    public function testWithApp(): void
    {
        $this->ids = new IdsCollection();
        $this->createProducts();

        $appInfo = $this->installApp(__DIR__ . '/_fixtures/apps/withProductPermission');

        $facade = $this->factory->factory(
            new TestHook('test', Context::createDefaultContext()),
            new Script('test', '', new \DateTimeImmutable(), $appInfo)
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
        static::assertEquals(600, $agg->getSum());
    }

    /**
     * @dataProvider withoutPermissionProvider
     */
    public function testWithAppWithoutNeededPermissions(string $method): void
    {
        $this->ids = new IdsCollection();
        $this->createProducts();

        $appInfo = $this->installApp(__DIR__ . '/_fixtures/apps/withoutProductPermission');

        $facade = $this->factory->factory(
            new TestHook('test', Context::createDefaultContext()),
            new Script('test', '', new \DateTimeImmutable(), $appInfo)
        );

        static::expectException(MissingPrivilegeException::class);
        $facade->$method('product', []); /* @phpstan-ignore-line */
    }

    /**
     * @return array<array<string>>
     */
    public static function withoutPermissionProvider(): array
    {
        return [
            ['search'],
            ['ids'],
            ['aggregate'],
        ];
    }

    public function testSearchByIdIntegration(): void
    {
        $this->ids = new IdsCollection();
        $this->createProducts();

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

        $this->getContainer()->get(ScriptExecutor::class)->execute($hook);

        static::assertTrue($page->hasExtension('myProduct'));
        $product = $page->getExtension('myProduct');
        static::assertInstanceOf(ProductEntity::class, $product);
        static::assertEquals($this->ids->get('p1'), $product->getId());
    }

    public function testSearchWithFilterIntegration(): void
    {
        $this->ids = new IdsCollection();
        $this->createProducts();

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

        $this->getContainer()->get(ScriptExecutor::class)->execute($hook);

        static::assertTrue($page->hasExtension('myProduct'));
        $product = $page->getExtension('myProduct');
        static::assertInstanceOf(ProductEntity::class, $product);
        static::assertEquals($this->ids->get('p1'), $product->getId());
    }

    public function testSearchWithAssociationIntegration(): void
    {
        $this->ids = new IdsCollection();
        $this->createProducts();

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

        $this->getContainer()->get(ScriptExecutor::class)->execute($hook);

        static::assertTrue($page->hasExtension('myProduct'));
        $product = $page->getExtension('myProduct');
        static::assertInstanceOf(ProductEntity::class, $product);
        static::assertEquals($this->ids->get('p1'), $product->getId());
        static::assertInstanceOf(ProductManufacturerEntity::class, $product->getManufacturer());

        $manufacturer = $page->getExtension('myManufacturer');
        static::assertInstanceOf(ProductManufacturerEntity::class, $manufacturer);
        static::assertEquals($this->ids->get('m1'), $manufacturer->getId());
    }

    public function testSearchIdsIntegration(): void
    {
        $this->ids = new IdsCollection();
        $this->createProducts();

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

        $this->getContainer()->get(ScriptExecutor::class)->execute($hook);

        static::assertTrue($page->hasExtension('myProductIds'));
        $extension = $page->getExtension('myProductIds');
        static::assertInstanceOf(ArrayStruct::class, $extension);
        static::assertEquals([$this->ids->get('p1')], $extension->get('ids'));
    }

    public function testAggregateIntegration(): void
    {
        $this->ids = new IdsCollection();
        $this->createProducts();

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

        $this->getContainer()->get(ScriptExecutor::class)->execute($hook);

        static::assertTrue($page->hasExtension('myProductAggregations'));
        $extension = $page->getExtension('myProductAggregations');
        static::assertInstanceOf(ArrayStruct::class, $extension);
        static::assertEquals(600, $extension->get('sum'));
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

        $this->getContainer()->get('product.repository')->create([
            $product1->build(),
            $product2->build(),
            $product3->build(),
        ], Context::createDefaultContext());
    }

    private function installApp(string $appDir): ScriptAppInformation
    {
        $this->loadAppsFromDir($appDir);

        /** @var AppEntity $app */
        $app = $this->getContainer()->get('app.repository')->search(new Criteria(), Context::createDefaultContext())->first();

        return new ScriptAppInformation(
            $app->getId(),
            $app->getName(),
            $app->getIntegrationId()
        );
    }
}
