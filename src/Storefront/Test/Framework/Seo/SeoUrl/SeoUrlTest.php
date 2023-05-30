<?php declare(strict_types=1);

namespace Shopware\Storefront\Test\Framework\Seo\SeoUrl;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Content\LandingPage\LandingPageEntity;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Content\Seo\SeoUrl\SeoUrlCollection;
use Shopware\Core\Content\Seo\SeoUrl\SeoUrlEntity;
use Shopware\Core\Content\Seo\SeoUrlGenerator;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Test\Seo\StorefrontSalesChannelTestHelper;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\QueueTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\TestDefaults;
use Shopware\Storefront\Framework\Seo\SeoUrlRoute\ProductPageSeoUrlRoute;

/**
 * @internal
 *
 * @group slow
 * @group skip-paratest
 */
class SeoUrlTest extends TestCase
{
    use IntegrationTestBehaviour;
    use StorefrontSalesChannelTestHelper;
    use QueueTestBehaviour;

    private EntityRepository $productRepository;

    private EntityRepository $seoUrlTemplateRepository;

    private EntityRepository $landingPageRepository;

    private SeoUrlGenerator $seoUrlGenerator;

    protected function setUp(): void
    {
        $this->productRepository = $this->getContainer()->get('product.repository');
        $this->seoUrlTemplateRepository = $this->getContainer()->get('seo_url_template.repository');
        $this->landingPageRepository = $this->getContainer()->get('landing_page.repository');

        $this->seoUrlGenerator = $this->getContainer()->get(SeoUrlGenerator::class);
    }

    public function testSearchLandingPage(): void
    {
        $salesChannelId = Uuid::randomHex();
        $salesChannelContext = $this->createStorefrontSalesChannelContext($salesChannelId, 'test');

        $id = $this->createTestLandingPage(['salesChannels' => [
            [
                'id' => $salesChannelContext->getSalesChannelId(),
            ],
        ]]);

        $criteria = new Criteria([$id]);
        $criteria->addAssociation('seoUrls');

        /** @var LandingPageEntity $landingPage */
        $landingPage = $this->landingPageRepository->search($criteria, $salesChannelContext->getContext())->first();

        static::assertInstanceOf(SeoUrlCollection::class, $landingPage->getSeoUrls());

        /** @var SeoUrlCollection $seoUrls */
        $seoUrls = $landingPage->getSeoUrls();
        $seoUrl = $seoUrls->first();
        static::assertInstanceOf(SeoUrlEntity::class, $seoUrl);
        static::assertEquals('coolUrl', $seoUrl->getSeoPathInfo());
    }

    public function testLandingPageUpdate(): void
    {
        $salesChannelId = Uuid::randomHex();
        $salesChannelContext = $this->createStorefrontSalesChannelContext($salesChannelId, 'test');

        $id = $this->createTestLandingPage(['salesChannels' => [
            [
                'id' => $salesChannelContext->getSalesChannelId(),
            ],
        ]]);

        $this->landingPageRepository->update(
            [
                [
                    'id' => $id,
                    'url' => 'newUrl',
                ],
            ],
            $salesChannelContext->getContext()
        );

        $criteria = new Criteria([$id]);
        $criteria->addAssociation('seoUrls');

        /** @var ProductEntity $first */
        $first = $this->landingPageRepository->search($criteria, Context::createDefaultContext())->first();

        static::assertNotNull($first);

        // Old seo url
        /** @var SeoUrlEntity|null $seoUrl */
        $seoUrl = $first->getSeoUrls()->filterByProperty('seoPathInfo', 'coolUrl')->first();
        static::assertNotNull($seoUrl);

        static::assertNull($seoUrl->getIsCanonical());
        static::assertFalse($seoUrl->getIsDeleted());

        static::assertEquals('/landingPage/' . $id, $seoUrl->getPathInfo());
        static::assertEquals($id, $seoUrl->getForeignKey());

        // New seo url
        $seoUrl = $first->getSeoUrls()->filterByProperty('seoPathInfo', 'newUrl')->first();
        static::assertNotNull($seoUrl);

        static::assertTrue($seoUrl->getIsCanonical());
        static::assertFalse($seoUrl->getIsDeleted());

        static::assertEquals('/landingPage/' . $id, $seoUrl->getPathInfo());
        static::assertEquals($id, $seoUrl->getForeignKey());
    }

