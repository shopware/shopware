<?php declare(strict_types=1);

namespace Shopware\Core\Content\Category\Service;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Shopware\Core\Content\Breadcrumb\BreadcrumbException;
use Shopware\Core\Content\Breadcrumb\Struct\Breadcrumb;
use Shopware\Core\Content\Breadcrumb\Struct\BreadcrumbCollection;
use Shopware\Core\Content\Category\CategoryCollection;
use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Content\Category\Util\CategoryBreadcrumbHelper;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductCollection;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\Content\Seo\MainCategory\MainCategoryCollection;
use Shopware\Core\Content\Seo\SeoUrlRoute\EntityRouteResolver;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\AndFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\ContainsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\OrFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepository;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;

#[Package('discovery')]
class CategoryBreadcrumbBuilder
{
    /**
     * @internal
     *
     * @param EntityRepository<CategoryCollection> $categoryRepository
     * @param SalesChannelRepository<SalesChannelProductCollection> $productRepository
     */
    public function __construct(
        private readonly EntityRepository $categoryRepository,
        private readonly SalesChannelRepository $productRepository,
        private readonly Connection $connection,
        private readonly EntityRouteResolver $entityRouteResolver,
    ) {
    }

    public function getProductBreadcrumbUrls(string $productId, string $referrerCategoryId, SalesChannelContext $salesChannelContext): BreadcrumbCollection
    {
        $product = $this->loadProduct($productId, $salesChannelContext);
        $category = $this->getProductCategoryByReferrer($referrerCategoryId, $product, $salesChannelContext);
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

        return $this->categoryRepository
            ->search($criteria, $context)
            ->getEntities()
            ->get($categoryId);
    }

    public function getProductSeoCategory(ProductEntity $product, SalesChannelContext $context): ?CategoryEntity
    {
        $category = $this->getMainCategory($product, $context);
        if ($category !== null) {
            return $category;
        }

        $categoryIds = $product->getCategoryIds() ?? [];
        $productStreamIds = $product->getStreamIds() ?? [];

        if ($productStreamIds === [] && $categoryIds === []) {
            return null;
        }

        $criteria = new Criteria();
        $criteria->setTitle('breadcrumb-builder');
        $criteria->setLimit(1);
        $criteria->addFilter($this->getCategoryVisibleForCustomerFilter($context));

        if ($categoryIds !== []) {
            $criteria->setIds($categoryIds);
        } else {
            $criteria->addFilter(new EqualsAnyFilter('productStream.id', $productStreamIds));
            $criteria->addFilter(new EqualsFilter('productAssignmentType', CategoryDefinition::PRODUCT_ASSIGNMENT_TYPE_PRODUCT_STREAM));
        }

        $criteria->addSorting(new FieldSorting('level', FieldSorting::DESCENDING));

        return $this->categoryRepository->search($criteria, $context->getContext())->getEntities()->first();
    }

    public function getProductCategoryByReferrer(
        string $referrerCategoryId,
        SalesChannelProductEntity $product,
        SalesChannelContext $salesChannelContext
    ): ?CategoryEntity {
        if (\in_array($referrerCategoryId, $product->getCategoryTree() ?? [], true)) {
            $referrerCategory = $this->loadCategory($referrerCategoryId, $salesChannelContext->getContext());

            if ($referrerCategory instanceof CategoryEntity && $this->isCategoryVisibleForCustomer($referrerCategory, $salesChannelContext)) {
                return $referrerCategory;
            }
        }

        return $this->getProductSeoCategory($product, $salesChannelContext);
    }

    public function getCategoryBreadcrumbUrls(CategoryEntity $category, Context $context, SalesChannelEntity $salesChannel): BreadcrumbCollection
    {
        $seoBreadcrumb = $this->build($category, $salesChannel);
        $categoryIds = array_keys($seoBreadcrumb ?? []);

        if ($categoryIds === []) {
            return new BreadcrumbCollection();
        }

        $categories = $this->loadCategories($categoryIds, $context, $salesChannel);
        $seoUrls = $this->loadSeoUrls($categoryIds, $context, $salesChannel);

        return $this->convertCategoriesToBreadcrumbUrls($categories, $seoUrls);
    }

    /**
     * @return array<string, string>|null
     */
    public function build(CategoryEntity $category, ?SalesChannelEntity $salesChannel = null, ?string $navigationCategoryId = null): ?array
    {
        return CategoryBreadcrumbHelper::build($category, $salesChannel, $navigationCategoryId);
    }

    private function loadProduct(string $productId, SalesChannelContext $salesChannelContext): SalesChannelProductEntity
    {
        $criteria = new Criteria();
        $criteria->setIds([$productId]);
        $criteria->setTitle('breadcrumb::product::data');

        $product = $this->productRepository
            ->search($criteria, $salesChannelContext)
            ->getEntities()
            ->first();

        if (!$product instanceof SalesChannelProductEntity) {
            throw BreadcrumbException::productNotFound($productId);
        }

        return $product;
    }

