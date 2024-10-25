<?php declare(strict_types=1);

namespace Shopware\Core\Content\Category\Service;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Shopware\Core\Content\Breadcrumb\BreadcrumbException;
use Shopware\Core\Content\Breadcrumb\Struct\Breadcrumb;
use Shopware\Core\Content\Category\CategoryCollection;
use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Content\Category\SalesChannel\SalesChannelEntrypointService;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\Content\Seo\MainCategory\MainCategoryEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\AndFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\ContainsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\OrFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepository;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Shopware\Storefront\Framework\Seo\SeoUrlRoute\NavigationPageSeoUrlRoute;

/**
 * @experimental stableVersion:v6.7.0 feature:BREADCRUMB_STORE_API
 * related methods: getProductBreadcrumbUrls, loadProduct, getCategoryForProduct, loadCategory,
 * getCategoryBreadcrumbUrls, loadCategories, loadSeoUrls, convertCategoriesToBreadcrumbUrls, filterCategorySeoUrls
 */
#[Package('inventory')]
class CategoryBreadcrumbBuilder
{
    /**
     * @internal
     */
    public function __construct(
        private readonly EntityRepository $categoryRepository,
        private readonly SalesChannelRepository $productRepository,
        private readonly Connection $connection,
        private readonly SalesChannelEntrypointService $entrypointService
    ) {
    }

    /**
     * @return array<int, Breadcrumb>
     */
    public function getProductBreadcrumbUrls(string $productId, string $referrerCategoryId, SalesChannelContext $salesChannelContext): array
    {
        $product = $this->loadProduct($productId, $salesChannelContext);
        $category = $this->getCategoryForProduct($referrerCategoryId, $product, $salesChannelContext);
        if ($category === null) {
            throw BreadcrumbException::categoryNotFoundForProduct($productId);
        }

        return $this->getCategoryBreadcrumbUrls(
            $category,
            $salesChannelContext->getContext(),
            $salesChannelContext->getSalesChannel()
        );
    }

    public function loadCategory(string $categoryId, Context $context): ?CategoryEntity
    {
        $criteria = new Criteria([$categoryId]);
        $criteria->setTitle('breadcrumb::category::data');

        $category = $this->categoryRepository
            ->search($criteria, $context)
            ->get($categoryId);

        if (!$category instanceof CategoryEntity) {
            return null;
        }

        return $category;
    }

    public function getProductSeoCategory(ProductEntity $product, SalesChannelContext $context): ?CategoryEntity
    {
        $category = $this->getMainCategory($product, $context);

        if ($category !== null) {
            return $category;
        }

        $categoryIds = $product->getCategoryIds() ?? [];
        $productStreamIds = $product->getStreamIds() ?? [];

        if (empty($productStreamIds) && empty($categoryIds)) {
            return null;
        }

        $criteria = new Criteria();
        $criteria->setTitle('breadcrumb-builder');
        $criteria->setLimit(1);
        $criteria->addFilter(new EqualsFilter('active', true));

        if (!empty($categoryIds)) {
            $criteria->setIds($categoryIds);
        } else {
            $criteria->addFilter(new EqualsAnyFilter('productStream.id', $productStreamIds));
            $criteria->addFilter(new EqualsFilter('productAssignmentType', CategoryDefinition::PRODUCT_ASSIGNMENT_TYPE_PRODUCT_STREAM));
        }

        $criteria->addFilter($this->getSalesChannelFilter($context->getSalesChannel()));

        $categories = $this->categoryRepository->search($criteria, $context->getContext());

        if ($categories->count() > 0) {
            /** @var CategoryEntity|null $category */
            $category = $categories->first();

            return $category;
        }

        return null;
    }

    /**
     * @return array<int, Breadcrumb>
     */
    public function getCategoryBreadcrumbUrls(CategoryEntity $category, Context $context, SalesChannelEntity $salesChannel): array
    {
        $seoBreadcrumb = $this->build($category, $salesChannel);
        $categoryIds = array_keys($seoBreadcrumb ?? []);

        if (empty($categoryIds)) {
            return [];
        }

        $categories = $this->loadCategories($categoryIds, $context, $salesChannel);
        $seoUrls = $this->loadSeoUrls($categoryIds, $context, $salesChannel);

        return $this->convertCategoriesToBreadcrumbUrls($categories, $seoUrls);
    }

