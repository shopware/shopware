<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Api\ApiDefinition\ApiRoute;

use OpenApi\Annotations as OA;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Routing\Annotation\Entity;
use Shopware\Core\Framework\Routing\Annotation\LoginRequired;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\Framework\Routing\Annotation\Since;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\BrowserKit\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @RouteScope(scopes={"store-api"})
 */
class StoreApiTestOtherRoute extends AbstractStoreApiTestRoute
{
    public function getDecorated(): AbstractStoreApiTestRoute
    {
        throw new DecorationPatternException(self::class);
    }

    /**
     * @Since("6.3.4.0")
     * @Entity("test")
     * @OA\Post(
     *      path="/testinternalother",
     *      summary="An other internal Route",
     *      operationId="readOtherInternalTest",
     *      tags={"Store API", "Test"},
     *      @OA\Parameter(name="Api-Basic-Parameters"),
     *      @OA\Response(
     *          response="200",
     *          description="Success"
     *     )
     * )
     *
     * @internal (flag:FEATURE_NEXT_12345)
     * @LoginRequired()
     * @Route("/store-api/v{version}/testinternalother", name="store-api.test.internal.other", methods={"GET"})
     */
    public function load(Request $request, SalesChannelContext $context, Criteria $criteria): Response
    {
        return new Response('', 200, '');
    }

    /**
     * @Since("6.3.4.0")
     * @Entity("test")
     * @OA\Post(
     *      path="/testnotinternalother",
     *      summary="An other not internal Route",
     *      operationId="readOtherNotInternalTest",
     *      tags={"Store API", "Test"},
     *      @OA\Parameter(name="Api-Basic-Parameters"),
     *      @OA\Response(
     *          response="200",
     *          description="Success"
     *     )
     * )
     * @LoginRequired()
     * @Route("/store-api/v{version}/testnotinternalother", name="store-api.test.not.internal.other", methods={"POST"})
     */
    public function loadPost(Request $request, SalesChannelContext $context, Criteria $criteria): Response
    {
        return new Response('', 200, '');
    }

    /**
     * @Since("6.3.4.0")
     * @Entity("test")
     * @OA\Post(
     *      path="/testinternalnoflagother",
     *      summary="An other internal no flag Route",
     *      operationId="readOtherInternalNoFlagTest",
     *      tags={"Store API", "Test"},
     *      @OA\Parameter(name="Api-Basic-Parameters"),
     *      @OA\Response(
     *          response="200",
     *          description="Success"
     *     )
     * )
     *
     * @internal
     * @LoginRequired()
     * @Route("/store-api/v{version}/testinternalothernoflag", name="store-api.test.internal.other.no.flag", methods={"GET"})
     */
    public function loadNoFlag(Request $request, SalesChannelContext $context, Criteria $criteria): Response
    {
        return new Response('', 200, '');
    }
}
