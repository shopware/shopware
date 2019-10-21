<?php declare(strict_types=1);

namespace Shopware\Storefront\Test\Framework\Seo\SeoUrl;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Seo\SeoUrl\SeoUrlCollection;
use Shopware\Core\Framework\Seo\SeoUrl\SeoUrlEntity;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\QueueTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Storefront\Framework\Seo\SeoUrlRoute\ProductPageSeoUrlRoute;
use Shopware\Storefront\Test\Framework\Seo\StorefrontSalesChannelTestHelper;

class SeoUrlExtensionTest extends TestCase
{
    use IntegrationTestBehaviour;
    use StorefrontSalesChannelTestHelper;
    use QueueTestBehaviour;

    /**
     * @var EntityRepositoryInterface
     */
    private $productRepository;

    /**
     * @var EntityRepositoryInterface
     */
    private $seoUrlTemplateRepository;

    public function setUp(): void
    {
        $this->productRepository = $this->getContainer()->get('product.repository');
        $this->seoUrlTemplateRepository = $this->getContainer()->get('seo_url_template.repository');
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

        static::assertInstanceOf(SeoUrlCollection::class, $product->getExtension('seoUrls'));

        /** @var SeoUrlCollection $seoUrls */
        $seoUrls = $product->getExtension('seoUrls');
        $seoUrl = $seoUrls->first();
        static::assertInstanceOf(SeoUrlEntity::class, $seoUrl);
        static::assertEquals('foo-bar/P1234', $seoUrl->getSeoPathInfo());
    }

    /**
     * @depends testSearchProduct
     */
    public function testSearchProductAfterManufacturerUpdate(): void
    {
        $salesChannelId = Uuid::randomHex();
        $salesChannelContext = $this->createStorefrontSalesChannelContext($salesChannelId, 'test');

        $templateUrl = Uuid::randomHex();
        $this->seoUrlTemplateRepository->create(
            [
                [
                    'id' => $templateUrl,
                    'salesChannelId' => $salesChannelId,
                    'routeName' => ProductPageSeoUrlRoute::ROUTE_NAME,
                    'entityName' => ProductDefinition::ENTITY_NAME,
                    'template' => '{{ product.translated.name }}/{{ product.manufacturer.translated.name }}',
                    'isValid' => true,
                ],
            ],
            $salesChannelContext->getContext()
        );

        $id = $this->createTestProduct();

        $criteria = new Criteria([$id]);
        $criteria->addAssociation('seoUrls');

        /** @var ProductEntity $product */
        $product = $this->productRepository->search($criteria, $salesChannelContext->getContext())->first();

        static::assertInstanceOf(SeoUrlCollection::class, $product->getExtension('seoUrls'));

        /** @var SeoUrlCollection $seoUrls */
        $seoUrls = $product->getExtension('seoUrls');
        /** @var SeoUrlEntity $canonicalUrl */
        $canonicalUrl = $seoUrls
            ->filterByProperty('isCanonical', true)
            ->filterByProperty('salesChannelId', $salesChannelId)
            ->first();
        static::assertInstanceOf(SeoUrlEntity::class, $canonicalUrl);
        static::assertEquals('foo-bar/amazing-brand', $canonicalUrl->getSeoPathInfo());

        /** @var EntityRepositoryInterface $manufacturerRepository */
        $manufacturerRepository = $this->getContainer()->get('product_manufacturer.repository');
        $manufacturerRepository->update(
            [
                [
                    'id' => $product->getManufacturerId(),
                    'name' => 'wuseldusel',
                ],
            ],
            $salesChannelContext->getContext()
        );

        $product = $this->productRepository->search($criteria, $salesChannelContext->getContext())->first();

        static::assertInstanceOf(SeoUrlCollection::class, $product->getExtension('seoUrls'));

        /** @var SeoUrlEntity $canonicalUrl */
        $canonicalUrl = $product->getExtension('seoUrls')
            ->filterByProperty('isCanonical', true)
            ->filterByProperty('salesChannelId', $salesChannelId)
            ->first();
        static::assertInstanceOf(SeoUrlEntity::class, $canonicalUrl);
        static::assertEquals('foo-bar/wuseldusel', $canonicalUrl->getSeoPathInfo());
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
            //            ['expected' => 'root', 'categoryId' => $rootId],
            ['expected' => 'root/a/', 'categoryId' => $childAId],
            ['expected' => 'root/a/1/', 'categoryId' => $childA1Id],
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
        $casesBeforeUpdate = [
            ['expected' => null, 'categoryId' => $rootId],
            ['expected' => 'root/b/', 'categoryId' => $childBId],
            ['expected' => 'root/b/2/y/', 'categoryId' => $childB1ZId],
        ];
        $this->runChecks($casesBeforeUpdate, $categoryRepository, $context, $salesChannelId);

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
        /** @var EntityRepositoryInterface $productRepo */
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

        static::assertInstanceOf(SeoUrlCollection::class, $product->getExtension('seoUrls'));
    }

