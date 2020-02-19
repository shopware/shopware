<?php declare(strict_types=1);

namespace Shopware\Core\Content\Category\SalesChannel;

use OpenApi\Annotations as OA;
use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Content\Category\Exception\CategoryNotFoundException;
use Shopware\Core\Content\Cms\DataResolver\ResolverContext\EntityResolverContext;
use Shopware\Core\Content\Cms\Exception\PageNotFoundException;
use Shopware\Core\Content\Cms\SalesChannel\SalesChannelCmsPageLoader;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepositoryInterface;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @RouteScope(scopes={"sales-api"})
 */
class CategoryRoute
{
    /**
     * @var SalesChannelRepositoryInterface
     */
    private $categoryRepository;

    /**
     * @var SalesChannelCmsPageLoader
     */
    private $cmsPageLoader;

    /**
     * @var CategoryDefinition
     */
    private $definition;

    public function __construct(
        SalesChannelRepositoryInterface $categoryRepository,
        SalesChannelCmsPageLoader $cmsPageLoader,
        CategoryDefinition $definition
    ) {
        $this->categoryRepository = $categoryRepository;
        $this->cmsPageLoader = $cmsPageLoader;
        $this->definition = $definition;
    }

    /**
     * @OA\Post(
     *      path="/category/{categoryId}",
     *      description="Loads a category with the resolved cms page",
     *      operationId="readCategory",
     *      tags={"Sales Channel Api"},
     *      @OA\Parameter(
     *          parameter="categoryId",
     *          name="categoryId",
     *          in="query",
     *          description="Id of the category",
     *          @OA\Schema(type="string", format="uuid"),
     *      ),
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
     * @Route("/sales-api/v{version}/category/{categoryId}", name="sales-api.category.detail", methods={"GET","POST"})
     */
    public function load(string $categoryId, Request $request, SalesChannelContext $context): CategoryResponse
    {
        $category = $this->loadCategory($categoryId, $context);

        $pageId = $category->getCmsPageId();

        if (!$pageId) {
            return new CategoryResponse($category);
        }

        $resolverContext = new EntityResolverContext($context, $request, $this->definition, $category);

        $pages = $this->cmsPageLoader->load(
            $request,
            new Criteria([$pageId]),
            $context,
            $category->getTranslation('slotConfig'),
            $resolverContext
        );

        if (!$pages->has($pageId)) {
            throw new PageNotFoundException($pageId);
        }

        $category->setCmsPage($pages->get($pageId));

        return new CategoryResponse($category);
    }

    private function loadCategory(string $categoryId, SalesChannelContext $context): CategoryEntity
    {
        $criteria = new Criteria([$categoryId]);
        $criteria->addAssociation('media');

        $category = $this->categoryRepository
            ->search($criteria, $context)
            ->get($categoryId);

        if (!$category) {
            throw new CategoryNotFoundException($categoryId);
        }

        return $category;
    }
}
