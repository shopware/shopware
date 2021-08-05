<?php declare(strict_types=1);

namespace Shopware\Core\Content\Seo\SalesChannel;

use OpenApi\Annotations as OA;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Routing\Annotation\Entity;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\Framework\Routing\Annotation\Since;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepositoryInterface;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @RouteScope(scopes={"store-api"})
 */
class SeoUrlRoute extends AbstractSeoUrlRoute
{
    /**
     * @var SalesChannelRepositoryInterface
     */
    private $salesChannelRepository;

    public function __construct(SalesChannelRepositoryInterface $salesChannelRepository)
    {
        $this->salesChannelRepository = $salesChannelRepository;
    }

    public function getDecorated(): AbstractSeoUrlRoute
    {
        throw new DecorationPatternException(self::class);
    }

    /**
     * @Since("6.2.0.0")
     * @Entity("seo_url")
     * @OA\Post(
     *      path="/seo-url",
     *      summary="Fetch SEO routes",
     *      description="Perform a filtered search for seo urls.",
     *      operationId="readSeoUrl",
     *      tags={"Store API", "Sitemap & Routes"},
     *      @OA\Parameter(name="Api-Basic-Parameters"),
     *      @OA\Response(
     *          response="200",
     *          description="Entity search result containing seo urls.",
     *          @OA\JsonContent(
     *              type="object",
     *              allOf={
     *                  @OA\Schema(ref="#/components/schemas/EntitySearchResult"),
     *                  @OA\Schema(type="object",
     *                      @OA\Property(
     *                          type="array",
     *                          property="elements",
     *                          @OA\Items(ref="#/components/schemas/SeoUrl")
     *                      )
     *                  )
     *              }
     *          )
     *     ),
     *     @OA\Response(
     *          response="404",
     *          ref="#/components/responses/404"
     *     )
     * )
     *
     * @Route("/store-api/seo-url", name="store-api.seo.url", methods={"GET", "POST"})
     */
    public function load(Request $request, SalesChannelContext $context, Criteria $criteria): SeoUrlRouteResponse
    {
        return new SeoUrlRouteResponse($this->salesChannelRepository->search($criteria, $context));
    }
}
