<?php declare(strict_types=1);

namespace Shopware\Core\Content\Seo\SalesChannel;

use OpenApi\Annotations as OA;
use Shopware\Core\Content\Seo\SeoUrl\SalesChannel\SalesChannelSeoUrlDefinition;
use Shopware\Core\Content\Seo\SeoUrl\SeoUrlCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\RequestCriteriaBuilder;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Routing\Annotation\Entity;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
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

    /**
     * @var RequestCriteriaBuilder
     */
    private $requestCriteriaBuilder;

    public function __construct(SalesChannelRepositoryInterface $salesChannelRepository, RequestCriteriaBuilder $requestCriteriaBuilder)
    {
        $this->salesChannelRepository = $salesChannelRepository;
        $this->requestCriteriaBuilder = $requestCriteriaBuilder;
    }

    public function getDecorated(): AbstractSeoUrlRoute
    {
        throw new DecorationPatternException(self::class);
    }

    /**
     * @Entity("seo_url")
     * @OA\Post(
     *      path="/seo-url",
     *      description="Loads seo urls",
     *      operationId="readSeoUrl",
     *      tags={"Store API", "Seo"},
     *      @OA\Parameter(name="Api-Basic-Parameters"),
     *      @OA\Response(
     *          response="200",
     *          description="Found seo urls",
     *          @OA\JsonContent(type="array", @OA\Items(ref="#/components/schemas/seo_url_flat"))
     *     ),
     *     @OA\Response(
     *          response="404",
     *          ref="#/components/responses/404"
     *     )
     * )
     *
     * @Route("/store-api/v{version}/seo-url", name="store-api.seo.url", methods={"GET", "POST"})
     */
    public function load(Request $request, SalesChannelContext $context, ?Criteria $criteria = null): SeoUrlRouteResponse
    {
        // @deprecated tag:v6.4.0 - Criteria will be required
        if (!$criteria) {
            $criteria = $this->requestCriteriaBuilder->handleRequest($request, new Criteria(), new SalesChannelSeoUrlDefinition(), $context->getContext());
        }

        /** @var SeoUrlCollection $seoUrlCollection */
        $seoUrlCollection = $this->salesChannelRepository->search($criteria, $context)->getEntities();

        return new SeoUrlRouteResponse($seoUrlCollection);
    }
}
