<?php declare(strict_types=1);

namespace Shopware\Storefront\Test\Framework\Seo\MainCategory;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Content\Seo\MainCategory\MainCategoryCollection;
use Shopware\Core\Content\Seo\MainCategory\MainCategoryEntity;
use Shopware\Core\Content\Seo\SeoUrl\SeoUrlEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Test\Seo\StorefrontSalesChannelTestHelper;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Framework\Seo\SeoUrlRoute\ProductPageSeoUrlRoute;

class MainCategoryExtensionTest extends TestCase
{
    use IntegrationTestBehaviour;
    use StorefrontSalesChannelTestHelper;

    /**
     * @var EntityRepositoryInterface
     */
    private $productRepository;

    /**
     * @var EntityRepositoryInterface
     */
    private $seoUrlTemplateRepository;

    /**
     * @var EntityRepositoryInterface
     */
    private $categoryRepository;

    public function setUp(): void
    {
        $this->productRepository = $this->getContainer()->get('product.repository');
        $this->seoUrlTemplateRepository = $this->getContainer()->get('seo_url_template.repository');
        $this->categoryRepository = $this->getContainer()->get('category.repository');
    }

    public function testMainCategoryLoaded(): void
    {
        $salesChannelId = Uuid::randomHex();
        $salesChannelContext = $this->createStorefrontSalesChannelContext($salesChannelId, 'test');

        $id = $this->createTestProduct();

        $criteria = new Criteria([$id]);
        $criteria->addAssociation('mainCategories');

        /** @var ProductEntity $product */
        $product = $this->productRepository->search($criteria, $salesChannelContext->getContext())->first();

        static::assertNotNull($product->getMainCategories());
        static::assertInstanceOf(MainCategoryCollection::class, $product->getMainCategories());
        static::assertEmpty($product->getMainCategories());

        // update main category
        $categories = $this->categoryRepository->searchIds(new Criteria(), Context::createDefaultContext());

        $this->productRepository->update([
            [
                'id' => $id,
                'mainCategories' => [
                    [
                        'salesChannelId' => $salesChannelId,
                        'categoryId' => $categories->firstId(),
                    ],
                ],
            ],
        ], Context::createDefaultContext());

        $product = $this->productRepository->search($criteria, $salesChannelContext->getContext())->first();

        static::assertNotNull($product->getMainCategories());
        static::assertInstanceOf(MainCategoryCollection::class, $product->getMainCategories());
        $mainCategories = $product->getMainCategories();
        static::assertCount(1, $mainCategories);

        $mainCategory = $mainCategories->filterBySalesChannelId($salesChannelId)->first();
        static::assertInstanceOf(MainCategoryEntity::class, $mainCategory);
        static::assertEquals($salesChannelId, $mainCategory->getSalesChannelId());
        static::assertEquals($categories->firstId(), $mainCategory->getCategoryId());
    }

    public function testSeoUrlWithMainCategory(): void
    {
        $salesChannelId = Uuid::randomHex();
        $salesChannelContext = $this->createStorefrontSalesChannelContext($salesChannelId, 'test');

        $this->createProductUrlTemplate(
            $salesChannelContext,
            '{{ product.mainCategory.name }}/{{ product.name }}'
        );

        $categoryId = Uuid::randomHex();
        $this->categoryRepository->create([[
            'id' => $categoryId,
            'name' => 'awesome category',
        ]], Context::createDefaultContext());

        $id = $this->createTestProduct([
            'mainCategories' => [
                [
                    'salesChannelId' => $salesChannelId,
                    'categoryId' => $categoryId,
                ],
            ],
        ]);

        $criteria = new Criteria([$id]);
        $criteria->addAssociation('seoUrls');
        $criteria->addAssociation('mainCategories');

        /** @var ProductCollection $products */
        $products = $this->productRepository->search($criteria, $salesChannelContext->getContext());
        static::assertCount(1, $products);

        $product = $products->first();

        $mainCategories = $product->getMainCategories();
        static::assertNotNull($mainCategories);
        static::assertCount(1, $mainCategories);

        $mainCategory = $mainCategories->first();
        static::assertEquals($categoryId, $mainCategory->getCategoryId());

        $seoUrls = $product->getSeoUrls();
        static::assertNotNull($seoUrls);

        /** @var SeoUrlEntity|null $canonical */
        $canonical = $seoUrls->filterByProperty('isCanonical', true)->filterByProperty('salesChannelId', $salesChannelId)->first();
        static::assertNotNull($canonical);
        static::assertEquals('awesome-category/foo-bar', $canonical->getSeoPathInfo());
    }

