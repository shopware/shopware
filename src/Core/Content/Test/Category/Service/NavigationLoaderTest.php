<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Category\Service;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Test\Cart\Common\Generator;
use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Content\Category\Exception\CategoryNotFoundException;
use Shopware\Core\Content\Category\SalesChannel\NavigationRoute;
use Shopware\Core\Content\Category\Service\NavigationLoader;
use Shopware\Core\Content\Category\Service\NavigationLoaderInterface;
use Shopware\Core\Content\Category\Tree\TreeItem;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\Test\IdsCollection;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseHelper\ReflectionHelper;
use Shopware\Core\Framework\Test\TestDataCollection;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\EventDispatcher\EventDispatcher;
use function array_map;
use function array_values;

class NavigationLoaderTest extends TestCase
{
    use IntegrationTestBehaviour;

    private EntityRepositoryInterface $repository;

    private NavigationLoaderInterface $navigationLoader;

    public function setUp(): void
    {
        $this->repository = $this->getContainer()->get('category.repository');

        $this->ids = new IdsCollection();

        $this->navigationLoader = $this->getContainer()->get(NavigationLoader::class);
    }

    public function testTreeBuilderWithSimpleTree(): void
    {
        $loader = new NavigationLoader(
            $this->createMock(EventDispatcher::class),
            $this->createMock(NavigationRoute::class)
        );

        /** @var TreeItem[] $treeItems */
        $treeItems = ReflectionHelper::getMethod(NavigationLoader::class, 'buildTree')->invoke($loader, '1', $this->createSimpleTree());

        static::assertCount(3, $treeItems);
        static::assertCount(2, $treeItems['1.1']->getChildren());
        static::assertCount(0, $treeItems['1.1']->getChildren()['1.1.1']->getChildren());
        static::assertCount(0, $treeItems['1.1']->getChildren()['1.1.2']->getChildren());
        static::assertCount(2, $treeItems['1.2']->getChildren());
        static::assertCount(1, $treeItems['1.2']->getChildren()['1.2.1']->getChildren());
        static::assertCount(1, $treeItems['1.2']->getChildren()['1.2.2']->getChildren());
        static::assertCount(0, $treeItems['1.3']->getChildren());
    }

    public function testLoadActiveAndRootCategoryAreSame(): void
    {
        $this->createCategoryTree();

        $context = Generator::createSalesChannelContext();
        $context->getSalesChannel()->setNavigationCategoryId($this->ids->get('rootId'));

        $tree = $this->navigationLoader->load($this->ids->get('category1'), $context, $this->ids->get('category1'));

        static::assertSame($this->ids->get('category1'), $tree->getActive()->getId());
    }

    public function testLoadChildOfRootCategory(): void
    {
        $this->createCategoryTree();
        $context = Generator::createSalesChannelContext();
        $context->getSalesChannel()->setNavigationCategoryId($this->ids->get('rootId'));

        $tree = $this->navigationLoader->load($this->ids->get('category1_1'), $context, $this->ids->get('category1'));
        static::assertSame($this->ids->get('category1_1'), $tree->getActive()->getId());
    }

    public function testLoadCategoryNotFound(): void
    {
        static::expectException(CategoryNotFoundException::class);
        $this->navigationLoader->load(Uuid::randomHex(), Generator::createSalesChannelContext(), Uuid::randomHex());
    }

    public function testLoadNotChildOfRootCategoryThrowsException(): void
    {
        $this->createCategoryTree();

        static::expectException(CategoryNotFoundException::class);
        $this->navigationLoader->load($this->ids->get('category2_1'), Generator::createSalesChannelContext(), $this->ids->get('category1'));
    }

    public function testLoadParentOfRootCategoryThrowsException(): void
    {
        $this->createCategoryTree();

        static::expectException(CategoryNotFoundException::class);
        $this->navigationLoader->load($this->ids->get('rootId'), Generator::createSalesChannelContext(), $this->ids->get('category1'));
    }

    public function testLoadDeepNestedTree(): void
    {
        $category1_1_1Id = Uuid::randomHex();
        $category1_1_1_1Id = Uuid::randomHex();

        $this->createCategoryTree();
        $this->repository->upsert([
            [
                'id' => $category1_1_1Id,
                'parentId' => $this->ids->get('category1_1'),
                'name' => 'category 1.1.1',
                'children' => [
                    [
                        'id' => $category1_1_1_1Id,
                        'name' => 'category 1.1.1.1',
                    ],
                ],
            ],
        ], Context::createDefaultContext());

        $context = Generator::createSalesChannelContext();
        $context->getSalesChannel()->setNavigationCategoryId($this->ids->get('rootId'));

        $tree = $this->navigationLoader->load($category1_1_1_1Id, $context, $this->ids->get('rootId'));

        static::assertNotNull($tree->getChildren($category1_1_1Id));
    }

