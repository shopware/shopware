<?php declare(strict_types=1);

namespace Shopware\Core\Content\Category\SalesChannel;

use Doctrine\DBAL\Connection;
use OpenApi\Annotations as OA;
use Shopware\Core\Content\Category\CategoryCollection;
use Shopware\Core\Content\Category\Exception\CategoryNotFoundException;
use Shopware\Core\Framework\DataAbstractionLayer\Doctrine\FetchModeHelper;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\ContainsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\RequestCriteriaBuilder;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Routing\Annotation\Entity;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepositoryInterface;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
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

    /**
     * @var SalesChannelCategoryDefinition
     */
    private $categoryDefinition;

    /**
     * @var RequestCriteriaBuilder
     */
    private $requestCriteriaBuilder;

    public function __construct(
        Connection $connection,
        SalesChannelRepositoryInterface $repository,
        SalesChannelCategoryDefinition $categoryDefinition,
        RequestCriteriaBuilder $requestCriteriaBuilder
    ) {
        $this->categoryRepository = $repository;
        $this->connection = $connection;
        $this->categoryDefinition = $categoryDefinition;
        $this->requestCriteriaBuilder = $requestCriteriaBuilder;
    }

    public function getDecorated(): AbstractNavigationRoute
    {
        throw new DecorationPatternException(self::class);
    }

    /**
     * @Entity("category")
     * @OA\Get(
     *      path="/navigation/{requestActiveId}/{requestRootId}",
     *      description="Loads all available navigations",
     *      operationId="readNavigation",
     *      tags={"Store API", "Navigation"},
     *      @OA\Parameter(name="Api-Basic-Parameters"),
     *      @OA\Parameter(
     *          parameter="buildTree",
     *          name="buildTree",
     *          in="query",
     *          description="Build category tree",
     *          @OA\Schema(type="boolean")
     *      ),
     *      @OA\Response(
     *          response="200",
     *          description="All available navigations",
     *          @OA\JsonContent(ref="#/definitions/NavigationRouteResponse")
     *     )
     * )
     * @Route("/store-api/v{version}/navigation/{requestActiveId}/{requestRootId}", name="store-api.navigation", methods={"GET", "POST"})
     */
    public function load(
        string $requestActiveId,
        string $requestRootId,
        Request $request,
        SalesChannelContext $context,
        ?Criteria $criteria = null
    ): NavigationRouteResponse {
        $buildTree = $request->query->getBoolean('buildTree', $request->request->getBoolean('buildTree', true));
        $depth = $request->query->getInt('depth', $request->request->getInt('depth', 2));

        $activeId = $this->resolveAliasId($requestActiveId, $context->getSalesChannel());
        $rootId = $this->resolveAliasId($requestRootId, $context->getSalesChannel());

        if ($activeId === null) {
            throw new CategoryNotFoundException($requestActiveId);
        }

        if ($rootId === null) {
            throw new CategoryNotFoundException($requestRootId);
        }

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

        // @deprecated tag:v6.4.0 - Criteria will be required
        if (!$criteria) {
            $criteria = $this->requestCriteriaBuilder->handleRequest($request, new Criteria(), $this->categoryDefinition, $context->getContext());
        }

        // Load the first two levels without using the activeId in the query, so this can be cached
        $categories = $this->loadLevels($rootId, (int) $root['level'], $context, clone $criteria, $depth);

        // If the active category is part of the provided root id, we have to load the children and the parents of the active id
        $categories = $this->loadChildren($activeId, $context, $rootId, $metaInfo, $categories, clone $criteria);

        if ($buildTree) {
            $categories = $this->buildTree($rootId, $categories->getElements());
        }

        return new NavigationRouteResponse($categories);
    }

    private function buildTree(?string $parentId, array $categories): CategoryCollection
    {
        $children = new CategoryCollection();
        foreach ($categories as $key => $category) {
            if ($category->getParentId() !== $parentId) {
                continue;
            }

            unset($categories[$key]);

            $children->add($category);
        }

        $children->sortByPosition();

        $items = new CategoryCollection();
        foreach ($children as $child) {
            if (!$child->getActive() || !$child->getVisible()) {
                continue;
            }

            $child->setChildren($this->buildTree($child->getId(), $categories));

            $items->add($child);
        }

        return $items;
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

        return $levels;
    }

    private function getCategoryMetaInfo(string $activeId, string $rootId): array
    {
        $result = $this->connection->fetchAll('
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

    private function resolveAliasId(string $id, SalesChannelEntity $salesChannelEntity): ?string
    {
        switch ($id) {
            case 'main-navigation':
                return $salesChannelEntity->getNavigationCategoryId();
            case 'service-navigation':
                return $salesChannelEntity->getServiceCategoryId();
            case 'footer-navigation':
                return $salesChannelEntity->getFooterCategoryId();
            default:
                return $id;
        }
    }
}
