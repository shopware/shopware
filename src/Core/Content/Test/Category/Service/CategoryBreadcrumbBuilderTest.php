<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Category\Service;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Category\CategoryCollection;
use Shopware\Core\Content\Category\Service\CategoryBreadcrumbBuilder;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Context\SystemSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Test\TestCaseBase\AdminApiTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelApiTestBehaviour;
use Shopware\Core\Framework\Test\TestDataCollection;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Context\AbstractSalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

class CategoryBreadcrumbBuilderTest extends TestCase
{
    use IntegrationTestBehaviour;
    use SalesChannelApiTestBehaviour;
    use AdminApiTestBehaviour;

    private ?EntityRepositoryInterface $categoryRepository;

    private ?SalesChannelContext $salesChannelContext;

    private TestDataCollection $ids;

    private string $deLanguageId;

    private ?CategoryBreadcrumbBuilder $breadcrumbBuilder;

    private ?EntityRepositoryInterface $productRepository;

    private KernelBrowser $browser;

    private ?AbstractSalesChannelContextFactory $contextFactory;

    public function setUp(): void
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
     * @dataProvider
     */
    public function breadcrumbDataProvider(): iterable
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
     * @dataProvider
     */
    public function seoCategoryProvider(): iterable
    {
        yield [false, false, false];
        yield [false, false, true];
        yield [false, true, false];
        yield [false, true, true];
        yield [true, false, false];
        yield [true, false, true];
        yield [true, true, false];
        yield [true, true, true];
    }

    /**
     * @dataProvider breadcrumbDataProvider
     * @group slow
     */
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

        $result1 = $this->breadcrumbBuilder->build($category1, $salesChannel, $categoryId);
        $result2 = $this->breadcrumbBuilder->build($category2, $salesChannel, $categoryId);
        $result3 = $this->breadcrumbBuilder->build($category3, $salesChannel, $categoryId);

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

        $result1 = $this->breadcrumbBuilder->build($category1, $salesChannel, $categoryId);
        $result2 = $this->breadcrumbBuilder->build($category2, $salesChannel, $categoryId);
        $result3 = $this->breadcrumbBuilder->build($category3, $salesChannel, $categoryId);