    /**
     * @return array<mixed>|null
     */
    public function build(CategoryEntity $category, ?SalesChannelEntity $salesChannel = null, ?string $navigationCategoryId = null): ?array
    {
        $categoryBreadcrumb = $category->getPlainBreadcrumb();

        // If the current SalesChannel is null ( which refers to the default template SalesChannel) or
        // this category has no root, we return the full breadcrumb
        if ($salesChannel === null && $navigationCategoryId === null) {
            return $categoryBreadcrumb;
        }

        $entryPoints = [
            $navigationCategoryId,
        ];

        if ($salesChannel !== null) {
            $entryPoints = array_merge($entryPoints, $this->entrypointService->getEntrypointIds($salesChannel));
        }

        $entryPoints = array_filter($entryPoints);

        $keys = array_keys($categoryBreadcrumb);

        foreach ($entryPoints as $entryPoint) {
            // Check where this category is located in relation to the navigation entry point of the sales channel
            $pos = array_search($entryPoint, $keys, true);

            if ($pos !== false) {
                // Remove all breadcrumbs preceding the navigation category
                return \array_slice($categoryBreadcrumb, $pos + 1);
            }
        }

        return $categoryBreadcrumb;
    }

    private function loadProduct(string $productId, SalesChannelContext $salesChannelContext): SalesChannelProductEntity
    {
        $criteria = new Criteria();
        $criteria->setIds([$productId]);
        $criteria->setTitle('breadcrumb::product::data');

        $product = $this->productRepository
            ->search($criteria, $salesChannelContext)
            ->first();

        if (!($product instanceof SalesChannelProductEntity)) {
            throw BreadcrumbException::productNotFound($productId);
        }

        return $product;
    }

    private function getCategoryForProduct(
        string $referrerCategoryId,
        SalesChannelProductEntity $product,
        SalesChannelContext $salesChannelContext
    ): ?CategoryEntity {
        $categoryIds = $product->getCategoryIds();
        if ($categoryIds !== null && \in_array($referrerCategoryId, $categoryIds, true)) {
            return $this->loadCategory($referrerCategoryId, $salesChannelContext->getContext());
        }

        return $this->getProductSeoCategory($product, $salesChannelContext);
    }

    private function getMainCategory(ProductEntity $product, SalesChannelContext $context): ?CategoryEntity
    {
        $criteria = new Criteria();
        $criteria->setLimit(1);
        $criteria->setTitle('breadcrumb-builder::main-category');

        if (($product->getMainCategories() === null || $product->getMainCategories()->count() <= 0) && $product->getParentId() !== null) {
            $criteria->addFilter($this->getMainCategoryFilter($product->getParentId(), $context));
        } else {
            $criteria->addFilter($this->getMainCategoryFilter($product->getId(), $context));
        }

        $categories = $this->categoryRepository->search($criteria, $context->getContext())->getEntities();
        if ($categories->count() <= 0) {
            return null;
        }

        $firstCategory = $categories->first();

        /** @var CategoryEntity|null $entity */
        $entity = $firstCategory instanceof MainCategoryEntity ? $firstCategory->getCategory() : $firstCategory;

        return $product->getCategoryIds() !== null && $entity !== null && \in_array($entity->getId(), $product->getCategoryIds(), true) ? $entity : null;
    }

    private function getMainCategoryFilter(string $productId, SalesChannelContext $context): AndFilter
    {
        return new AndFilter([
            new EqualsFilter('mainCategories.productId', $productId),
            new EqualsFilter('mainCategories.salesChannelId', $context->getSalesChannelId()),
            $this->getSalesChannelFilter($context->getSalesChannel()),
        ]);
    }

    private function getSalesChannelFilter(SalesChannelEntity $salesChannel): MultiFilter
    {
        $ids = $this->entrypointService->getEntrypointIds($salesChannel);

        return new OrFilter(array_map(static fn (string $id) => new ContainsFilter('path', '|' . $id . '|'), $ids));
    }

