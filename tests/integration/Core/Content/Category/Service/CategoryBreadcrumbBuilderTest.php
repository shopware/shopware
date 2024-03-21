<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Content\Category\Service;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Category\CategoryCollection;
use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Content\Category\Service\CategoryBreadcrumbBuilder;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Context\SystemSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\AdminApiTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelApiTestBehaviour;
use Shopware\Core\Framework\Test\TestDataCollection;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Context\AbstractSalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

/**
 * @internal
 */
#[Package('inventory')]
class CategoryBreadcrumbBuilderTest extends TestCase
{
    use AdminApiTestBehaviour;
    use IntegrationTestBehaviour;
    use SalesChannelApiTestBehaviour;

    private EntityRepository $categoryRepository;

    private SalesChannelContext $salesChannelContext;

    private TestDataCollection $ids;

    private string $deLanguageId;

    private CategoryBreadcrumbBuilder $breadcrumbBuilder;

    private EntityRepository $productRepository;

    private KernelBrowser $browser;

    private AbstractSalesChannelContextFactory $contextFactory;

    protected function setUp(): void
    {
        $this->ids = new TestDataCollection();
        $this->deLanguageId = $this->getDeDeLanguageId();
        $this->breadcrumbBuilder = $this->getContainer()->get(CategoryBreadcrumbBuilder::class);

        $this->browser = $this->createCustomSalesChannelBrowser([
            'id' => $this->ids->create('sales-channel'),
            'navigationCategoryId' => $this->createTestData('navigation'),
            'serviceCategoryId' => $this->createTestData('service'),
            'footerCategoryId' => $this->createTestData('footer'),
        ]);

        $this->assignSalesChannelContext($this->browser);

        $this->categoryRepository = $this->getContainer()->get('category.repository');
        $this->productRepository = $this->getContainer()->get('product.repository');

        $this->contextFactory = $this->getContainer()->get(SalesChannelContextFactory::class);
        $this->salesChannelContext = $this->contextFactory->create('', $this->ids->get('sales-channel'));
    }

    /**
     * @return iterable<array<string|bool>>
     */
    public static function breadcrumbDataProvider(): iterable
    {
        yield ['navigation', true, false];
        yield ['navigation', true, true];
        yield ['navigation', false, true];

        yield ['service', true, false];
        yield ['service', true, true];
        yield ['service', false, true];

        yield ['footer', true, false];
        yield ['footer', true, true];
        yield ['footer', false, true];
    }

    /**
     * @return iterable<array<bool>>
     */
    public static function seoCategoryProvider(): iterable
    {
        yield [false, false, false];
        yield [false, false, false];
        yield [false, false, true];
        yield [false, true, false];
        yield [false, true, true];
        yield [true, false, false];
        yield [true, false, true];
        yield [true, true, false];
        yield [true, true, true];
    }

