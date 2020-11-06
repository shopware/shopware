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
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class CategoryBreadcrumbBuilderTest extends TestCase
{
    use IntegrationTestBehaviour;
    use SalesChannelApiTestBehaviour;
    use AdminApiTestBehaviour;

    /**
     * @var EntityRepositoryInterface
     */
    private $categoryRepository;

    /**
     * @var SalesChannelContext
     */
    private $salesChannelContext;

    /**
     * @var TestDataCollection
     */
    private $ids;

    /**
     * @var string
     */
    private $deLanguageId;

    /**
     * @var CategoryBreadcrumbBuilder
     */
    private $breadcrumbBuilder;

    /**
     * @var EntityRepositoryInterface
     */
    private $productRepository;

    /**
     * @var \Symfony\Bundle\FrameworkBundle\KernelBrowser
     */
    private $browser;

    /**
     * @var SalesChannelContextFactory
     */
    private $contextFactory;

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
    public function breadcrumbDataProvider()
    {
        return [
            [
                'navigation',
                true,
                false,
            ],
            [
                'navigation',
                true,
                true,
            ],
            [
                'navigation',
                false,
                true,
            ],
            [
                'service',
                true,
                false,
            ],
            [
                'service',
                true,
                true,
            ],
            [
                'service',
                false,
                true,
            ],
            [
                'footer',
                true,
                false,
            ],
            [
                'footer',
                true,
                true,
            ],
            [
                'footer',
                false,
                true,
            ],
        ];
    }

    /**
     * @dataProvider
     */
    public function seoCategoryProvider()
    {
        return [
            [
                true,
                true,
                true,
            ],
            [
                true,
                true,
                false,
            ],
            [
                true,
                false,
                true,
            ],
            [
                true,
                false,
                false,
            ],
            [
                false,
                true,
                false,
            ],
            [
                false,
                true,
                true,
            ],
            [
                false,
                false,
                true,
            ],
            [
                false,
                false,
                false,
            ],
        ];
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
        $category2 = $categories->get($this->ids->get($key . '-1'));
        $category3 = $categories->get($this->ids->get($key . '-2'));

        $result1 = $this->breadcrumbBuilder->build($category1, $salesChannel, $categoryId);
        $result2 = $this->breadcrumbBuilder->build($category2, $salesChannel, $categoryId);
        $result3 = $this->breadcrumbBuilder->build($category3, $salesChannel, $categoryId);

        static::assertCount(0, $result1);
        static::assertSame(['EN-A'], array_values($result2));
        static::assertSame(['EN-A', 'EN-B'], array_values($result3));

        /** @var CategoryCollection $categories */
        $categories = $this->categoryRepository->search(new Criteria($this->ids->prefixed($key)), new Context(
            new SystemSource(),
            [],
            Defaults::CURRENCY,
            [$this->deLanguageId]
        ))->getEntities();

        $category1 = $categories->get($this->ids->get($key));
        $category2 = $categories->get($this->ids->get($key . '-1'));
        $category3 = $categories->get($this->ids->get($key . '-2'));

        $result1 = $this->breadcrumbBuilder->build($category1, $salesChannel, $categoryId);
        $result2 = $this->breadcrumbBuilder->build($category2, $salesChannel, $categoryId);
        $result3 = $this->breadcrumbBuilder->build($category3, $salesChannel, $categoryId);

        static::assertCount(0, $result1);
        static::assertSame(['DE-A'], array_values($result2));
        static::assertSame(['DE-A', 'DE-B'], array_values($result3));
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
            'visibilities' => [
                ['salesChannelId' => $this->ids->get('sales-channel-2'), 'visibility' => ProductVisibilityDefinition::VISIBILITY_ALL],
            ],
            'mainCategories' => [],
        ];

        if ($hasCategories === true) {
            $productData = [
                'categories' => [
                    ['id' => $this->ids->get('navigation-2')],
                    ['id' => $this->ids->get('navigation-sc2-2')],
                ],
            ];
        }

        if ($hasMainCategory === true) {
            $productData['mainCategories'][] = [
                'categoryId' => $this->ids->get('navigation-1'),
                'id' => Uuid::randomHex(),
                'salesChannelId' => $this->salesChannelContext->getSalesChannel()->getId(),
            ];
        }

        if ($hasMainCategory2ndChannel === true) {
            $productData['mainCategories'][] = [
                'categoryId' => $this->ids->get('navigation-sc2-1'),
                'id' => Uuid::randomHex(),
                'salesChannelId' => $this->ids->get('sales-channel-2'),
            ];
        }

        $this->createTestProduct('scc', $productData);

        $criteria = new Criteria([$this->ids->get('scc-product-id')]);
        $criteria->addAssociation('categories');

        /** @var ProductEntity $product */
        $product = $this->productRepository->search($criteria, $this->ids->getContext())->first();

        $category = $this->breadcrumbBuilder->getProductSeoCategory($product, $this->salesChannelContext);
        $category2 = $this->breadcrumbBuilder->getProductSeoCategory($product, $this->contextFactory->create('', $this->ids->get('sales-channel-2')));

        if ($hasCategories === true) {
            static::assertNotNull($product->getCategories());
            static::assertNotCount(0, $product->getCategories());

            if ($hasMainCategory === true) {
                static::assertSame($this->ids->get('navigation-1'), $category->getId());
            } else {
                static::assertSame($this->ids->get('navigation-2'), $category->getId());
            }

            if ($hasMainCategory2ndChannel === true) {
                static::assertSame($this->ids->get('navigation-sc2-1'), $category2->getId());
            } else {
                static::assertSame($this->ids->get('navigation-sc2-2'), $category2->getId());
            }
        } else {
            static::assertNotNull($product->getCategories());
            static::assertCount(0, $product->getCategories());
            static::assertNull($category);
            static::assertNull($category2);
        }
    }

    /**
     * @group slow
     */
    public function testApiResponse(): void
    {
        $this->createTestData('navigation-test');
        $productId = $this->createTestProduct('test');

        $this->getBrowser()->request('PATCH', '/api/v' . PlatformRequest::API_VERSION . '/product/' . $this->ids->get('test-product-id'), [
            'categories' => [
                ['id' => $this->ids->get('navigation-2')],
                ['id' => $this->ids->get('navigation-test-2')],
            ],
        ]);
        $response = $this->getBrowser()->getResponse();
        static::assertEquals(204, $response->getStatusCode(), $response->getContent());

        $this->browser->request('POST', '/store-api/v' . PlatformRequest::API_VERSION . '/product/' . $productId);
        $response = $this->browser->getResponse();
        static::assertSame(200, $response->getStatusCode());

        $json = json_decode($response->getContent(), true);

        static::assertNotEmpty($json);
        static::assertArrayHasKey('product', $json);
        static::assertArrayHasKey('seoCategory', $json['product']);
        static::assertNotCount(0, $json['product']['seoCategory']);
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
                        'id' => $this->ids->create($key . '-1'),
                        'translations' => [
                            ['name' => 'EN-A', 'languageId' => Defaults::LANGUAGE_SYSTEM],
                            ['name' => 'DE-A', 'languageId' => $this->deLanguageId],
                        ],
                        'children' => [
                            [
                                'id' => $this->ids->create($key . '-2'),
                                'translations' => [
                                    ['name' => 'EN-B', 'languageId' => Defaults::LANGUAGE_SYSTEM],
                                    ['name' => 'DE-B', 'languageId' => $this->deLanguageId],
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

    private function createTestProduct(string $name, array $additionalPayload = []): string
    {
        $id = $this->ids->create($name . '-product-id');
        $payload = [
            'id' => $id,
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

        $payload = array_merge_recursive($payload, $additionalPayload);

        $this->productRepository->create([
            $payload,
        ], $this->salesChannelContext->getContext());

        return $id;
    }
}