    public function testSearchWithFilter(): void
    {
        /** @var EntityRepositoryInterface $productRepo */
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
                ['id' => Uuid::randomHex(), 'pathInfo' => 'foo', 'seoPathInfo' => 'asdf'],
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
        static::assertInstanceOf(SeoUrlCollection::class, $product->getExtension('seoUrls'));
    }

    public function testInsert(): void
    {
        $seoUrlId1 = Uuid::randomHex();
        $seoUrlId2 = Uuid::randomHex();

        $id = Uuid::randomHex();
        $this->upsertProduct([
            'id' => $id,
            'name' => 'awesome product',
            'extensions' => [
                'seoUrls' => [
                    [
                        'id' => $seoUrlId1,
                        'salesChannelId' => Defaults::SALES_CHANNEL,
                        'pathInfo' => '/detail/' . $id,
                        'seoPathInfo' => 'awesome v2',
                        'isCanonical' => true,
                    ],
                    [
                        'id' => $seoUrlId2,
                        'salesChannelId' => Defaults::SALES_CHANNEL,
                        'pathInfo' => '/detail/' . $id,
                        'seoPathInfo' => 'awesome',
                        'isCanonical' => null,
                    ],
                ],
            ],
        ]);

        $criteria = new Criteria([$id]);
        $criteria->addAssociation('seoUrls');

        /** @var ProductEntity $first */
        $first = $this->productRepository->search($criteria, Context::createDefaultContext())->first();

        static::assertNotNull($first);

        /** @var SeoUrlCollection $seoUrls */
        $seoUrls = $first->getExtensionOfType('seoUrls', SeoUrlCollection::class);
        static::assertNotNull($seoUrls);

        /** @var SeoUrlEntity|null $seoUrl */
        $seoUrl = $seoUrls->filterByProperty('id', $seoUrlId1)->first();
        static::assertNotNull($seoUrl);

        static::assertTrue($seoUrl->getIsModified());
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
            'extensions' => [
                'seoUrls' => [
                    [
                        'id' => $seoUrlId,
                        'salesChannelId' => Defaults::SALES_CHANNEL,
                        'pathInfo' => $pathInfo,
                        'seoPathInfo' => 'awesome',
                        'isCanonical' => true,
                    ],
                ],
            ],
        ]);

        $criteria = new Criteria([$id]);
        $criteria->addAssociation('seoUrls');

        /** @var ProductEntity $first */
        $first = $this->productRepository->search($criteria, Context::createDefaultContext())->first();

        static::assertNotNull($first);

        /** @var SeoUrlCollection $seoUrls */
        $seoUrls = $first->getExtensionOfType('seoUrls', SeoUrlCollection::class);

        /** @var SeoUrlEntity|null $seoUrl */
        $seoUrl = $seoUrls->filterByProperty('id', $seoUrlId)->first();
        static::assertNotNull($seoUrl);

        static::assertTrue($seoUrl->getIsModified());
        static::assertTrue($seoUrl->getIsCanonical());
        static::assertFalse($seoUrl->getIsDeleted());

        static::assertEquals('/detail/' . $id, $seoUrl->getPathInfo());
        static::assertEquals($id, $seoUrl->getForeignKey());
    }

    private function runChecks($cases, $categoryRepository, $context, $salesChannelId): void
    {
        foreach ($cases as $case) {
            $criteria = new Criteria([$case['categoryId']]);
            $criteria->addAssociation('seoUrls');

            $category = $categoryRepository->search($criteria, $context)->first();
            static::assertEquals($case['categoryId'], $category->getId());

            /** @var SeoUrlCollection $seoUrls */
            $seoUrls = $category->getExtension('seoUrls');
            static::assertInstanceOf(SeoUrlCollection::class, $seoUrls);
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

    private function upsertProduct($data): void
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
        $this->productRepository->upsert([$data], Context::createDefaultContext());
    }

    private function createTestProduct(): string
    {
        $id = Uuid::randomHex();
        $this->productRepository->create([[
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
        ]], Context::createDefaultContext());

        return $id;
    }
}