    #[DataProvider('breadcrumbDataProvider')]
    #[Group('slow')]
    public function testIsWithoutEntrypoint(string $key, bool $withSalesChannel, bool $withCategoryId = false): void
    {
        $categoryId = $withCategoryId ? $this->ids->get($key) : null;
        $salesChannel = $withSalesChannel ? $this->salesChannelContext->getSalesChannel() : null;

        /** @var CategoryCollection $categories */
        $categories = $this->categoryRepository->search(new Criteria($this->ids->prefixed($key)), new Context(
            new SystemSource(),
            [],
            Defaults::CURRENCY,
            [Defaults::LANGUAGE_SYSTEM]
        ))->getEntities();

        $category1 = $categories->get($this->ids->get($key));
        $category2 = $categories->get($this->ids->get($key . '-a-1'));
        $category3 = $categories->get($this->ids->get($key . '-a-2'));
        static::assertInstanceOf(CategoryEntity::class, $category1);
        static::assertInstanceOf(CategoryEntity::class, $category2);
        static::assertInstanceOf(CategoryEntity::class, $category3);

        $result1 = $this->breadcrumbBuilder->build($category1, $salesChannel, $categoryId);
        $result2 = $this->breadcrumbBuilder->build($category2, $salesChannel, $categoryId);
        $result3 = $this->breadcrumbBuilder->build($category3, $salesChannel, $categoryId);

        static::assertIsArray($result1);
        static::assertIsArray($result2);
        static::assertIsArray($result3);
        static::assertCount(0, $result1);
        static::assertSame(['EN-A'], array_values($result2));
        static::assertSame(['EN-A', 'EN-AA'], array_values($result3));

        /** @var CategoryCollection $categories */
        $categories = $this->categoryRepository->search(new Criteria($this->ids->prefixed($key)), new Context(
            new SystemSource(),
            [],
            Defaults::CURRENCY,
            [$this->deLanguageId]
        ))->getEntities();

        $category1 = $categories->get($this->ids->get($key));
        $category2 = $categories->get($this->ids->get($key . '-a-1'));
        $category3 = $categories->get($this->ids->get($key . '-a-2'));
        static::assertInstanceOf(CategoryEntity::class, $category1);
        static::assertInstanceOf(CategoryEntity::class, $category2);
        static::assertInstanceOf(CategoryEntity::class, $category3);

        $result1 = $this->breadcrumbBuilder->build($category1, $salesChannel, $categoryId);
        $result2 = $this->breadcrumbBuilder->build($category2, $salesChannel, $categoryId);
        $result3 = $this->breadcrumbBuilder->build($category3, $salesChannel, $categoryId);

        static::assertIsArray($result1);
        static::assertIsArray($result2);
        static::assertIsArray($result3);
        static::assertCount(0, $result1);
        static::assertSame(['DE-A'], array_values($result2));
        static::assertSame(['DE-A', 'DE-AA'], array_values($result3));
    }

    #[DataProvider('seoCategoryProvider')]
    #[Group('slow')]
    public function testItHasSeoCategory(bool $hasCategories, bool $hasMainCategory, bool $hasMainCategory2ndChannel): void
    {
        $this->createTestData('navigation-sc2');
        $this->createSalesChannel([
            'id' => $this->ids->create('sales-channel-2'),
            'navigationCategoryId' => $this->ids->get('navigation-sc2'),
            'domains' => [
                [
                    'languageId' => Defaults::LANGUAGE_SYSTEM,
                    'currencyId' => Defaults::CURRENCY,
                    'snippetSetId' => $this->getSnippetSetIdForLocale('en-GB'),
                    'url' => 'http://test.to',
                ],
            ],
        ]);

        $productData = [
            [
                'id' => $this->ids->get('seo-product'),
                'active' => true,
                'weight' => 999,
                'visibilities' => [
                    ['salesChannelId' => $this->ids->get('sales-channel-2'), 'visibility' => ProductVisibilityDefinition::VISIBILITY_ALL],
                ],
            ],
        ];

        if ($hasCategories) {
            $productData[0]['categories'] = [
                ['id' => $this->ids->get('navigation-a-1')],
                ['id' => $this->ids->get('navigation-a-2')],
                ['id' => $this->ids->get('navigation-sc2-a-1')],
                ['id' => $this->ids->get('navigation-sc2-a-2')],
            ];
        }

        if ($hasMainCategory) {
            $productData[0]['mainCategories'][] = [
                'categoryId' => $this->ids->get('navigation-a-1'),
                'id' => Uuid::randomHex(),
                'salesChannelId' => $this->salesChannelContext->getSalesChannel()->getId(),
            ];
        }

        if ($hasMainCategory2ndChannel) {
            $productData[0]['mainCategories'][] = [
                'categoryId' => $this->ids->get('navigation-sc2-a-1'),
                'id' => Uuid::randomHex(),
                'salesChannelId' => $this->ids->get('sales-channel-2'),
            ];
        }

        $this->createTestProduct($productData);

        $criteria = new Criteria([$this->ids->get('seo-product')]);
        $criteria->addAssociation('categories');

        /** @var ProductEntity $product */
        $product = $this->productRepository->search($criteria, Context::createDefaultContext())->first();

        $this->createProductStreams();
        $this->createCategoryStreams();
        $product->setStreamIds([$this->ids->create('stream_id_1')]);

        $category = $this->breadcrumbBuilder->getProductSeoCategory($product, $this->salesChannelContext);
        $category2 = $this->breadcrumbBuilder->getProductSeoCategory($product, $this->contextFactory->create('', $this->ids->get('sales-channel-2')));

        static::assertInstanceOf(CategoryCollection::class, $product->getCategories());

        if ($hasCategories) {
            static::assertInstanceOf(CategoryEntity::class, $category);
            static::assertInstanceOf(CategoryEntity::class, $category2);
            static::assertNotCount(0, $product->getCategories());

            if ($hasMainCategory) {
                static::assertSame($this->ids->get('navigation-a-1'), $category->getId());
            } else {
                static::assertTrue(\in_array($category->getId(), $this->ids->prefixed('navigation-a'), true));
            }

            if ($hasMainCategory2ndChannel) {
                static::assertSame($this->ids->get('navigation-sc2-a-1'), $category2->getId());
            } else {
                static::assertTrue(\in_array($category2->getId(), $this->ids->prefixed('navigation-sc2-a'), true));
            }
        } else {
            static::assertCount(0, $product->getCategories());
            static::assertNull($category);
            static::assertNull($category2);
        }
    }

