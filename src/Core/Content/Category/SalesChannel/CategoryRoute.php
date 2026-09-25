<?php declare(strict_types=1);

namespace Shopware\Core\Content\Category\SalesChannel;

use Shopware\Core\Content\Breadcrumb\Struct\Breadcrumb;
use Shopware\Core\Content\Category\CategoryCollection;
use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Content\Category\CategoryException;
use Shopware\Core\Content\Category\Service\CategoryBreadcrumbBuilder;
use Shopware\Core\Content\Cms\DataResolver\ResolverContext\EntityResolverContext;
use Shopware\Core\Content\Cms\SalesChannel\SalesChannelCmsPageLoaderInterface;
use Shopware\Core\Content\Cms\Service\EntityCmsSlotConfigInheritanceBuilder;
use Shopware\Core\Framework\Adapter\Cache\CacheTagCollector;
use Shopware\Core\Framework\Adapter\Request\RequestParamHelper;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Routing\StoreApiRouteScope;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepository;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Package('discovery')]
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [StoreApiRouteScope::ID]])]
class CategoryRoute extends AbstractCategoryRoute
{
    final public const HOME = 'home';

    /**
     * Opt out of loading the breadcrumb. Clients pass it as the `skipBreadcrumb` query or body parameter; internal
     * callers set it as a request attribute, which takes precedence and cannot be provided by a client.
     */
    final public const SKIP_BREADCRUMB = 'skipBreadcrumb';

    /**
     * @internal
     *
     * @param SalesChannelRepository<CategoryCollection> $categoryRepository
     */
    public function __construct(
        private readonly SalesChannelRepository $categoryRepository,
        private readonly SalesChannelCmsPageLoaderInterface $cmsPageLoader,
        private readonly EntityCmsSlotConfigInheritanceBuilder $cmsSlotConfigInheritanceBuilder,
        private readonly CategoryDefinition $categoryDefinition,
        private readonly CacheTagCollector $cacheTagCollector,
        private readonly CategoryBreadcrumbBuilder $breadcrumbBuilder,
    ) {
    }

    public static function buildName(string $id): string
    {
        return 'category-route-' . $id;
    }

    public function getDecorated(): AbstractCategoryRoute
    {
        throw new DecorationPatternException(self::class);
    }

    #[Route(
        path: '/store-api/category/{navigationId}',
        name: 'store-api.category.detail',
        methods: [Request::METHOD_GET, Request::METHOD_POST],
        defaults: [PlatformRequest::ATTRIBUTE_HTTP_CACHE => true],
    )]
    public function load(string $navigationId, Request $request, SalesChannelContext $context): CategoryRouteResponse
    {
        if ($navigationId === self::HOME) {
            $navigationId = $context->getSalesChannel()->getNavigationCategoryId();
            $request->attributes->set('navigationId', $navigationId);

            $routeParams = $request->attributes->get('_route_params', []);
            $routeParams['navigationId'] = $navigationId;
            $request->attributes->set('_route_params', $routeParams);
        }

        $this->cacheTagCollector->addTag(self::buildName($navigationId));

        $category = $this->loadCategory($navigationId, $context);

        $categoryHasContentlessPageType = \in_array($category->getType(), [CategoryDefinition::TYPE_FOLDER, CategoryDefinition::TYPE_LINK], true);
        if ($categoryHasContentlessPageType && $context->getSalesChannel()->getNavigationCategoryId() !== $navigationId) {
            if ($category->getType() === CategoryDefinition::TYPE_LINK) {
                $this->addBreadcrumb($request, $category, $context);

                return new CategoryRouteResponse($category);
            }

            // a folder category always results in a 404, so the breadcrumb would be built and thrown away
            throw CategoryException::categoryNotFound($navigationId);
        }

        $this->addBreadcrumb($request, $category, $context);

        $pageId = $category->getCmsPageId();
        $salesChannel = $context->getSalesChannel();

        if ($category->getId() === $salesChannel->getNavigationCategoryId() && $salesChannel->getHomeCmsPageId()) {
            $pageId = $salesChannel->getHomeCmsPageId();
            $slotConfig = $salesChannel->getTranslation('homeSlotConfig');
        } else {
            $slotConfig = $this->buildMergedCmsSlotConfig($category, $context);
        }

        if (!$pageId) {
            return new CategoryRouteResponse($category);
        }

        $resolverContext = new EntityResolverContext($context, $request, $this->categoryDefinition, $category);

        $pages = $this->cmsPageLoader->load(
            $request,
            $this->createCriteria($pageId, $request),
            $context,
            $slotConfig,
            $resolverContext,
        );

        $cmsPage = $pages->getEntities()->first();
        if ($cmsPage === null) {
            throw CategoryException::pageNotFound($pageId);
        }

        $category->setCmsPage($cmsPage);
        $category->setCmsPageId($pageId);

        return new CategoryRouteResponse($category);
    }

    private function loadCategory(string $categoryId, SalesChannelContext $context): SalesChannelCategoryEntity
    {
        $criteria = new Criteria([$categoryId]);
        $criteria->setTitle('category::data');

        $criteria->addAssociation('media');
        $criteria->addAssociation('translations');

        $category = $this->categoryRepository->search($criteria, $context)->getEntities()->get($categoryId);
        if (!$category instanceof SalesChannelCategoryEntity) {
            throw CategoryException::categoryNotFound($categoryId);
        }

        return $category;
    }

    private function addBreadcrumb(Request $request, SalesChannelCategoryEntity $category, SalesChannelContext $context): void
    {
        if ($this->skipBreadcrumb($request)) {
            return;
        }

        $breadcrumb = $this->breadcrumbBuilder->getCategoryBreadcrumbUrls(
            $category,
            $context->getContext(),
            $context->getSalesChannel()
        );

        $category->setSeoBreadcrumb($breadcrumb);

        // the breadcrumb reflects every category on the path, so all of them have to invalidate the cached response
        $tags = $breadcrumb->map(static fn (Breadcrumb $item) => self::buildName($item->categoryId));

        if ($tags !== []) {
            $this->cacheTagCollector->addTag(...$tags);
        }
    }

    /**
     * Internal callers opt out via a request attribute, which a client cannot set. Only when no attribute is present
     * the client provided parameter is honoured, and it is read leniently so a malformed value cannot turn a
     * storefront page into a 400.
     */
    private function skipBreadcrumb(Request $request): bool
    {
        if ($request->attributes->has(self::SKIP_BREADCRUMB)) {
            return $request->attributes->getBoolean(self::SKIP_BREADCRUMB);
        }

        return filter_var(RequestParamHelper::get($request, self::SKIP_BREADCRUMB, false), \FILTER_VALIDATE_BOOL);
    }

    private function createCriteria(string $pageId, Request $request): Criteria
    {
        $criteria = new Criteria([$pageId]);
        $criteria->setTitle('category::cms-page');

        $slots = RequestParamHelper::get($request, 'slots');

        if (\is_string($slots)) {
            $slots = explode('|', $slots);
        }

        if (\is_array($slots) && $slots !== []) {
            $criteria
                ->getAssociation('sections.blocks')
                ->addFilter(new EqualsAnyFilter('slots.id', $slots));
        }

        return $criteria;
    }

    /**
     * @return array<string, array<string, mixed>>|null
     */
    private function buildMergedCmsSlotConfig(CategoryEntity $category, SalesChannelContext $context): ?array
    {
        return $this->cmsSlotConfigInheritanceBuilder->build(
            $category->getTranslations(),
            $context,
        );
    }
}
