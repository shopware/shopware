<?php declare(strict_types=1);

namespace Shopware\Core\Content\Category\SalesChannel;

use Shopware\Core\Content\Category\CategoryCollection;
use Shopware\Core\Content\Category\CategoryException;
use Shopware\Core\Content\Category\CategoryService;
use Shopware\Core\Content\Category\Event\NavigationRouteValidateEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @phpstan-type CategoryMetaInformation array{id: string, level: int, path: string}
 */
#[Route(defaults: ['_routeScope' => ['store-api']])]
#[Package('inventory')]
class NavigationRoute extends AbstractNavigationRoute
{
    /**
     * @internal
     */
    public function __construct(
        private readonly CategoryService $categoryService,
        private readonly EventDispatcherInterface $eventDispatcher
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

        $metaInfo = $this->categoryService->getCategoryMetaInfo($activeId, $rootId);

        $active = $this->getMetaInfoById($activeId, $metaInfo);

        $root = $this->getMetaInfoById($rootId, $metaInfo);

        // Validate the provided category is part of the sales channel
        $this->validate($activeId, $active['path'], $context);

        $isChild = $this->categoryService->isChildCategory($activeId, $active['path'], $rootId);

        // If the provided activeId is not part of the rootId, a fallback to the rootId must be made here.
        // The passed activeId is therefore part of another navigation and must therefore not be loaded.
        // The availability validation has already been done in the `validate` function.
        if (!$isChild) {
            $activeId = $rootId;
        }

        $categories = new CategoryCollection();
        if ($depth > 0) {
            // Load the first two levels without using the activeId in the query
            $categories = $this->categoryService->loadLevels($rootId, (int) $root['level'], $context, clone $criteria, $depth);
        }

        // If the active category is part of the provided root id, we have to load the children and the parents of the active id
        $categories = $this->loadChildren($activeId, $context, $rootId, $metaInfo, $categories, clone $criteria);

        return new NavigationRouteResponse($categories);
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
            $this->categoryService->loadCategories($missing, $context, $criteria)
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

    /**
     * @param array<string, CategoryMetaInformation> $metaInfo
     *
     * @return CategoryMetaInformation
     */
    private function getMetaInfoById(string $id, array $metaInfo): array
    {
        if (!\array_key_exists($id, $metaInfo)) {
            throw CategoryException::categoryNotFound($id);
        }

        return $metaInfo[$id];
    }

      /**
     * @param array<string, CategoryMetaInformation> $metaInfo
     */

    private function validate(string $activeId, ?string $path, SalesChannelContext $context): void
    {
        $event = new NavigationRouteValidateEvent($activeId, $path, $context);
        $validateEvent = $this->eventDispatcher->dispatch($event);

        if($validateEvent->isValid() === false) {
            throw CategoryException::categoryNotFound($activeId);
        }        
    }
}
