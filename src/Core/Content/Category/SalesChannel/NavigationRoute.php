<?php declare(strict_types=1);

namespace Shopware\Core\Content\Category\SalesChannel;

use Doctrine\DBAL\Connection;
use Shopware\Core\Content\Category\CategoryCollection;
use Shopware\Core\Content\Category\CategoryException;
use Shopware\Core\Content\Category\Service\DefaultCategoryLevelLoaderInterface;
use Shopware\Core\Content\Category\Tree\CategoryTreePathResolver;
use Shopware\Core\Framework\Adapter\Cache\CacheTagCollector;
use Shopware\Core\Framework\DataAbstractionLayer\Doctrine\FetchModeHelper;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Routing\StoreApiRouteScope;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepository;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @phpstan-type CategoryMetaInformation array{id: string, level: string, path: string}
 */
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [StoreApiRouteScope::ID]])]
#[Package('discovery')]
class NavigationRoute extends AbstractNavigationRoute
{
    final public const ALL_TAG = 'navigation';

    /**
     * @internal
     *
     * @param SalesChannelRepository<CategoryCollection> $categoryRepository
     */
    public function __construct(
        private readonly Connection $connection,
        private readonly SalesChannelRepository $categoryRepository,
        private readonly CacheTagCollector $cacheTagCollector,
        private readonly CategoryTreePathResolver $categoryTreePathResolver,
        private readonly DefaultCategoryLevelLoaderInterface $categoryLevelLoader,
    ) {
    }

    /**
     * @deprecated - tag:v6.8.0 - will be removed, navigation route will only be tagged globally, use NavigationRoute::ALL_TAG instead
     */
    public static function buildName(string $id): string
    {
        Feature::triggerDeprecationOrThrow(
            'v6.8.0.0',
            Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.8.0.0', ' NavigationRoute::ALL_TAG')
        );

        return 'navigation-route-' . $id;
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

        $tags = [self::ALL_TAG];

        // Navigation route will be tagged & invalidated globally only in 6.8
        Feature::callSilentIfInactive(
            'v6.8.0.0',
            static function () use ($context, $activeId, &$tags): void {
                $tags[] = self::buildName($context->getSalesChannelId());
                $tags[] = self::buildName($activeId);
            }
        );

        $this->cacheTagCollector->addTag(...$tags);

        $root = $this->getMetaInfoById($rootId, $metaInfo);

        // Validate the provided category is part of the sales channel
        $this->validate($activeId, $active['path'], $context);

        $isChild = $this->isChildCategory($activeId, $active['path'], $rootId);

        $activePath = $active['path'];
        // If the provided activeId is not part of the rootId, a fallback to the rootId must be made here.
        // The passed activeId is therefore part of another navigation and must therefore not be loaded.
        // The availability validation has already been done in the `validate` function.
        if (!$isChild) {
            $activeId = $rootId;
            $activePath = $root['path'];
        }

        $categories = $this->categoryLevelLoader->loadLevels(
            $rootId,
            (int) $root['level'],
            $context,
            clone $criteria,
            $depth
        );

        $additionalPathsToLoad = $this->categoryTreePathResolver->getAdditionalPathsToLoad($activeId, $activePath, $rootId, $root['path'], $depth);

        if ($additionalPathsToLoad !== []) {
            $categories->merge($this->loadAdditionalPaths($context, clone $criteria, $additionalPathsToLoad));
        }

        return new NavigationRouteResponse($categories);
    }

    /**
     * @param list<string> $additionalPaths
     */
    private function loadAdditionalPaths(
        SalesChannelContext $context,
        Criteria $criteria,
        array $additionalPaths
    ): CategoryCollection {
        $criteria->addFilter(new EqualsAnyFilter('path', $additionalPaths));

        $criteria->addAssociation('media');

        $criteria->setLimit(null);
        $criteria->setTotalCountMode(Criteria::TOTAL_COUNT_MODE_NONE);

        $levels = $this->categoryRepository->search($criteria, $context)->getEntities();

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
            WHERE `id` = :activeId OR `id` = :rootId
        ', ['activeId' => Uuid::fromHexToBytes($activeId), 'rootId' => Uuid::fromHexToBytes($rootId)]);

        if (!$result) {
            throw CategoryException::categoryNotFound($activeId);
        }

        /** @var array<string, CategoryMetaInformation> $result */
        $result = FetchModeHelper::groupUnique($result);

        return $result;
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

        throw CategoryException::categoryNotFound($activeId);
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
