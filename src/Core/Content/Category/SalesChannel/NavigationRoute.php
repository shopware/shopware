<?php declare(strict_types=1);

namespace Shopware\Core\Content\Category\SalesChannel;

use Doctrine\DBAL\Connection;
use Shopware\Core\Content\Category\CategoryCollection;
use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Content\Category\Exception\CategoryNotFoundException;
use Shopware\Core\Framework\DataAbstractionLayer\Doctrine\FetchModeHelper;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Bucket\TermsAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Metric\CountAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Bucket\TermsResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\ContainsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepository;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @phpstan-type CategoryMetaInformation array{id: string, level: int, path: string}
 */
#[Route(defaults: ['_routeScope' => ['store-api']])]
#[Package('content')]
class NavigationRoute extends AbstractNavigationRoute
{
    /**
     * @internal
     */
    public function __construct(
        private readonly Connection $connection,
        private readonly SalesChannelRepository $categoryRepository
    ) {
    }

    public function getDecorated(): AbstractNavigationRoute
    {
        throw new DecorationPatternException(self::class);
    }

    #[Route(path: '/store-api/navigation/{activeId}/{rootId}', name: 'store-api.navigation', methods: ['GET', 'POST'], defaults: ['_entity' => 'category'])]
    public function load(
        string $activeId,
        string $rootId,
        Request $request,
        SalesChannelContext $context,
        Criteria $criteria
    ): NavigationRouteResponse {
        $depth = $request->query->getInt('depth', $request->request->getInt('depth', 2));

        $metaInfo = $this->getCategoryMetaInfo($activeId, $rootId);

        $active = $this->getMetaInfoById($activeId, $metaInfo);

        $root = $this->getMetaInfoById($rootId, $metaInfo);

        // Validate the provided category is part of the sales channel
        $this->validate($activeId, $active['path'], $context);

        $isChild = $this->isChildCategory($activeId, $active['path'], $rootId);

        // If the provided activeId is not part of the rootId, a fallback to the rootId must be made here.
        // The passed activeId is therefore part of another navigation and must therefore not be loaded.
        // The availability validation has already been done in the `validate` function.
        if (!$isChild) {
            $activeId = $rootId;
        }

        $categories = new CategoryCollection();
        if ($depth > 0) {
            // Load the first two levels without using the activeId in the query
            $categories = $this->loadLevels($rootId, (int) $root['level'], $context, clone $criteria, $depth);
        }

        // If the active category is part of the provided root id, we have to load the children and the parents of the active id
        $categories = $this->loadChildren($activeId, $context, $rootId, $metaInfo, $categories, clone $criteria);

        return new NavigationRouteResponse($categories);
    }

    /**
     * @param string[] $ids
     */
    private function loadCategories(array $ids, SalesChannelContext $context, Criteria $criteria): CategoryCollection
    {
        $criteria->setIds($ids);
        $criteria->addAssociation('media');
        $criteria->setTotalCountMode(Criteria::TOTAL_COUNT_MODE_NONE);

        /** @var CategoryCollection $missing */
        $missing = $this->categoryRepository->search($criteria, $context)->getEntities();

        return $missing;
    }

    private function loadLevels(string $rootId, int $rootLevel, SalesChannelContext $context, Criteria $criteria, int $depth = 2): CategoryCollection
    {
        $criteria->addFilter(
            new ContainsFilter('path', '|' . $rootId . '|'),
            new RangeFilter('level', [
                RangeFilter::GT => $rootLevel,
                RangeFilter::LTE => $rootLevel + $depth + 1,
            ])
        );

        $criteria->addAssociation('media');

        $criteria->setLimit(null);
        $criteria->setTotalCountMode(Criteria::TOTAL_COUNT_MODE_NONE);

        /** @var CategoryCollection $levels */
        $levels = $this->categoryRepository->search($criteria, $context)->getEntities();

        $this->addVisibilityCounts($rootId, $rootLevel, $depth, $levels, $context);

        return $levels;
    }