    public function testLoadDifferentDepth(): void
    {
        $data = new TestDataCollection(Context::createDefaultContext());
        $categories = [
            [
                'id' => $data->create('root'), 'name' => 'root', 'children' => [
                    [
                        'id' => $data->create('a'), 'name' => 'a', 'children' => [
                            [
                                'id' => $data->create('b'), 'name' => 'b', 'children' => [
                                    [
                                        'id' => $data->create('c'), 'name' => 'c', 'children' => [
                                            [
                                                'id' => $data->create('d'), 'name' => 'd', 'children' => [
                                                    ['id' => $data->create('e'), 'name' => 'e'],
                                                ],
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $this->repository->create($categories, $data->getContext());

        $context = Generator::createSalesChannelContext();
        $context->getSalesChannel()->setNavigationCategoryId($data->get('root'));

        $tree = $this->navigationLoader->load(
            $data->get('root'),
            $context,
            $data->get('root'),
            3
        );

        static::assertSame($data->get('root'), $tree->getActive()->getId());
        static::assertCount(1, $tree->getChildren($data->get('root'))->getTree());
        static::assertCount(1, $tree->getChildren($data->get('a'))->getTree());
        static::assertCount(1, $tree->getChildren($data->get('b'))->getTree());
        static::assertCount(0, $tree->getChildren($data->get('c'))->getTree());

        $tree = $this->navigationLoader->load(
            $data->get('root'),
            $context,
            $data->get('root'),
            4
        );

        static::assertSame($data->get('root'), $tree->getActive()->getId());
        static::assertCount(1, $tree->getChildren($data->get('root'))->getTree());
        static::assertCount(1, $tree->getChildren($data->get('a'))->getTree());
        static::assertCount(1, $tree->getChildren($data->get('b'))->getTree());
        static::assertCount(1, $tree->getChildren($data->get('c'))->getTree());
        static::assertCount(0, $tree->getChildren($data->get('d'))->getTree());
    }

    public function testChildrenSortingTest(): void
    {
        $this->createCategoryTree();

        $context = Generator::createSalesChannelContext();
        $context->getSalesChannel()->setNavigationCategoryId($this->ids->get('rootId'));

        $tree = $this->navigationLoader->load($this->ids->get('rootId'), $context, $this->ids->get('rootId'));

        $elements = array_values(array_map(static function (TreeItem $item) {
            return $item->getCategory()->getName();
        }, $tree->getChildren($this->ids->get('category3'))->getTree()));

        static::assertSame('Category 3.1', $elements[0]);
        static::assertSame('Category 3.3', $elements[1]);
        static::assertSame('Category 3.2', $elements[2]);
    }

    private function createSimpleTree(): array
    {
        return [
            new TestTreeAware('1.1', '1'),
            new TestTreeAware('1.1.1', '1.1'),
            new TestTreeAware('1.1.2', '1.1'),
            new TestTreeAware('1.2', '1'),
            new TestTreeAware('1.2.1', '1.2'),
            new TestTreeAware('1.2.1.1', '1.2.1'),
            new TestTreeAware('1.2.2', '1.2'),
            new TestTreeAware('1.2.2.1', '1.2.2'),
            new TestTreeAware('1.3', '1'),
        ];
    }

    private function createCategoryTree(): void
    {
        $this->repository->upsert([
            [
                'id' => $this->ids->get('rootId'),
                'name' => 'root',
                'children' => [
                    [
                        'id' => $this->ids->get('category1'),
                        'name' => 'Category 1',
                        'children' => [
                            [
                                'id' => $this->ids->get('category1_1'),
                                'name' => 'Category 1.1',
                            ],
                        ],
                    ],
                    [
                        'id' => $this->ids->get('category2'),
                        'name' => 'Category 2',
                        'children' => [
                            [
                                'id' => $this->ids->get('category2_1'),
                                'name' => 'Category 2.1',
                            ],
                        ],
                    ],
                    [
                        'id' => $this->ids->get('category3'),
                        'name' => 'Category 3',
                        'children' => [
                            [
                                'id' => $this->ids->get('category3_1'),
                                'name' => 'Category 3.1',
                            ],
                            [
                                'id' => $this->ids->get('category3_3'),
                                'name' => 'Category 3.3',
                            ],
                            [
                                'id' => $this->ids->get('category3_2'),
                                'name' => 'Category 3.2',
                            ],
                        ],
                    ],
                ],
            ],
        ], Context::createDefaultContext());

        $this->repository->update([
            [
                'id' => $this->ids->get('category3_3'),
                'afterCategoryId' => $this->ids->get('category3_1'),
            ],
            [
                'id' => $this->ids->get('category3_2'),
                'afterCategoryId' => $this->ids->get('category3_3'),
            ],
        ], Context::createDefaultContext());
    }
}

class TestTreeAware extends CategoryEntity
{
    public function __construct(string $id, string $parentId)
    {
        $this->id = $id;
        $this->parentId = $parentId;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getActive(): bool
    {
        return true;
    }

    public function getVisible(): bool
    {
        return true;
    }

    public function getPath(): ?string
    {
        throw new \Exception('Should not be called');
    }

    public function getParentId(): ?string
    {
        return $this->parentId;
    }

    public function getUniqueIdentifier(): string
    {
        return $this->getId();
    }
}