        static::assertCount(0, $result1);
        static::assertSame(['DE-A'], array_values($result2));
        static::assertSame(['DE-A', 'DE-AA'], array_values($result3));
    }

    /**
     * @dataProvider seoCategoryProvider
     * @group slow
     */
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
                'visibilities' => [
                    ['salesChannelId' => $this->ids->get('sales-channel-2'), 'visibility' => ProductVisibilityDefinition::VISIBILITY_ALL],
                ],
            ],
        ];

        if ($hasCategories === true) {
            $productData[0]['categories'] = [
                ['id' => $this->ids->get('navigation-a-1')],
                ['id' => $this->ids->get('navigation-a-2')],
                ['id' => $this->ids->get('navigation-sc2-a-1')],
                ['id' => $this->ids->get('navigation-sc2-a-2')],
            ];
        }

        if ($hasMainCategory === true) {
            $productData[0]['mainCategories'][] = [
                'categoryId' => $this->ids->get('navigation-a-1'),
                'id' => Uuid::randomHex(),
                'salesChannelId' => $this->salesChannelContext->getSalesChannel()->getId(),
            ];
        }

        if ($hasMainCategory2ndChannel === true) {
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
        $product = $this->productRepository->search($criteria, $this->ids->getContext())->first();

        $category = $this->breadcrumbBuilder->getProductSeoCategory($product, $this->salesChannelContext);
        $category2 = $this->breadcrumbBuilder->getProductSeoCategory($product, $this->contextFactory->create('', $this->ids->get('sales-channel-2')));

        static::assertNotNull($product->getCategories());

        if ($hasCategories === true) {
            static::assertNotCount(0, $product->getCategories());

            if ($hasMainCategory === true) {
                static::assertSame($this->ids->get('navigation-a-1'), $category->getId());
            } else {
                static::assertTrue(\in_array($category->getId(), $this->ids->prefixed('navigation-a'), true));
            }

            if ($hasMainCategory2ndChannel === true) {
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

    /**
     * @group slow
     */
    public function testApiResponseHasSeoCategory(): void
    {
        $this->createTestData('navigation-test');

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
        static::assertEquals(204, $response->getStatusCode(), $response->getContent());

        $this->browser->request('POST', '/store-api/product/' . $productId);
        $response = $this->browser->getResponse();
        static::assertSame(200, $response->getStatusCode());

        $json = json_decode($response->getContent(), true);

        static::assertNotEmpty($json);
        static::assertArrayHasKey('product', $json);
        static::assertArrayHasKey('seoCategory', $json['product']);
        static::assertNotCount(0, $json['product']['seoCategory']);
        static::assertEquals($this->ids->get('navigation-a-2'), $json['product']['seoCategory']['id']);
    }

    /**
     * @group slow
     */
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

        $mainProduct = $this->productRepository->search($this->createSeoCriteria([$this->ids->get('variant-product')]), $this->ids->getContext())->first();
        $categoryMain = $this->breadcrumbBuilder->getProductSeoCategory($mainProduct, $this->salesChannelContext);

        static::assertSame($this->ids->get('navigation-a-2'), $categoryMain->getId());
        static::assertSame('EN-AA', $categoryMain->getName());

        $variant1 = $this->productRepository->search($this->createSeoCriteria([$this->ids->get('variant-product-1')]), $this->ids->getContext())->first();
        $categoryVariant1 = $this->breadcrumbBuilder->getProductSeoCategory($variant1, $this->salesChannelContext);

        static::assertSame($this->ids->get('navigation-b-2'), $categoryVariant1->getId());
        static::assertSame('EN-BA', $categoryVariant1->getName());

        $variant2 = $this->productRepository->search($this->createSeoCriteria([$this->ids->get('variant-product-2')]), $this->ids->getContext())->first();
        $categoryVariant2 = $this->breadcrumbBuilder->getProductSeoCategory($variant2, $this->salesChannelContext);

        static::assertSame($this->ids->get('navigation-b-1'), $categoryVariant2->getId());
        static::assertSame('EN-B', $categoryVariant2->getName());

        $variant3 = $this->productRepository->search($this->createSeoCriteria([$this->ids->get('variant-product-3')]), $this->ids->getContext())->first();
        $categoryVariant3 = $this->breadcrumbBuilder->getProductSeoCategory($variant3, $this->salesChannelContext);

        static::assertSame($this->ids->get('navigation-a-1'), $categoryVariant3->getId());
        static::assertSame('EN-A', $categoryVariant3->getName());

        $variant4 = $this->productRepository->search($this->createSeoCriteria([$this->ids->get('variant-product-4')]), $this->ids->getContext())->first();
        $categoryVariant4 = $this->breadcrumbBuilder->getProductSeoCategory($variant4, $this->salesChannelContext);

        static::assertSame($this->ids->get('navigation-a-2'), $categoryVariant4->getId());
        static::assertSame('EN-AA', $categoryVariant4->getName());
    }

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

        $this->getContainer()->get('category.repository')->create($data, $this->ids->getContext());

        return $this->ids->get($key);
    }

    private function createTestProduct(array $products = [], $fillAll = true): void
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
            'active' => true,
            'visibilities' => [
                ['salesChannelId' => $this->ids->get('sales-channel'), 'visibility' => ProductVisibilityDefinition::VISIBILITY_ALL],
            ],
        ];

        if ($fillAll === true) {
            foreach ($products as $k => $v) {
                $products[$k] = array_merge($basicPayload, $v);
            }
        } else {
            $products[0] = array_merge($basicPayload, $products[0]);
        }

        $this->productRepository->create($products, $this->salesChannelContext->getContext());
    }
}