    public function testSearchProduct(): void
    {
        $salesChannelId = Uuid::randomHex();
        $salesChannelContext = $this->createStorefrontSalesChannelContext($salesChannelId, 'test');

        $id = $this->createTestProduct();

        $criteria = new Criteria([$id]);
        $criteria->addAssociation('seoUrls');

        /** @var ProductEntity $product */
        $product = $this->productRepository->search($criteria, $salesChannelContext->getContext())->first();

        static::assertInstanceOf(SeoUrlCollection::class, $product->getSeoUrls());

        /** @var SeoUrlCollection $seoUrls */
        $seoUrls = $product->getSeoUrls();
        $seoUrl = $seoUrls->first();
        static::assertInstanceOf(SeoUrlEntity::class, $seoUrl);
        static::assertEquals('foo-bar/P1234', $seoUrl->getSeoPathInfo());
    }

    public function testSearchCategory(): void
    {
        $salesChannelId = Uuid::randomHex();
        $salesChannelContext = $this->createStorefrontSalesChannelContext($salesChannelId, 'test');

        $categoryRepository = $this->getContainer()->get('category.repository');

        $rootId = Uuid::randomHex();
        $childAId = Uuid::randomHex();
        $childA1Id = Uuid::randomHex();

        $categoryRepository->create([[
            'id' => $rootId,
            'name' => 'root',
            'children' => [
                [
                    'id' => $childAId,
                    'name' => 'a',
                    'children' => [
                        [
                            'id' => $childA1Id,
                            'name' => '1',
                        ],
                    ],
                ],
            ],
        ]], Context::createDefaultContext());
        $this->runWorker();

        $context = $salesChannelContext->getContext();

        $cases = [
            ['expected' => null, 'categoryId' => $childAId],
            ['expected' => null, 'categoryId' => $childA1Id],
        ];

        $this->runChecks($cases, $categoryRepository, $context, $salesChannelId);
    }

    public function testSearchCategoryWithLink(): void
    {
        $salesChannelId = Uuid::randomHex();
        $salesChannelContext = $this->createStorefrontSalesChannelContext($salesChannelId, 'test');

        $categoryRepository = $this->getContainer()->get('category.repository');

        $categoryPageId = Uuid::randomHex();
        $categoryPage = [
            [
                'id' => $categoryPageId,
                'name' => 'page',
                'type' => 'page',
            ],
        ];

        $categoryLinkId = Uuid::randomHex();
        $categoryLink = [
            [
                'id' => $categoryLinkId,
                'name' => 'link',
                'type' => 'link',
            ],
        ];

        $categories = [...$categoryLink, ...$categoryPage];
        $categoryRepository->create($categories, Context::createDefaultContext());
        $this->runWorker();

        $context = $salesChannelContext->getContext();

        $cases = [
            ['expected' => null, 'categoryId' => $categoryPageId],
            ['expected' => null, 'categoryId' => $categoryLinkId],
        ];

        $this->runChecks($cases, $categoryRepository, $context, $salesChannelId);
    }