    #[Group('slow')]
    public function testApiResponseHasSeoCategory(): void
    {
        $this->createTestData('navigation-test');
        $this->createProductStreams();
        $this->createCategoryStreams();

        $productId = $this->ids->get('pid');
        $this->createTestProduct([
            [
                'id' => $productId,
            ],
        ]);

        $this->getBrowser()->request('PATCH', '/api/product/' . $productId, [
            'categories' => [
                ['id' => $this->ids->get('navigation-a-2')],
                ['id' => $this->ids->get('navigation-test-a-2')],
            ],
        ]);
        $response = $this->getBrowser()->getResponse();
        static::assertIsString($response->getContent());
        static::assertEquals(204, $response->getStatusCode(), $response->getContent());

        $this->updateProductStream($productId, $this->ids->create('stream_id_1'));

        $this->browser->request('POST', '/store-api/product/' . $productId);
        $response = $this->browser->getResponse();
        static::assertIsString($response->getContent());
        static::assertSame(200, $response->getStatusCode());

        $json = json_decode($response->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertNotEmpty($json);
        static::assertArrayHasKey('product', $json);
        static::assertArrayHasKey('seoCategory', $json['product']);
        static::assertNotCount(0, $json['product']['seoCategory']);
        static::assertEquals($this->ids->get('navigation-a-2'), $json['product']['seoCategory']['id']);
    }

    #[Group('slow')]
    public function testSeoCategoryInheritance(): void
    {
        $optionIds = [
            'red' => Uuid::randomHex(),
            'green' => Uuid::randomHex(),
            'blue' => Uuid::randomHex(),
            'yellow' => Uuid::randomHex(),
        ];
        $groupId = Uuid::randomHex();

        $this->createTestProduct([
            [
                'id' => $this->ids->get('variant-product'),
                'categories' => [
                    ['id' => $this->ids->get('navigation-a-2')],
                    ['id' => $this->ids->get('navigation-b-1')],
                    ['id' => $this->ids->get('navigation-b-2')],
                ],
                'mainCategories' => [
                    [
                        'categoryId' => $this->ids->get('navigation-a-2'),
                        'id' => Uuid::randomHex(),
                        'salesChannelId' => $this->ids->get('sales-channel'),
                    ],
                ],
                'configuratorGroupConfig' => [
                    [
                        'id' => $groupId,
                        'expressionForListings' => true,
                        'representation' => 'box',
                    ],
                ],
                'configuratorSettings' => [
                    [
                        'option' => [
                            'id' => $optionIds['red'],
                            'name' => 'Red',
                            'group' => [
                                'id' => $groupId,
                                'name' => 'Color',
                            ],
                        ],
                    ],
                    [
                        'option' => [
                            'id' => $optionIds['green'],
                            'name' => 'Green',
                            'group' => [
                                'id' => $groupId,
                                'name' => 'Color',
                            ],
                        ],
                    ],
                    [
                        'option' => [
                            'id' => $optionIds['blue'],
                            'name' => 'Blue',
                            'group' => [
                                'id' => $groupId,
                                'name' => 'Color',
                            ],
                        ],
                    ],
                    [
                        'option' => [
                            'id' => $optionIds['yellow'],
                            'name' => 'Yellow',
                            'group' => [
                                'id' => $groupId,
                                'name' => 'Color',
                            ],
                        ],
                    ],
                ],
            ],
            [
                'id' => $this->ids->get('variant-product-1'),
                'mainCategories' => [
                    [
                        'categoryId' => $this->ids->get('navigation-b-2'),
                        'id' => Uuid::randomHex(),
                        'salesChannelId' => $this->ids->get('sales-channel'),
                    ],
                ],
                'productNumber' => 'v-1',
                'stock' => 1,
                'active' => true,
                'parentId' => $this->ids->get('variant-product'),
                'options' => [
                    ['id' => $optionIds['red']],
                ],
            ],
            [
                'id' => $this->ids->get('variant-product-2'),
                'mainCategories' => [
                    [
                        'categoryId' => $this->ids->get('navigation-b-1'),
                        'id' => Uuid::randomHex(),
                        'salesChannelId' => $this->ids->get('sales-channel'),
                    ],
                ],
                'productNumber' => 'v-2',
                'stock' => 1,
                'active' => true,
                'parentId' => $this->ids->get('variant-product'),
                'options' => [
                    ['id' => $optionIds['green']],
                ],
            ],
            [
                'id' => $this->ids->get('variant-product-3'),
                'categories' => [
                    ['id' => $this->ids->get('navigation-a-1')],
                ],
                'productNumber' => 'v-3',
                'stock' => 1,
                'active' => true,
                'parentId' => $this->ids->get('variant-product'),
                'options' => [
                    ['id' => $optionIds['blue']],
                ],
            ],
            [
                'id' => $this->ids->get('variant-product-4'),
                'categories' => [
                    ['id' => $this->ids->get('navigation-a-2')],
                    ['id' => $this->ids->get('navigation-b-1')],
                ],
                'productNumber' => 'v-4',
                'stock' => 1,
                'active' => true,
                'parentId' => $this->ids->get('variant-product'),
                'options' => [
                    ['id' => $optionIds['yellow']],
                ],
            ],
        ], false);

        $this->updateProductStream($this->ids->get('variant-product-3'), $this->ids->get('stream_id_1'));

        /** @var ProductEntity $mainProduct */
        $mainProduct = $this->productRepository->search($this->createSeoCriteria([$this->ids->get('variant-product')]), Context::createDefaultContext())->first();
        $categoryMain = $this->breadcrumbBuilder->getProductSeoCategory($mainProduct, $this->salesChannelContext);

        static::assertInstanceOf(CategoryEntity::class, $categoryMain);
        static::assertSame($this->ids->get('navigation-a-2'), $categoryMain->getId());
        static::assertSame('EN-AA', $categoryMain->getName());

        /** @var ProductEntity $variant1 */
        $variant1 = $this->productRepository->search($this->createSeoCriteria([$this->ids->get('variant-product-1')]), Context::createDefaultContext())->first();
        $categoryVariant1 = $this->breadcrumbBuilder->getProductSeoCategory($variant1, $this->salesChannelContext);

        static::assertInstanceOf(CategoryEntity::class, $categoryVariant1);
        static::assertSame($this->ids->get('navigation-b-2'), $categoryVariant1->getId());
        static::assertSame('EN-BA', $categoryVariant1->getName());

        /** @var ProductEntity $variant2 */
        $variant2 = $this->productRepository->search($this->createSeoCriteria([$this->ids->get('variant-product-2')]), Context::createDefaultContext())->first();
        $categoryVariant2 = $this->breadcrumbBuilder->getProductSeoCategory($variant2, $this->salesChannelContext);

        static::assertInstanceOf(CategoryEntity::class, $categoryVariant2);
        static::assertSame($this->ids->get('navigation-b-1'), $categoryVariant2->getId());
        static::assertSame('EN-B', $categoryVariant2->getName());

        /** @var ProductEntity $variant3 */
        $variant3 = $this->productRepository->search($this->createSeoCriteria([$this->ids->get('variant-product-3')]), Context::createDefaultContext())->first();
        $categoryVariant3 = $this->breadcrumbBuilder->getProductSeoCategory($variant3, $this->salesChannelContext);

        static::assertInstanceOf(CategoryEntity::class, $categoryVariant3);
        static::assertSame($this->ids->get('navigation-a-1'), $categoryVariant3->getId());
        static::assertSame('EN-A', $categoryVariant3->getName());

        /** @var ProductEntity $variant4 */
        $variant4 = $this->productRepository->search($this->createSeoCriteria([$this->ids->get('variant-product-4')]), Context::createDefaultContext())->first();
        $categoryVariant4 = $this->breadcrumbBuilder->getProductSeoCategory($variant4, $this->salesChannelContext);

        static::assertInstanceOf(CategoryEntity::class, $categoryVariant4);
        static::assertSame($this->ids->get('navigation-a-2'), $categoryVariant4->getId());
        static::assertSame('EN-AA', $categoryVariant4->getName());
    }

    #[Group('slow')]
    public function testGetProductSeoCategoryWithInactiveCategory(): void
    {
        // create and retrieve product and categories
        $productData = [
            [
                'id' => $this->ids->get('seo-product'),
                'categories' => [
                    ['id' => $this->ids->get('navigation-a-1')],
                    ['id' => $this->ids->get('navigation-a-2')],
                ],
            ],
        ];
        $this->createTestProduct($productData);

        $this->updateProductStream($this->ids->get('seo-product'), $this->ids->get('stream_id_1'));

        $criteria = new Criteria([$this->ids->get('seo-product')]);
        $criteria->addAssociation('categories');
        /** @var ProductEntity $product */
        $product = $this->productRepository->search($criteria, Context::createDefaultContext())->first();

        // test if you get at least one category if both are active
        $seoCategory = $this->breadcrumbBuilder->getProductSeoCategory($product, $this->salesChannelContext);
        static::assertInstanceOf(CategoryEntity::class, $seoCategory);
        static::assertTrue(\in_array($seoCategory->getId(), $this->ids->prefixed('navigation-a'), true));

        // test if you only get the active category
        $this->categoryRepository->update(
            [[
                'id' => $this->ids->get('navigation-a-2'),
                'active' => false,
            ]],
            Context::createDefaultContext()
        );
        $seoCategory = $this->breadcrumbBuilder->getProductSeoCategory($product, $this->salesChannelContext);
        static::assertInstanceOf(CategoryEntity::class, $seoCategory);
        static::assertSame($this->ids->get('navigation-a-1'), $seoCategory->getId());
    }

    /**
     * @param array<string> $ids
     */
    private function createSeoCriteria(array $ids): Criteria
    {
        $criteria = new Criteria($ids);
        $criteria->addAssociation('mainCategories.category');

        return $criteria;
    }

    private function createTestData(string $key): string
    {
        $data = [
            [
                'id' => $this->ids->create($key),
                'translations' => [
                    ['name' => 'EN-Entry', 'languageId' => Defaults::LANGUAGE_SYSTEM],
                    ['name' => 'DE-Entry', 'languageId' => $this->deLanguageId],
                ],
                'children' => [
                    [
                        'id' => $this->ids->create($key . '-a-1'),
                        'translations' => [
                            ['name' => 'EN-A', 'languageId' => Defaults::LANGUAGE_SYSTEM],
                            ['name' => 'DE-A', 'languageId' => $this->deLanguageId],
                        ],
                        'children' => [
                            [
                                'id' => $this->ids->create($key . '-a-2'),
                                'translations' => [
                                    ['name' => 'EN-AA', 'languageId' => Defaults::LANGUAGE_SYSTEM],
                                    ['name' => 'DE-AA', 'languageId' => $this->deLanguageId],
                                ],
                            ],
                        ],
                    ],
                    [
                        'id' => $this->ids->create($key . '-b-1'),
                        'translations' => [
                            ['name' => 'EN-B', 'languageId' => Defaults::LANGUAGE_SYSTEM],
                            ['name' => 'DE-B', 'languageId' => $this->deLanguageId],
                        ],
                        'children' => [
                            [
                                'id' => $this->ids->create($key . '-b-2'),
                                'translations' => [
                                    ['name' => 'EN-BA', 'languageId' => Defaults::LANGUAGE_SYSTEM],
                                    ['name' => 'DE-BA', 'languageId' => $this->deLanguageId],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $this->getContainer()->get('category.repository')->create($data, Context::createDefaultContext());

        return $this->ids->get($key);
    }

    /**
     * @param array<mixed> $products
     */
    private function createTestProduct(array $products = [], bool $fillAll = true): void
    {
        $basicPayload = [
            'id' => Uuid::randomHex(),
            'name' => 'foo bar',
            'manufacturer' => [
                'id' => Uuid::randomHex(),
                'name' => 'amazing brand',
            ],
            'productNumber' => 'P1234',
            'tax' => ['id' => Uuid::randomHex(), 'taxRate' => 19, 'name' => 'tax'],
            'price' => [
                [
                    'currencyId' => Defaults::CURRENCY,
                    'gross' => 10,
                    'net' => 12,
                    'linked' => false,
                ],
            ],
            'stock' => 0,
            'weight' => 998,
            'active' => true,
            'visibilities' => [
                ['salesChannelId' => $this->ids->get('sales-channel'), 'visibility' => ProductVisibilityDefinition::VISIBILITY_ALL],
            ],
        ];

        if ($fillAll) {
            foreach ($products as $k => $v) {
                $products[$k] = array_merge($basicPayload, $v);
            }
        } else {
            $products[0] = array_merge($basicPayload, $products[0]);
        }

        $this->productRepository->create($products, $this->salesChannelContext->getContext());
    }

    private function createProductStreams(): void
    {
        $stream = [
            'id' => $this->ids->create('stream_id_1'),
            'name' => 'test',
            'filters' => [
                [
                    'type' => 'equals',
                    'field' => 'weight',
                    'value' => '999',
                    'parameters' => [
                        'operator' => 'eq',
                    ],
                ],
            ],
        ];

        $productStreamsRepository = $this->getContainer()->get('product_stream.repository');
        $productStreamsRepository->create([$stream], $this->salesChannelContext->getContext());
    }

    private function createCategoryStreams(): string
    {
        $data = [
            'id' => $this->ids->create('category_stream_id_1'),
            'productStreamId' => $this->ids->get('stream_id_1'),
            'productAssignmentType' => CategoryDefinition::PRODUCT_ASSIGNMENT_TYPE_PRODUCT_STREAM,
            'active' => true,
            'name' => 'Home',
        ];

        $this->getContainer()->get('category.repository')->create([$data], Context::createDefaultContext());

        return $this->ids->get('category_stream_id_1');
    }

    private function updateProductStream(string $productId, string $streamId): void
    {
        $connection = $this->getContainer()->get(Connection::class);
        $connection->executeStatement(
            'UPDATE `product` SET `stream_ids` = :streamIds WHERE `id` = :id',
            [
                'streamIds' => json_encode([$streamId], \JSON_THROW_ON_ERROR),
                'id' => Uuid::fromHexToBytes($productId),
            ]
        );
    }
}
