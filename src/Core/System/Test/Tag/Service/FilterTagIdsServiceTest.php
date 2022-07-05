<?php declare(strict_types=1);

namespace Shopware\Core\System\Test\Tag\Service;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Test\Customer\Rule\OrderFixture;
use Shopware\Core\Content\Test\Product\ProductBuilder;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexerRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\CountSorting;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Test\IdsCollection;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Tag\Service\FilterTagIdsService;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
class FilterTagIdsServiceTest extends TestCase
{
    use KernelTestBehaviour;
    use DatabaseTransactionBehaviour;
    use OrderFixture;

    private IdsCollection $ids;

    private FilterTagIdsService $filterTagIdsService;

    protected function setup(): void
    {
        $this->ids = new IdsCollection();
        $this->filterTagIdsService = $this->getContainer()->get(FilterTagIdsService::class);
    }

    public function testFilterIdsWithDuplicateFilter(): void
    {
        $this->prepareTestData();

        $request = new Request();
        $request->request->set('duplicateFilter', true);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('landingPages.id', null));
        $criteria->addSorting(new FieldSorting('categories.name', FieldSorting::ASCENDING));

        $filteredTagIdsStruct = $this->filterTagIdsService->filterIds(
            $request,
            $criteria,
            Context::createDefaultContext()
        );

