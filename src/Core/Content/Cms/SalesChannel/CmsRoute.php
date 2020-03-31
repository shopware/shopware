<?php declare(strict_types=1);

namespace Shopware\Core\Content\Cms\SalesChannel;

use OpenApi\Annotations as OA;
use Shopware\Core\Content\Cms\Exception\PageNotFoundException;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
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
     * @OA\Post(
     *      path="/cms/{id}",
     *      description="Resolves a cms page",
     *      operationId="readCms",
     *      tags={"Store API", "Content"},
     *      @OA\Parameter(name="Api-Basic-Parameters"),
     *      @OA\Response(
     *          response="200",
     *          description="The loaded cms page",
     *          @OA\JsonContent(ref="#/components/schemas/cms_page_flat")
     *     ),
     *     @OA\Response(
     *          response="404",
     *          ref="#/components/responses/404"
     *     ),
     * )
     *
     * @Route("/store-api/v{version}/cms/{id}", name="store-api.cms.detail", methods={"GET", "POST"})
     */
    public function load(string $id, Request $request, SalesChannelContext $context): CmsRouteResponse
    {
        $criteria = new Criteria([$id]);

        $slots = $request->get('slots');

        if (is_string($slots)) {
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