    public function testSearchCategoryWithSalesChannelEntryPoint(): void
    {
        $salesChannelId = Uuid::randomHex();
        $salesChannelContext = $this->createStorefrontSalesChannelContext(
            $salesChannelId,
            'test'
        );

        $categoryRepository = $this->getContainer()->get('category.repository');

        $rootId = Uuid::randomHex();
        $childAId = Uuid::randomHex();
        $childA1Id = Uuid::randomHex();
        $childA1ZId = Uuid::randomHex();

        $categoryRepository->create([[
            'id' => $rootId,
            'name' => 'root',
            'children' => [
                [
                    'id' => $childAId,
                    'name' => 'a',
                    'children' => [
                        [
                            'id' => $childA1Id,
                            'name' => '1',
                            'children' => [
                                [
                                    'id' => $childA1ZId,
                                    'name' => 'z',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]], Context::createDefaultContext());

        $this->updateSalesChannelNavigationEntryPoint($salesChannelId, $childAId);
        $this->runWorker();

        $context = $salesChannelContext->getContext();

        $cases = [
            ['expected' => '1/', 'categoryId' => $childA1Id],
            ['expected' => '1/z/', 'categoryId' => $childA1ZId],
        ];

        $this->runChecks($cases, $categoryRepository, $context, $salesChannelId);
    }

    public function testSearchCategoryWithComplexHierarchy(): void
    {
        $salesChannelId = Uuid::randomHex();
        $salesChannelContext = $this->createStorefrontSalesChannelContext(
            $salesChannelId,
            'test'
        );

        $categoryRepository = $this->getContainer()->get('category.repository');

        $rootId = Uuid::randomHex();
        $childAId = Uuid::randomHex();
        $childA1Id = Uuid::randomHex();
        $childA1ZId = Uuid::randomHex();
        $childBId = Uuid::randomHex();
        $childB1Id = Uuid::randomHex();
        $childB1ZId = Uuid::randomHex();

        $categoryRepository->create([[
            'id' => $rootId,
            'name' => 'root',
            'children' => [
                [
                    'id' => $childAId,
                    'name' => 'a',
                    'children' => [
                        [
                            'id' => $childA1Id,
                            'name' => '1',
                            'children' => [
                                [
                                    'id' => $childA1ZId,
                                    'name' => 'z',
                                ],
                            ],
                        ],
                    ],
                ],
                [
                    'id' => $childBId,
                    'name' => 'b',
                    'children' => [
                        [
                            'id' => $childB1Id,
                            'name' => '2',
                            'children' => [
                                [
                                    'id' => $childB1ZId,
                                    'name' => 'y',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]], Context::createDefaultContext());

        $context = $salesChannelContext->getContext();

        // We are updating the sales channel entry point without running a worker task. We expect the root category url
        // to change, while all other urls will be recreated in an asynch worker task.
        $this->updateSalesChannelNavigationEntryPoint($salesChannelId, $rootId);
        $this->runChecks([], $categoryRepository, $context, $salesChannelId);

        $this->runWorker();
        $casesRoot = [
            ['expected' => null, 'categoryId' => $rootId],
            ['expected' => 'b/', 'categoryId' => $childBId],
            ['expected' => 'b/2/y/', 'categoryId' => $childB1ZId],
            ['expected' => 'a/', 'categoryId' => $childAId],
            ['expected' => 'a/1/z/', 'categoryId' => $childA1ZId],
        ];
        $this->runChecks($casesRoot, $categoryRepository, $context, $salesChannelId);

        $this->updateSalesChannelNavigationEntryPoint($salesChannelId, $childAId);
        $this->runWorker();
        $casesA = [
            ['expected' => null, 'categoryId' => $rootId],
            ['expected' => '1/', 'categoryId' => $childA1Id],
            ['expected' => '1/z/', 'categoryId' => $childA1ZId],
        ];
        $this->runChecks($casesA, $categoryRepository, $context, $salesChannelId);

        $this->updateSalesChannelNavigationEntryPoint($salesChannelId, $rootId);
        $this->runWorker();
        $this->runChecks($casesRoot, $categoryRepository, $context, $salesChannelId);
    }

    public function testSearchWithLimit(): void
    {
        /** @var EntityRepository $productRepo */
        $productRepo = $this->getContainer()->get('product.repository');

        $productRepo->create([[
            'id' => Uuid::randomHex(),
            'name' => 'foo bar',
            'manufacturer' => [
                'id' => Uuid::randomHex(),
                'name' => 'amazing brand',
            ],
            'productNumber' => Uuid::randomHex(),
            'tax' => ['id' => Uuid::randomHex(), 'taxRate' => 19, 'name' => 'tax'],
            'price' => [
                ['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 12, 'linked' => false],
            ],
            'stock' => 0,
        ]], Context::createDefaultContext());

        $criteria = new Criteria();
        $criteria->setLimit(10);
        $criteria->getAssociation('seoUrls')->setLimit(10);

        /** @var ProductEntity $product */
        $product = $productRepo->search($criteria, Context::createDefaultContext())->first();

        static::assertInstanceOf(SeoUrlCollection::class, $product->getSeoUrls());
    }

    public function testSearchWithFilter(): void
    {
        /** @var EntityRepository $productRepo */
        $productRepo = $this->getContainer()->get('product.repository');

        $productRepo->create([[
            'id' => Uuid::randomHex(),
            'name' => 'foo bar',
            'manufacturer' => [
                'id' => Uuid::randomHex(),
                'name' => 'amazing brand',
            ],
            'productNumber' => Uuid::randomHex(),
            'tax' => ['id' => Uuid::randomHex(), 'taxRate' => 19, 'name' => 'tax'],
            'price' => [
                ['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 12, 'linked' => false],
            ],
            'stock' => 0,
            'seoUrls' => [
                [
                    'id' => Uuid::randomHex(),
                    'routeName' => ProductPageSeoUrlRoute::ROUTE_NAME,
                    'pathInfo' => 'foo',
                    'seoPathInfo' => 'asdf',
                ],
            ],
        ]], Context::createDefaultContext());

        $criteria = new Criteria();
        $criteria->setLimit(10);
        $criteria->addFilter(new EqualsFilter('product.seoUrls.isCanonical', null));

        $criteria->getAssociation('seoUrls')
            ->setLimit(10)
            ->addFilter(new EqualsFilter('isCanonical', null));

        $products = $productRepo->search($criteria, Context::createDefaultContext());
        static::assertNotEmpty($products);

        /** @var ProductEntity $product */
        $product = $products->first();
        static::assertInstanceOf(SeoUrlCollection::class, $product->getSeoUrls());
    }

    public function testInsert(): void
    {
        $seoUrlId1 = Uuid::randomHex();
        $seoUrlId2 = Uuid::randomHex();

        $id = Uuid::randomHex();
        $this->upsertProduct([
            'id' => $id,
            'name' => 'awesome product',
            'seoUrls' => [
                [
                    'id' => $seoUrlId1,
                    'salesChannelId' => TestDefaults::SALES_CHANNEL,
                    'routeName' => ProductPageSeoUrlRoute::ROUTE_NAME,
                    'pathInfo' => '/detail/' . $id,
                    'seoPathInfo' => 'awesome v2',
                    'isCanonical' => true,
                    'isModified' => true,
                ],
                [
                    'id' => $seoUrlId2,
                    'salesChannelId' => TestDefaults::SALES_CHANNEL,
                    'routeName' => ProductPageSeoUrlRoute::ROUTE_NAME,
                    'pathInfo' => '/detail/' . $id,
                    'seoPathInfo' => 'awesome',
                    'isCanonical' => null,
                    'isModified' => true,
                ],
            ],
            'visibilities' => [
                [
                    'salesChannelId' => TestDefaults::SALES_CHANNEL,
                    'visibility' => ProductVisibilityDefinition::VISIBILITY_ALL,
                ],
            ],
        ]);

        $criteria = new Criteria([$id]);
        $criteria->addAssociation('seoUrls');

        /** @var ProductEntity $first */
        $first = $this->productRepository->search($criteria, Context::createDefaultContext())->first();

        static::assertNotNull($first);

        /** @var SeoUrlCollection $seoUrls */
        $seoUrls = $first->getSeoUrls();
        static::assertNotNull($seoUrls);

        /** @var SeoUrlEntity|null $seoUrl */
        $seoUrl = $seoUrls->filterByProperty('id', $seoUrlId1)->first();
        static::assertNotNull($seoUrl);

        static::assertTrue($seoUrl->getIsCanonical());
        static::assertFalse($seoUrl->getIsDeleted());

        static::assertEquals('awesome v2', $seoUrl->getSeoPathInfo());
    }

    public function testUpdate(): void
    {
        $seoUrlId = Uuid::randomHex();
        $id = Uuid::randomHex();
        $this->upsertProduct(['id' => $id, 'name' => 'awesome product']);

        $router = $this->getContainer()->get('router');
        $pathInfo = $router->generate(ProductPageSeoUrlRoute::ROUTE_NAME, ['productId' => $id]);

        $this->upsertProduct([
            'id' => $id,
            'seoUrls' => [
                [
                    'id' => $seoUrlId,
                    'routeName' => ProductPageSeoUrlRoute::ROUTE_NAME,
                    'pathInfo' => $pathInfo,
                    'seoPathInfo' => 'awesome',
                    'isCanonical' => true,
                ],
            ],
        ]);

        $criteria = new Criteria([$id]);
        $criteria->addAssociation('seoUrls');

        /** @var ProductEntity $first */
        $first = $this->productRepository->search($criteria, Context::createDefaultContext())->first();

        static::assertNotNull($first);

        /** @var SeoUrlEntity|null $seoUrl */
        $seoUrl = $first->getSeoUrls()->filterByProperty('id', $seoUrlId)->first();
        static::assertNotNull($seoUrl);

        static::assertTrue($seoUrl->getIsCanonical());
        static::assertFalse($seoUrl->getIsDeleted());

        static::assertEquals('/detail/' . $id, $seoUrl->getPathInfo());
        static::assertEquals($id, $seoUrl->getForeignKey());
    }

    private function runChecks(array $cases, EntityRepository $categoryRepository, Context $context, string $salesChannelId): void
    {
        foreach ($cases as $case) {
            $criteria = new Criteria([$case['categoryId']]);
            $criteria->addAssociation('seoUrls');

            $category = $categoryRepository->search($criteria, $context)->first();
            static::assertEquals($case['categoryId'], $category->getId());

            /** @var SeoUrlCollection $seoUrls */
            $seoUrls = $category->getSeoUrls();
            static::assertInstanceOf(SeoUrlCollection::class, $seoUrls);

            if ($category->getType() === CategoryDefinition::TYPE_LINK) {
                static::assertCount(0, $category->getSeoUrls());

                continue;
            }

            $seoUrls = $seoUrls->filterByProperty('salesChannelId', $salesChannelId);
            $expectedCount = $case['expected'] === null ? 0 : 1;
            static::assertCount($expectedCount, $seoUrls->filterByProperty('isCanonical', true));

            if ($expectedCount === 0) {
                continue;
            }

            /** @var SeoUrlEntity $canonicalUrl */
            $canonicalUrl = $seoUrls
                ->filterByProperty('isCanonical', true)
                ->filterByProperty('salesChannelId', $salesChannelId)
                ->first();
            static::assertInstanceOf(SeoUrlEntity::class, $canonicalUrl);
            static::assertEquals($case['expected'], $canonicalUrl->getSeoPathInfo());
        }
    }

    private function upsertProduct(array $data): EntityWrittenContainerEvent
    {
        $defaults = [
            'productNumber' => Uuid::randomHex(),
            'manufacturer' => [
                'id' => Uuid::randomHex(),
                'name' => 'amazing brand',
            ],
            'tax' => ['id' => Uuid::randomHex(), 'taxRate' => 19, 'name' => 'tax'],
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 12, 'linked' => false]],
            'stock' => 0,
        ];
        $data = array_merge($defaults, $data);

        return $this->productRepository->upsert([$data], Context::createDefaultContext());
    }

    private function createTestProduct(array $overrides = []): string
    {
        $id = Uuid::randomHex();
        $insert = [
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
            'visibilities' => [
                [
                    'salesChannelId' => TestDefaults::SALES_CHANNEL,
                    'visibility' => ProductVisibilityDefinition::VISIBILITY_ALL,
                ],
            ],
        ];

        $insert = array_merge($insert, $overrides);

        $this->productRepository->create([$insert], Context::createDefaultContext());

        return $id;
    }

    private function createTestLandingPage(array $overrides = []): string
    {
        $id = Uuid::randomHex();
        $insert = [
            'id' => $id,
            'name' => 'foo bar',
            'url' => 'coolUrl',
        ];

        $insert = array_merge($insert, $overrides);

        $this->landingPageRepository->create([$insert], Context::createDefaultContext());

        return $id;
    }
}