    private function getMainCategory(ProductEntity $product, SalesChannelContext $context): ?CategoryEntity
    {
        if ($mainCategory = $this->getMainCategoryFromProduct($product, $context)) {
            return $mainCategory;
        }

        $categoryIds = $product->getCategoryIds() ?? [];

        if ($categoryIds === []) {
            return null;
        }

        $criteria = new Criteria([$product->getId()]);
        $criteria->setTitle('breadcrumb-builder::main-category');
        $criteria->addAssociation('mainCategories.category');
        $criteria->getAssociation('mainCategories')
            ->setLimit(1)
            ->addFilter(new AndFilter([
                new EqualsFilter('salesChannelId', $context->getSalesChannelId()),
                new EqualsAnyFilter('category.id', $categoryIds),
                $this->getCategoryVisibleForCustomerFilter($context, 'category.'),
            ]));

        $product = $context->getContext()->enableInheritance(fn (): ?ProductEntity => $this->productRepository->search($criteria, $context)->getEntities()->first());

        if (!$product instanceof ProductEntity || !$product->getMainCategories() instanceof MainCategoryCollection) {
            return null;
        }

        return $product->getMainCategories()->first()?->getCategory();
    }

    private function getMainCategoryFromProduct(ProductEntity $product, SalesChannelContext $context): ?CategoryEntity
    {
        if (!$product->getMainCategories()?->count()) {
            return null;
        }

        $category = $product->getMainCategories()->filterBySalesChannelId($context->getSalesChannelId())->first()?->getCategory();

        if (
            !$category instanceof CategoryEntity
            || !\in_array($category->getId(), $product->getCategoryIds() ?? [], true)
            || !$this->isCategoryVisibleForCustomer($category, $context)
        ) {
            return null;
        }

        return $category;
    }

    /**
     * @param array<string> $categoryIds
     */
    private function loadCategories(array $categoryIds, Context $context, SalesChannelEntity $salesChannel): CategoryCollection
    {
        $criteria = new Criteria($categoryIds);
        $criteria->setTitle('breadcrumb::categories::data');
        $criteria->addFilter($this->getSalesChannelFilter($salesChannel));

        return $this->categoryRepository->search($criteria, $context)->getEntities();
    }

    /**
     * @param array<string> $categoryIds
     *
     * @return list<array<string, string|mixed>>
     */
    private function loadSeoUrls(array $categoryIds, Context $context, SalesChannelEntity $salesChannel): array
    {
        $query = $this->connection->createQueryBuilder();
        $query->select(
            'LOWER(HEX(id)) as id',
            'LOWER(HEX(foreign_key)) as categoryId',
            'path_info as pathInfo',
            'seo_path_info as seoPathInfo',
        );
        $query->from('seo_url');
        $query->where('seo_url.is_canonical = 1');
        $query->andWhere('seo_url.route_name = :routeName');
        $query->andWhere('seo_url.language_id = :languageId');
        $query->andWhere('seo_url.sales_channel_id = :salesChannelId');
        $query->andWhere('seo_url.foreign_key IN (:categoryIds)');
        $routeName = $this->entityRouteResolver->getRouteNameForEntityName(CategoryDefinition::ENTITY_NAME, $salesChannel->getTypeId());
        $query->setParameter('routeName', $routeName);
        $query->setParameter('languageId', Uuid::fromHexToBytes($context->getLanguageId()));
        $query->setParameter('salesChannelId', Uuid::fromHexToBytes($salesChannel->getId()));
        $query->setParameter('categoryIds', Uuid::fromHexToBytesList($categoryIds), ArrayParameterType::BINARY);

        return $query->executeQuery()->fetchAllAssociative();
    }

    /**
     * @param list<array<string, string|mixed>> $seoUrls
     */
    private function convertCategoriesToBreadcrumbUrls(CategoryCollection $categories, array $seoUrls): BreadcrumbCollection
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

            if ($categorySeoUrls === []) {
                if ($category->getType() !== CategoryDefinition::TYPE_FOLDER) {
                    $categoryBreadcrumb->path = 'navigation/' . $categoryId;
                }
            } else {
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
            }

            $seoBreadcrumbCollection[$categoryId] = $categoryBreadcrumb;
        }

        return new BreadcrumbCollection(array_values($seoBreadcrumbCollection));
    }

    /**
     * @param array<int, array<string, string|mixed>> $seoUrls
     *
     * @return array<int, array<string, string|mixed>>
     */
    private function filterCategorySeoUrls(array $seoUrls, string $categoryId): array
    {
        return array_filter($seoUrls, static function (array $seoUrl) use ($categoryId): bool {
            return $seoUrl['categoryId'] === $categoryId;
        });
    }

    private function isCategoryVisibleForCustomer(CategoryEntity $category, SalesChannelContext $context): bool
    {
        $salesChannel = $context->getSalesChannel();

        if (!$category->getActive() || !$category->getVisible()) {
            return false;
        }

        if (array_intersect(\array_slice(explode('|', $category->getPath() ?? ''), 1, -1), array_filter([
            $salesChannel->getNavigationCategoryId(),
            $salesChannel->getServiceCategoryId(),
            $salesChannel->getFooterCategoryId(),
        ])) === []) {
            return false;
        }

        return true;
    }

    private function getSalesChannelFilter(SalesChannelEntity $salesChannel, string $fieldPath = ''): MultiFilter
    {
        return new OrFilter(array_map(static fn (string $id) => new ContainsFilter($fieldPath . 'path', '|' . $id . '|'), array_filter([
            $salesChannel->getNavigationCategoryId(),
            $salesChannel->getServiceCategoryId(),
            $salesChannel->getFooterCategoryId(),
        ])));
    }

    private function getCategoryVisibleForCustomerFilter(SalesChannelContext $context, string $fieldPath = ''): AndFilter
    {
        $salesChannel = $context->getSalesChannel();

        return new AndFilter([
            new EqualsFilter($fieldPath . 'active', true),
            new EqualsFilter($fieldPath . 'visible', true),
            $this->getSalesChannelFilter($salesChannel, $fieldPath),
        ]);
    }
}
