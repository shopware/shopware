<?php declare(strict_types=1);

namespace Shopware\Core\Content\Category\SalesChannel;

use OpenApi\Annotations as OA;
use Shopware\Core\Content\Category\CategoryCollection;
use Shopware\Core\Content\Category\Exception\CategoryNotFoundException;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Routing\Annotation\Entity;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\Framework\Routing\Annotation\Since;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @RouteScope(scopes={"store-api"})
 */
class TreeBuildingNavigationRoute extends AbstractNavigationRoute
{
    private AbstractNavigationRoute $decorated;

    public function __construct(AbstractNavigationRoute $decorated)
    {
        $this->decorated = $decorated;
    }

    public function getDecorated(): AbstractNavigationRoute
    {
        return $this->decorated;
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
    public function load(string $activeId, string $rootId, Request $request, SalesChannelContext $context, Criteria $criteria): NavigationRouteResponse
    {
        $activeId = $this->resolveAliasId($activeId, $context->getSalesChannel());

        $rootId = $this->resolveAliasId($rootId, $context->getSalesChannel());

        if ($activeId === null) {
            throw new CategoryNotFoundException($request->get('activeId'));
        }

        if ($rootId === null) {
            throw new CategoryNotFoundException($request->get('rootId'));
        }

        $response = $this->getDecorated()->load($activeId, $rootId, $request, $context, $criteria);

        $buildTree = $request->query->getBoolean('buildTree', $request->request->getBoolean('buildTree', true));

        if (!$buildTree) {
            return $response;
        }

        $categories = $this->buildTree($rootId, $response->getCategories()->getElements());

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
