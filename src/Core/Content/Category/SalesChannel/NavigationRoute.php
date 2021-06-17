<?php declare(strict_types=1);

namespace Shopware\Core\Content\Category\SalesChannel;

use Doctrine\DBAL\Connection;
use OpenApi\Annotations as OA;
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
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Routing\Annotation\Entity;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\Framework\Routing\Annotation\Since;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepositoryInterface;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @RouteScope(scopes={"store-api"})
 */
class NavigationRoute extends AbstractNavigationRoute
{
    /**
     * @var SalesChannelRepositoryInterface
     */
    private $categoryRepository;

    /**
     * @var Connection
     */
    private $connection;

    public function __construct(
        Connection $connection,
        SalesChannelRepositoryInterface $repository
    ) {
        $this->categoryRepository = $repository;
        $this->connection = $connection;
    }

    public function getDecorated(): AbstractNavigationRoute
    {
        throw new DecorationPatternException(self::class);
    }

    /**
     * @Since("6.2.0.0")
     * @Entity("category")
     * @OA\Post(
     *      path="/navigation/{requestActiveId}/{requestRootId}",
     *      summary="Fetch a navigation menu",
     *      description="This endpoint returns categories that can be used as a page navigation. You can either return them as a tree or as a flat list. You can also control the depth of the tree.

Instead of passing uuids, you can also use one of the following aliases for the activeId and rootId parameters to get the respective navigations of your sales channel.

* main-navigation
* service-navigation
* footer-navigation",
     *      operationId="readNavigation",
     *      tags={"Store API", "Category"},
     *      @OA\Parameter(name="Api-Basic-Parameters"),
     *      @OA\Parameter(
     *          name="sw-include-seo-urls",
     *          description="Instructs Shopware to try and resolve SEO URLs for the given navigation item",
     *          @OA\Schema(type="boolean"),
     *          in="header",
     *          required=false
     *      ),
     *      @OA\Parameter(
     *          name="requestActiveId",
     *          description="Identifier of the active category in the navigation tree (if not used, just set to the same as rootId).",
     *          @OA\Schema(type="string", pattern="^[0-9a-f]{32}$"),
     *          in="path",
     *          required=true
     *      ),
     *      @OA\Parameter(
     *          name="requestRootId",
     *          description="Identifier of the root category for your desired navigation tree. You can use it to fetch sub-trees of your navigation tree.",
     *          @OA\Schema(type="string", pattern="^[0-9a-f]{32}$"),
     *          in="path",
     *          required=true
     *      ),
     *      @OA\RequestBody(
     *          required=true,
     *          @OA\JsonContent(
     *              @OA\Property(
     *                  property="depth",
     *                  description="Determines the depth of fetched navigation levels.",
     *                  @OA\Schema(type="integer", default="2")
     *              ),
     *              @OA\Property(
     *                  property="buildTree",
     *                  description="Return the categories as a tree or as a flat list.",
     *                  @OA\Schema(type="boolean", default="true")
     *              )
     *          )
     *      ),
     *      @OA\Response(
     *          response="200",
     *          description="All available navigations",
     *          @OA\JsonContent(ref="#/components/schemas/NavigationRouteResponse")
     *     )
     * )
     * @Route("/store-api/navigation/{activeId}/{rootId}", name="store-api.navigation", methods={"GET", "POST"})
     */
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
                RangeFilter::LTE => $rootLevel + $depth,
            ])
        );

        $criteria->addAssociation('media');

        $criteria->setLimit(null);
        $criteria->setTotalCountMode(Criteria::TOTAL_COUNT_MODE_NONE);

        /** @var CategoryCollection $levels */
        $levels = $this->categoryRepository->search($criteria, $context)->getEntities();

        // Count visible children that are already included in the original query
        foreach ($levels as $level) {
            $count = $levels->filter(function (CategoryEntity $category) use ($level) {
                return $category->getParentId() === $level->getId() && $category->getVisible() && $category->getActive();
            })->count();
            $level->setVisibleChildCount($count);
        }

        // Fetch additional level of categories for counting visible children that are NOT included in the original query
        $criteria = new Criteria();
        $criteria->addFilter(
            new ContainsFilter('path', '|' . $rootId . '|'),
            new EqualsFilter('level', $rootLevel + $depth + 1),
            new EqualsFilter('active', true),
            new EqualsFilter('visible', true)
        )->addAggregation(
            new TermsAggregation(
                'category-ids',
                'parentId',
                null,
                null,
                new CountAggregation('visible-children-count', 'id')
            )
        );

        $termsResult = $this->categoryRepository
            ->search($criteria, $context)
            ->getAggregations()
            ->get('category-ids');

        if ($termsResult instanceof TermsResult) {
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

        return $levels;
    }

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

    private function getMetaInfoById(string $id, array $metaInfo): array
    {
        if (!\array_key_exists($id, $metaInfo)) {
            throw new CategoryNotFoundException($id);
        }

        return $metaInfo[$id];
    }

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

    private function getMissingIds(string $activeId, ?string $path, array $childIds, CategoryCollection $alreadyLoaded): array
    {
        $parentIds = array_filter(explode('|', $path ?? ''));

        $haveToBeIncluded = array_merge($childIds, $parentIds, [$activeId]);
        $included = $alreadyLoaded->getIds();
        $included = array_flip($included);

        return array_diff($haveToBeIncluded, $included);
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
}