    /**
     * @depends testSeoUrlWithMainCategory
     */
    public function testSeoUrlWithMainCategoryChange(): void
    {
        static::markTestSkipped('extractIdsToUpdate must be fixed first');
        $salesChannelId = Uuid::randomHex();
        $salesChannelContext = $this->createStorefrontSalesChannelContext($salesChannelId, 'test');

        $this->createProductUrlTemplate(
            $salesChannelContext,
            '{{ product.mainCategory.name }}/{{ product.name }}'
        );

        $categoryId = Uuid::randomHex();
        $this->categoryRepository->create([[
            'id' => $categoryId,
            'name' => 'awesome category',
        ]], Context::createDefaultContext());

        $id = $this->createTestProduct([
            'mainCategories' => [
                [
                    'salesChannelId' => $salesChannelId,
                    'categoryId' => $categoryId,
                ],
            ],
        ]);

        // update existing category
        $this->categoryRepository->update(
            [[
                'id' => $categoryId,
                'name' => 'super duper cat',
            ]],
            Context::createDefaultContext()
        );

        $criteria = new Criteria([$id]);
        $criteria->addAssociation('seoUrls');
        $criteria->addAssociation('mainCategories');

        /** @var ProductCollection $products */
        $products = $this->productRepository->search($criteria, $salesChannelContext->getContext());
        static::assertCount(1, $products);

        $product = $products->first();
        static::assertNotNull($product);

        $seoUrls = $product->getSeoUrls();
        static::assertNotNull($seoUrls);

        /** @var SeoUrlEntity|null $canonical */
        $canonical = $seoUrls->filterByProperty('isCanonical', true)->filterByProperty('salesChannelId', $salesChannelId)->first();
        static::assertNotNull($canonical);
        static::assertEquals('super-duper-cat/foo-bar', $canonical->getSeoPathInfo());
    }

    /**
     * @depends testMainCategoryLoaded
     */
    public function testDeleteCategoryDeletesMainCategory(): void
    {
        $salesChannelId = Uuid::randomHex();
        $this->createStorefrontSalesChannelContext($salesChannelId, 'test');

        $categoryId = Uuid::randomHex();
        $this->categoryRepository->create([[
            'id' => $categoryId,
            'name' => 'awesome category',
        ]], Context::createDefaultContext());

        $id = $this->createTestProduct([
            'mainCategories' => [
                [
                    'salesChannelId' => $salesChannelId,
                    'categoryId' => $categoryId,
                ],
            ],
        ]);

        $this->categoryRepository->delete([['id' => $categoryId]], Context::createDefaultContext());

        $result = $this->categoryRepository->search(new Criteria([$categoryId]), Context::createDefaultContext());
        static::assertEmpty($result);

        $criteria = new Criteria([$id]);
        $criteria->addAssociation('mainCategories');

        $products = $this->productRepository->search($criteria, Context::createDefaultContext());
        static::assertCount(1, $products);

        /** @var ProductEntity $product */
        $product = $products->first();

        static::assertNotNull($product->getMainCategories());
        static::assertEmpty($product->getMainCategories());
    }

    private function createTestProduct(array $additionalPayload = []): string
    {
        $id = Uuid::randomHex();
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
        ];

        $payload = array_merge_recursive($payload, $additionalPayload);

        $this->productRepository->create([
            $payload,
        ], Context::createDefaultContext());

        return $id;
    }

    private function createProductUrlTemplate(SalesChannelContext $salesChannelContext, string $template): string
    {
        $templateId = Uuid::randomHex();
        $this->seoUrlTemplateRepository->create(
            [
                [
                    'id' => $templateId,
                    'salesChannelId' => $salesChannelContext->getSalesChannel()->getId(),
                    'routeName' => ProductPageSeoUrlRoute::ROUTE_NAME,
                    'entityName' => ProductDefinition::ENTITY_NAME,
                    'template' => $template,
                    'isValid' => true,
                ],
            ],
            $salesChannelContext->getContext()
        );

        return $templateId;
    }
}
