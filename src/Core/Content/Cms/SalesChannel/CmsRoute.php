<?php declare(strict_types=1);

namespace Shopware\Core\Content\Cms\SalesChannel;

use OpenApi\Annotations as OA;
use Shopware\Core\Content\Cms\Exception\PageNotFoundException;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\Framework\Routing\Annotation\Since;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @RouteScope(scopes={"store-api"})
 */
class CmsRoute extends AbstractCmsRoute
{
    /**
     * @var SalesChannelCmsPageLoaderInterface
     */
    private $cmsPageLoader;

    public function __construct(SalesChannelCmsPageLoaderInterface $cmsPageLoader)
    {
        $this->cmsPageLoader = $cmsPageLoader;
    }

    public function getDecorated(): AbstractCmsRoute
    {
        throw new DecorationPatternException(self::class);
    }

    /**
     * @Since("6.2.0.0")
     * @OA\Post(
     *      path="/cms/{id}",
     *      summary="Fetch and resolve a CMS page",
     *      description="Loads a content management page by its identifier and resolve the slot data. This could be media files, product listing and so on.

**Important notice**

The criteria passed with this route also affects the listing, if there is one within the cms page.",
     *      operationId="readCms",
     *      tags={"Store API", "Content"},
     *      @OA\Parameter(
     *          name="id",
     *          description="Identifier of the CMS page to be resolved",
     *          @OA\Schema(type="string", pattern="^[0-9a-f]{32}$"),
     *          in="path",
     *          required=true
     *      ),
     *      @OA\RequestBody(
     *          @OA\JsonContent(
     *              type="object",
     *              allOf={
     *                  @OA\Schema(
     *                      description="The product listing criteria only has an effect, if the cms page contains a product listing.",
     *                      ref="#/components/schemas/ProductListingCriteria"
     *                  ),
     *                  @OA\Schema(type="object",
     *                      @OA\Property(
     *                          property="slots",
     *                          description="Resolves only the given slot identifiers. The identifiers have to be seperated by a `|` character.",
     *                          type="string"
     *                      )
     *                  )
     *              }
     *          )
     *      ),
     *      @OA\Response(
     *          response="200",
     *          description="The loaded cms page",
     *          @OA\JsonContent(ref="#/components/schemas/CmsPage")
     *     ),
     *     @OA\Response(
     *          response="404",
     *          ref="#/components/responses/404"
     *     ),
     * )
     *
     * @Route("/store-api/cms/{id}", name="store-api.cms.detail", methods={"GET", "POST"})
     */
    public function load(string $id, Request $request, SalesChannelContext $context): CmsRouteResponse
    {
        $criteria = new Criteria([$id]);

        $slots = $request->get('slots');

        if (\is_string($slots)) {
            $slots = explode('|', $slots);
        }

        if (!empty($slots)) {
            $criteria
                ->getAssociation('sections.blocks')
                ->addFilter(new EqualsAnyFilter('slots.id', $slots));
        }

        $pages = $this->cmsPageLoader->load($request, $criteria, $context);

        if (!$pages->has($id)) {
            throw new PageNotFoundException($id);
        }

        return new CmsRouteResponse($pages->get($id));
    }
}