    /**
     * @param array<string> $categoryIds
     */
    private function loadCategories(array $categoryIds, Context $context, SalesChannelEntity $salesChannel): CategoryCollection
    {
        $criteria = new Criteria($categoryIds);
        $criteria->setTitle('breadcrumb::categories::data');
        $criteria->addFilter($this->getSalesChannelFilter($salesChannel));
        /** @var EntitySearchResult<CategoryCollection> $searchResult */
        $searchResult = $this->categoryRepository->search($criteria, $context);

        return $searchResult->getEntities();
    }

    /**
     * @param array<string> $categoryIds
     *
     * @return array<int, array<string, string|mixed>>
     */
    private function loadSeoUrls(array $categoryIds, Context $context, SalesChannelEntity $salesChannel): array
    {
        $query = $this->connection->createQueryBuilder();
        $query->select([
            'LOWER(HEX(id)) as id',
            'LOWER(HEX(foreign_key)) as categoryId',
            'path_info as pathInfo',
            'seo_path_info as seoPathInfo',
        ]);
        $query->from('seo_url');
        $query->where('seo_url.is_canonical = 1');
        $query->andWhere('seo_url.route_name = :routeName');
        $query->andWhere('seo_url.language_id = :languageId');
        $query->andWhere('seo_url.sales_channel_id = :salesChannelId');
        $query->andWhere('seo_url.foreign_key IN (:categoryIds)');
        $query->setParameter('routeName', NavigationPageSeoUrlRoute::ROUTE_NAME);
        $query->setParameter('languageId', Uuid::fromHexToBytes($context->getLanguageId()));
        $query->setParameter('salesChannelId', Uuid::fromHexToBytes($salesChannel->getId()));
        $query->setParameter('categoryIds', Uuid::fromHexToBytesList($categoryIds), ArrayParameterType::BINARY);

        return $query->executeQuery()->fetchAllAssociative();
    }

    /**
     * @param array<int, array<string, string|mixed>> $seoUrls
     *
     * @return array<int, Breadcrumb>
     */
    private function convertCategoriesToBreadcrumbUrls(CategoryCollection $categories, array $seoUrls): array
    {
        $seoBreadcrumbCollection = [];
        foreach ($categories as $category) {
            $categoryId = $category->getId();
            $categorySeoUrls = $this->filterCategorySeoUrls($seoUrls, $categoryId);
            $translated = $category->getTranslated();
            unset($translated['breadcrumb'], $translated['name']);
            $categoryBreadcrumb = new Breadcrumb(
                $category->getTranslation('name'),
                $categoryId,
                $category->getType(),
                $translated,
            );

            if (!$categorySeoUrls || \count($categorySeoUrls) === 0) {
                $categoryBreadcrumb->path = 'navigation/' . $categoryId;
                continue;
            }

            foreach ($categorySeoUrls as $categorySeoUrl) {
                if ($categoryBreadcrumb->path === '') {
                    $categoryBreadcrumb->path = (isset($categorySeoUrl['seoPathInfo']) && $categorySeoUrl['seoPathInfo'] !== '')
                        ? $categorySeoUrl['seoPathInfo'] : $categorySeoUrl['pathInfo'];
                }
                if ($categoryId === $categorySeoUrl['categoryId']) {
                    unset($categorySeoUrl['categoryId']); // remove redundant data
                }
                $categoryBreadcrumb->seoUrls[] = $categorySeoUrl;
            }

            $seoBreadcrumbCollection[$categoryId] = $categoryBreadcrumb;
        }

        return array_values($seoBreadcrumbCollection);
    }

    /**
     * @param array<int, array<string, string|mixed>> $seoUrls
     *
     * @return array<int, array<string, string|mixed>>
     */
    private function filterCategorySeoUrls(array $seoUrls, string $categoryId): array
    {
        return array_filter($seoUrls, function (array $seoUrl) use ($categoryId) {
            return $seoUrl['categoryId'] === $categoryId;
        });
    }
}
