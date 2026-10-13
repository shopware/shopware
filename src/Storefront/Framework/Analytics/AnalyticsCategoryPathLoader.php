<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Analytics;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Shopware\Core\Content\Category\CategoryCollection;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * Loads the category path analytics reports for products that are shown outside a category, such as
 * on the homepage or a landing page, where the page breadcrumb describes no category.
 *
 * All products of a page are resolved together with a fixed number of queries, whatever their
 * number: the main category of the sales channel, else the deepest assigned category inside its
 * navigation, then the names of that path. A variant uses the categories of its parent unless it has
 * its own.
 *
 * @internal
 */
#[Package('discovery')]
class AnalyticsCategoryPathLoader
{
    /**
     * @param EntityRepository<CategoryCollection> $categoryRepository
     */
    public function __construct(
        private readonly Connection $connection,
        private readonly EntityRepository $categoryRepository,
    ) {
    }

    /**
     * @param list<SalesChannelProductEntity> $products
     *
     * @return array<string, list<string>> the category names from the top level down, per product id
     */
    public function load(array $products, SalesChannelContext $context): array
    {
        if ($products === []) {
            return [];
        }

        $navigationId = $context->getSalesChannel()->getNavigationCategoryId();

        // a variant without own categories inherits those of its parent
        $candidates = [];
        foreach ($products as $product) {
            $candidates[$product->getId()] = array_values(array_filter([$product->getId(), $product->getParentId()]));
        }
        $lookupIds = array_values(array_unique(array_merge(...array_values($candidates))));

        $mainCategories = $this->connection->fetchAllKeyValue(
            'SELECT LOWER(HEX(main_category.product_id)), LOWER(HEX(main_category.category_id))
             FROM main_category
             WHERE main_category.sales_channel_id = :salesChannelId
               AND main_category.product_id IN (:ids)
               AND main_category.product_version_id = :version',
            [
                'salesChannelId' => Uuid::fromHexToBytes($context->getSalesChannelId()),
                'ids' => Uuid::fromHexToBytesList($lookupIds),
                'version' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
            ],
            ['ids' => ArrayParameterType::BINARY]
        );

        // the assigned categories inside the navigation of the sales channel, deepest first
        $assigned = $this->connection->fetchAllAssociative(
            'SELECT LOWER(HEX(product_category.product_id)) AS product_id,
                    LOWER(HEX(category.id)) AS category_id,
                    category.path AS path
             FROM product_category
             INNER JOIN category ON category.id = product_category.category_id
                AND category.version_id = product_category.category_version_id
             WHERE product_category.product_id IN (:ids)
               AND product_category.product_version_id = :version
               AND category.active = 1
               AND category.path LIKE :navigation
             ORDER BY category.level DESC, category.id',
            [
                'ids' => Uuid::fromHexToBytesList($lookupIds),
                'version' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
                'navigation' => '%|' . $navigationId . '|%',
            ],
            ['ids' => ArrayParameterType::BINARY]
        );

        $deepest = [];
        $paths = [];
        foreach ($assigned as $row) {
            $deepest[$row['product_id']] ??= $row['category_id'];
            $paths[$row['category_id']] = (string) $row['path'];
        }

        $mainPaths = $this->loadPaths(array_values(array_diff(array_values($mainCategories), array_keys($paths))));
        $paths += $mainPaths;

        $chosen = [];
        foreach ($candidates as $productId => $ids) {
            foreach ($ids as $id) {
                $categoryId = $mainCategories[$id] ?? null;
                // a main category outside the navigation, such as one of the footer, is no path
                if ($categoryId !== null && str_contains($paths[$categoryId] ?? '', '|' . $navigationId . '|')) {
                    $chosen[$productId] = $categoryId;

                    break;
                }

                if (isset($deepest[$id])) {
                    $chosen[$productId] = $deepest[$id];

                    break;
                }
            }
        }

        if ($chosen === []) {
            return [];
        }

        // the path below the navigation root, as the breadcrumb shows it, plus the category itself
        $pathIds = [];
        foreach ($chosen as $categoryId) {
            $pathIds[$categoryId] = array_values(array_filter(
                [...explode('|', $paths[$categoryId] ?? ''), $categoryId],
                static fn (string $id) => $id !== '' && $id !== $navigationId
            ));
        }

        $categories = $this->categoryRepository->search(
            (new Criteria(array_values(array_unique(array_merge(...array_values($pathIds))))))->setTitle('analytics::category-paths'),
            $context->getContext()
        )->getEntities();

        $names = [];
        foreach ($chosen as $productId => $categoryId) {
            $names[$productId] = array_values(array_filter(array_map(
                static fn (string $id) => $categories->get($id)?->getTranslation('name'),
                $pathIds[$categoryId]
            ), static fn (mixed $name) => \is_string($name) && $name !== ''));
        }

        return $names;
    }

    /**
     * @param list<string> $categoryIds
     *
     * @return array<string, string> the path per category id
     */
    private function loadPaths(array $categoryIds): array
    {
        if ($categoryIds === []) {
            return [];
        }

        /** @var array<string, string> $paths */
        $paths = $this->connection->fetchAllKeyValue(
            'SELECT LOWER(HEX(category.id)), COALESCE(category.path, \'\')
             FROM category
             WHERE category.id IN (:ids) AND category.version_id = :version',
            [
                'ids' => Uuid::fromHexToBytesList($categoryIds),
                'version' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
            ],
            ['ids' => ArrayParameterType::BINARY]
        );

        return $paths;
    }
}
