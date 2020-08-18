<?php declare(strict_types=1);

namespace Shopware\Core\Content\Category\SalesChannel;

use OpenApi\Annotations as OA;
use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Content\Category\Exception\CategoryNotFoundException;
use Shopware\Core\Content\Cms\DataResolver\ResolverContext\EntityResolverContext;
use Shopware\Core\Content\Cms\Exception\PageNotFoundException;
use Shopware\Core\Content\Cms\SalesChannel\SalesChannelCmsPageLoaderInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepositoryInterface;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @RouteScope(scopes={"store-api"})
 */
class CategoryRoute extends AbstractCategoryRoute
{
    public const HOME = 'home';

    /**
     * @var SalesChannelRepositoryInterface
     */
    private $categoryRepository;

    /**
     * @var SalesChannelCmsPageLoaderInterface
     */
    private $cmsPageLoader;

    /**
     * @var CategoryDefinition
     */
    private $definition;

    public function __construct(
        SalesChannelRepositoryInterface $categoryRepository,
        SalesChannelCmsPageLoaderInterface $cmsPageLoader,
        CategoryDefinition $definition
    ) {
        $this->categoryRepository = $categoryRepository;
        $this->cmsPageLoader = $cmsPageLoader;
        $this->definition = $definition;
    }

    public function getDecorated(): AbstractCategoryRoute
    {
        throw new DecorationPatternException(self::class);
    }

    /**
     * @OA\Post(
     *      path="/category/{categoryId}",
     *      description="Loads a category with the resolved cms page",
     *      operationId="readCategory",
     *      tags={"Store API", "Content"},
     *      @OA\Parameter(
     *          parameter="categoryId",
     *          name="categoryId",
     *          in="query",
     *          description="Id of the category",
     *          @OA\Schema(type="string", format="uuid"),
     *      ),
     *      @OA\Parameter(name="Api-Basic-Parameters"),
     *      @OA\Response(
     *          response="200",
     *          description="The loaded category with cms page",
     *          @OA\JsonContent(ref="#/components/schemas/category_flat")
     *     ),
     *     @OA\Response(
     *          response="404",
     *          ref="#/components/responses/404"
     *     ),
     * )
     *
     * @Route("/store-api/v{version}/category/{navigationId}", name="store-api.category.detail", methods={"GET","POST"})
     */
    public function load(string $navigationId, Request $request, SalesChannelContext $context): CategoryRouteResponse
    {
        if ($navigationId === self::HOME) {
            $navigationId = $context->getSalesChannel()->getNavigationCategoryId();
            $request->attributes->set('navigationId', $navigationId);
            $routeParams = $request->attributes->get('_route_params', []);
            $routeParams['navigationId'] = $navigationId;
            $request->attributes->set('_route_params', $routeParams);
        }

        $category = $this->loadCategory($navigationId, $context);

        $pageId = $category->getCmsPageId();

        if (!$pageId) {
            return new CategoryRouteResponse($category);
        }

        $resolverContext = new EntityResolverContext($context, $request, $this->definition, $category);

        $pages = $this->cmsPageLoader->load(
            $request,
            $this->createCriteria($pageId, $request),
            $context,
            $category->getTranslation('slotConfig'),
            $resolverContext
        );

        if (!$pages->has($pageId)) {
            throw new PageNotFoundException($pageId);
        }

        $category->setCmsPage($pages->get($pageId));

        return new CategoryRouteResponse($category);
    }

    private function loadCategory(string $categoryId, SalesChannelContext $context): CategoryEntity
    {
        $criteria = new Criteria([$categoryId]);
        $criteria->setTitle('category::data');

        $criteria->addAssociation('media');

        $category = $this->categoryRepository
            ->search($criteria, $context)
            ->get($categoryId);

        if (!$category) {
            throw new CategoryNotFoundException($categoryId);
        }

        return $category;
    }

    private function createCriteria(string $pageId, Request $request): Criteria
    {
        $criteria = new Criteria([$pageId]);
        $criteria->setTitle('category::cms-page');

        $slots = $request->get('slots');

        if (is_string($slots)) {
            $slots = explode('|', $slots);
        }

        if (!empty($slots) && is_array($slots)) {
            $criteria
                ->getAssociation('sections.blocks')
                ->addFilter(new EqualsAnyFilter('slots.id', $slots));
        }

        return $criteria;
    }
}