        static::assertEquals(5, $filteredTagIdsStruct->getTotal());
        static::assertEquals(
            [
                $this->ids->get('a'),
                $this->ids->get('b'),
                $this->ids->get('c'),
                $this->ids->get('d'),
                $this->ids->get('e'),
            ],
            $filteredTagIdsStruct->getIds()
        );
    }

    public function testFilterIdsWithEmptyFilter(): void
    {
        $this->prepareTestData();

        $request = new Request();
        $request->request->set('emptyFilter', true);

        $criteria = new Criteria();
        $criteria->addSorting(new FieldSorting('name', FieldSorting::ASCENDING));

        $filteredTagIdsStruct = $this->filterTagIdsService->filterIds(
            $request,
            $criteria,
            Context::createDefaultContext()
        );

        static::assertEquals(2, $filteredTagIdsStruct->getTotal());
        static::assertEquals(
            [
                $this->ids->get('unassigned'),
                $this->ids->get('unique'),
            ],
            $filteredTagIdsStruct->getIds()
        );
    }

    public function testFilterIdsWithAggregatedSorting(): void
    {
        $this->prepareTestData();

        $criteria = new Criteria();
        $criteria->addFilter(new NotFilter(NotFilter::CONNECTION_AND, [
            new EqualsFilter('categories.id', null),
        ]));
        $criteria->addSorting(new CountSorting('categories.id', FieldSorting::DESCENDING));

        $filteredTagIdsStruct = $this->filterTagIdsService->filterIds(
            new Request(),
            $criteria,
            Context::createDefaultContext()
        );

        static::assertEquals(5, $filteredTagIdsStruct->getTotal());
        static::assertEquals(
            [
                $this->ids->get('e'),
                $this->ids->get('d'),
                $this->ids->get('c'),
                $this->ids->get('b'),
                $this->ids->get('a'),
            ],
            $filteredTagIdsStruct->getIds()
        );
    }

    public function testFilterIdsWithAggregatedSortingWithInheritedAndVersionized(): void
    {
        $versionContext = $this->prepareTestDataWithInheritedAndVersionized();

        $criteria = new Criteria();
        $criteria->addSorting(new CountSorting('products.id', FieldSorting::DESCENDING));

        (Context::createDefaultContext())->enableInheritance(function (Context $context) use ($criteria): void {
            $filteredTagIdsStruct = $this->filterTagIdsService->filterIds(
                new Request(),
                $criteria,
                $context
            );

            static::assertEquals(2, $filteredTagIdsStruct->getTotal());
            static::assertEquals(
                [
                    $this->ids->get('g'),
                    $this->ids->get('f'),
                ],
                $filteredTagIdsStruct->getIds()
            );
        });

        $criteria = new Criteria();
        $criteria->addSorting(new CountSorting('orders.id', FieldSorting::ASCENDING));

        $filteredTagIdsStruct = $this->filterTagIdsService->filterIds(
            new Request(),
            $criteria,
            $versionContext
        );

        static::assertEquals(2, $filteredTagIdsStruct->getTotal());
        static::assertEquals(
            [
                $this->ids->get('f'),
                $this->ids->get('g'),
            ],
            $filteredTagIdsStruct->getIds()
        );
    }

    public function testFilterIdsWithAssignmentFilter(): void
    {
        $this->prepareTestData();
        $this->prepareTestDataWithInheritedAndVersionized();

        $criteria = new Criteria();
        $context = Context::createDefaultContext();
        $request = new Request();

        $request->request->set('assignmentFilter', ['categories']);
        $filteredTagIdsStruct = $this->filterTagIdsService->filterIds($request, $criteria, $context);

        static::assertEquals(5, $filteredTagIdsStruct->getTotal());

        $request->request->set('assignmentFilter', ['categories', 'orders']);
        $filteredTagIdsStruct = $this->filterTagIdsService->filterIds($request, $criteria, $context);

        static::assertEquals(6, $filteredTagIdsStruct->getTotal());

        $request->request->set('assignmentFilter', ['categories', 'products']);
        $filteredTagIdsStruct = $this->filterTagIdsService->filterIds($request, $criteria, $context);

        static::assertEquals(7, $filteredTagIdsStruct->getTotal());

        $request->request->set('assignmentFilter', ['invalid']);
        $filteredTagIdsStruct = $this->filterTagIdsService->filterIds($request, $criteria, $context);

        static::assertEquals(9, $filteredTagIdsStruct->getTotal());
    }

    private function prepareTestData(): void
    {
        $tags = [
            [
                'id' => $this->ids->get('a'),
                'name' => 'foo',
                'categories' => $this->getCategoryPayload(1, 'a'),
            ],
            [
                'id' => $this->ids->get('b'),
                'name' => 'bar',
                'categories' => $this->getCategoryPayload(2, 'b'),
            ],
            [
                'id' => $this->ids->get('c'),
                'name' => 'foo',
                'categories' => $this->getCategoryPayload(3, 'c'),
            ],
            [
                'id' => $this->ids->get('unique'),
                'name' => 'unique',
            ],
            [
                'id' => $this->ids->get('unassigned'),
                'name' => 'unassigned',
            ],
            [
                'id' => $this->ids->get('d'),
                'name' => 'foo',
                'categories' => $this->getCategoryPayload(4, 'd'),
            ],
            [
                'id' => $this->ids->get('e'),
                'name' => 'bar',
                'categories' => $this->getCategoryPayload(5, 'e'),
            ],
        ];

        Context::createDefaultContext()->addState(EntityIndexerRegistry::DISABLE_INDEXING);
        $this->getContainer()->get('tag.repository')->create($tags, Context::createDefaultContext());
    }

    private function getCategoryPayload(int $count, string $name): array
    {
        $payload = [];

        for ($i = 0; $i < $count; ++$i) {
            $payload[] = ['name' => $name];
        }

        return $payload;
    }

    private function prepareTestDataWithInheritedAndVersionized(): Context
    {
        $context = Context::createDefaultContext();

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
                ->variant(
                    (new ProductBuilder($this->ids, 'v2.2'))
                        ->option('green', 'color')
                        ->build()
                )
                ->visibility()
                ->build(),
            (new ProductBuilder($this->ids, 'p3'))
                ->price(100)
                ->visibility()
                ->build(),
        ];

        $this->getContainer()->get('product.repository')
            ->create($products, $context);

        $tags = [
            [
                'id' => $this->ids->get('f'),
                'name' => 'foo',
                'products' => [
                    ['id' => $this->ids->get('p2')],
                ],
            ],
            [
                'id' => $this->ids->get('g'),
                'name' => 'bar',
                'products' => [
                    ['id' => $this->ids->get('p1')],
                    ['id' => $this->ids->get('p3')],
                ],
            ],
        ];

        $this->getContainer()->get('tag.repository')->create($tags, $context);

        $order = $this->getOrderData($this->ids->get('o1'), $context);
        $this->getContainer()->get('order.repository')->create($order, $context);

        $versionId = $this->getContainer()->get('order.repository')->createVersion(
            $this->ids->get('o1'),
            $context,
            Uuid::randomHex(),
            Uuid::randomHex()
        );

        $versionContext = (Context::createDefaultContext())->createWithVersionId($versionId);

        $orders = [
            [
                'id' => $this->ids->get('o1'),
                'tags' => [
                    ['id' => $this->ids->get('g')],
                ],
            ],
        ];

        $this->getContainer()->get('order.repository')->update($orders, $versionContext);

        return $versionContext;
    }
}