    /**
     * @return array<string, CategoryMetaInformation>
     */
    private function getCategoryMetaInfo(string $activeId, string $rootId): array
    {
        $result = $this->connection->fetchAllAssociative('
            # navigation-route::meta-information
            SELECT LOWER(HEX(`id`)), `path`, `level`
            FROM `category`
            WHERE `id` = :activeId OR `parent_id` = :activeId OR `id` = :rootId
        ', ['activeId' => Uuid::fromHexToBytes($activeId), 'rootId' => Uuid::fromHexToBytes($rootId)]);

        if (!$result) {
            throw new CategoryNotFoundException($activeId);
        }

        return FetchModeHelper::groupUnique($result);
    }

    /**
     * @param array<string, CategoryMetaInformation> $metaInfo
     *
     * @return CategoryMetaInformation
     */
    private function getMetaInfoById(string $id, array $metaInfo): array
    {
        if (!\array_key_exists($id, $metaInfo)) {
            throw new CategoryNotFoundException($id);
        }

        return $metaInfo[$id];
    }

    /**
     * @param array<string, CategoryMetaInformation> $metaInfo
     */
    private function loadChildren(string $activeId, SalesChannelContext $context, string $rootId, array $metaInfo, CategoryCollection $categories, Criteria $criteria): CategoryCollection
    {
        $active = $this->getMetaInfoById($activeId, $metaInfo);

        unset($metaInfo[$rootId], $metaInfo[$activeId]);

        $childIds = array_keys($metaInfo);

        // Fetch all parents and first-level children of the active category, if they're not already fetched
        $missing = $this->getMissingIds($activeId, $active['path'], $childIds, $categories);
        if (empty($missing)) {
            return $categories;
        }

        $categories->merge(
            $this->loadCategories($missing, $context, $criteria)
        );

        return $categories;
    }

    /**
     * @param array<string> $childIds
     *
     * @return list<string>
     */
    private function getMissingIds(string $activeId, ?string $path, array $childIds, CategoryCollection $alreadyLoaded): array
    {
        $parentIds = array_filter(explode('|', $path ?? ''));

        $haveToBeIncluded = array_merge($childIds, $parentIds, [$activeId]);
        $included = $alreadyLoaded->getIds();
        $included = array_flip($included);

        return array_values(array_diff($haveToBeIncluded, $included));
    }

    private function validate(string $activeId, ?string $path, SalesChannelContext $context): void
    {
        $ids = array_filter([
            $context->getSalesChannel()->getFooterCategoryId(),
            $context->getSalesChannel()->getServiceCategoryId(),
            $context->getSalesChannel()->getNavigationCategoryId(),
        ]);

        foreach ($ids as $id) {
            if ($this->isChildCategory($activeId, $path, $id)) {
                return;
            }
        }

        throw new CategoryNotFoundException($activeId);
    }

    private function isChildCategory(string $activeId, ?string $path, string $rootId): bool
    {
        if ($rootId === $activeId) {
            return true;
        }

        if ($path === null) {
            return false;
        }

        if (mb_strpos($path, '|' . $rootId . '|') !== false) {
            return true;
        }

        return false;
    }

    private function addVisibilityCounts(string $rootId, int $rootLevel, int $depth, CategoryCollection $levels, SalesChannelContext $context): void
    {
        $counts = [];
        foreach ($levels as $category) {
            if (!$category->getActive() || !$category->getVisible()) {
                continue;
            }

            $parentId = $category->getParentId();
            $counts[$parentId] ??= 0;
            ++$counts[$parentId];
        }
        foreach ($levels as $category) {
            $category->setVisibleChildCount($counts[$category->getId()] ?? 0);
        }

        // Fetch additional level of categories for counting visible children that are NOT included in the original query
        $criteria = new Criteria();
        $criteria->addFilter(
            new ContainsFilter('path', '|' . $rootId . '|'),
            new EqualsFilter('level', $rootLevel + $depth + 1),
            new EqualsFilter('active', true),
            new EqualsFilter('visible', true)
        );

        $criteria->addAggregation(
            new TermsAggregation('category-ids', 'parentId', null, null, new CountAggregation('visible-children-count', 'id'))
        );

        $termsResult = $this->categoryRepository
            ->aggregate($criteria, $context)
            ->get('category-ids');

        if (!($termsResult instanceof TermsResult)) {
            return;
        }

        foreach ($termsResult->getBuckets() as $bucket) {
            $key = $bucket->getKey();

            if ($key === null) {
                continue;
            }

            $parent = $levels->get($key);

            if ($parent instanceof CategoryEntity) {
                $parent->setVisibleChildCount($bucket->getCount());
            }
        }
    }
}
