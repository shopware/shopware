<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\DataAbstractionLayer\Facade;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\Content\Test\Product\ProductBuilder;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\ApiProtectionException;
use Shopware\Core\Framework\DataAbstractionLayer\Facade\SalesChannelRepositoryFacadeHookFactory;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\AggregationResultCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Metric\SumResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Script\Execution\Script;
use Shopware\Core\Framework\Script\Execution\ScriptExecutor;
use Shopware\Core\Framework\Test\App\AppSystemTestBehaviour;
use Shopware\Core\Framework\Test\IdsCollection;
use Shopware\Core\Framework\Test\Script\Execution\SalesChannelTestHook;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Test\TestDefaults;
use Shopware\Storefront\Page\Page;

class SalesChannelRepositoryFacadeTest extends TestCase
{
    use IntegrationTestBehaviour;
    use AppSystemTestBehaviour;

    private IdsCollection $ids;

    private SalesChannelRepositoryFacadeHookFactory $factory;

    private SalesChannelContext $context;

    public function setUp(): void
    {
        $this->factory = $this->getContainer()->get(SalesChannelRepositoryFacadeHookFactory::class);
        $this->context = $this->getContainer()->get(SalesChannelContextFactory::class)->create(Uuid::randomHex(), TestDefaults::SALES_CHANNEL);
    }

    /**
     * @dataProvider testCases
     */
    public function testFacade(array $criteria, string $method, IdsCollection $ids, callable $expectation): void
    {
        $this->ids = $ids;
        $this->createProducts();

        $facade = $this->factory->factory(
            new SalesChannelTestHook('test', $this->context),
            new Script('test', '', new \DateTimeImmutable(), null)
        );

        $result = $facade->$method('product', $criteria);

        $expectation($result);
    }

    public function testCases(): array
    {
        $ids = new IdsCollection();

        return [
            'testEmptySearch' => [
                [],
                'search',
                $ids,
                function (EntitySearchResult $result): void {
                    static::assertCount(3, $result);
                },
            ],
            'testSearchFilter' => [
                [
                    'filter' => [
                        ['type' => 'equals', 'field' => 'childCount', 'value' => 0],
                    ],
                ],
                'search',
                $ids,
                function (EntitySearchResult $result) use ($ids): void {
                    static::assertCount(1, $result);
                    static::assertContains($ids->get('p3'), $result->getIds());
                },
            ],
            'testSearchRead' => [
                [
                    'ids' => [$ids->get('p1'), $ids->get('p2')],
                ],
                'search',
                $ids,
                function (EntitySearchResult $result) use ($ids): void {
                    static::assertCount(1, $result);
                    static::assertContains($ids->get('p1'), $result->getIds());
                },
            ],
            'testSearchAggregation' => [
                [
                    'aggregations' => [
                        ['name' => 'sum', 'type' => 'sum', 'field' => 'childCount'],
                    ],
                ],
                'search',
                $ids,
                function (EntitySearchResult $result): void {
                    static::assertCount(3, $result);
                    $agg = $result->getAggregations()->get('sum');
                    static::assertInstanceOf(SumResult::class, $agg);
                    static::assertEquals(1, $agg->getSum());
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
                    static::assertCount(3, $result->getIds());
                },
            ],
            'testSearchIdsFilter' => [
                [
                    'filter' => [
                        ['type' => 'equals', 'field' => 'childCount', 'value' => 0],
                    ],
                ],
                'ids',
                $ids,
                function (IdSearchResult $result) use ($ids): void {
                    static::assertCount(1, $result->getIds());
                    static::assertContains($ids->get('p3'), $result->getIds());
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
                        ['name' => 'sum', 'type' => 'sum', 'field' => 'childCount'],
                    ],
                ],
                'aggregate',
                $ids,
                function (AggregationResultCollection $result): void {
                    static::assertCount(1, $result);
                    $agg = $result->get('sum');
                    static::assertInstanceOf(SumResult::class, $agg);
                    static::assertEquals(1, $agg->getSum());
                },
            ],
        ];
    }

    public function testIntegrationCase(): void
    {
        Feature::skipTestIfInActive('FEATURE_NEXT_17441', $this);

        $this->ids = new IdsCollection();
        $this->createProducts();

        $this->installApp(__DIR__ . '/_fixtures/apps/pageLoadedExample');

        $page = new Page();
        $hook = new SalesChannelTestHook(
            'store-search',
            $this->context,
            [
                'productId' => $this->ids->get('p1'),
                'page' => $page,
            ],
            [
                SalesChannelRepositoryFacadeHookFactory::class,
            ]
        );

        $this->getContainer()->get(ScriptExecutor::class)->execute($hook);

        static::asserttrue($page->hasExtension('myProduct'));
        $product = $page->getExtension('myProduct');
        static::assertInstanceOf(SalesChannelProductEntity::class, $product);
        static::assertEquals($this->ids->get('p1'), $product->getId());
    }

    public function testItThrowsForNotApiAwareField(): void
    {
        $this->ids = new IdsCollection();
        $this->createProducts();

        $facade = $this->factory->factory(
            new SalesChannelTestHook('test', $this->context),
            new Script('test', '', new \DateTimeImmutable(), null)
        );

        $criteria = [
            'aggregations' => [
                ['name' => 'sum', 'type' => 'sum', 'field' => 'price.gross'],
            ],
        ];

        static::expectException(ApiProtectionException::class);
        $facade->search('product', $criteria);
    }

    private function createProducts(): void
    {
        $taxId = $this->getExistingTaxId();
        $this->ids->set('t1', $taxId);

        $product1 = (new ProductBuilder($this->ids, 'p1'))
            ->price(100)
            ->visibility()
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

        $this->getContainer()->get('product.repository')->create([
            $product1->build(),
            $product2->build(),
            $product3->build(),
        ], $this->context->getContext());
    }

    private function installApp(string $appDir): string
    {
        $this->loadAppsFromDir($appDir);

        /** @var string $appId */
        $appId = $this->getContainer()->get('app.repository')->searchIds(new Criteria(), Context::createDefaultContext())->firstId();

        return $appId;
    }

    private function getExistingTaxId(): string
    {
        /** @var EntityRepositoryInterface $taxRepository */
        $taxRepository = $this->getContainer()->get('tax.repository');

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('name', 'Standard rate'));

        /** @var string $taxId */
        $taxId = $taxRepository->searchIds($criteria, $this->context->getContext())->firstId();

        return $taxId;
    }
}
