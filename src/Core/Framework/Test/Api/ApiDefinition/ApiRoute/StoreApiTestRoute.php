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
 * @internal (flag:FEATURE_NEXT_12345)
 *
 * @RouteScope(scopes={"store-api"})
 */
class StoreApiTestRoute extends AbstractStoreApiTestRoute
{
    public function getDecorated(): AbstractStoreApiTestRoute
    {
        throw new DecorationPatternException(self::class);
    }

    /**
     * @Since("6.3.4.0")
     * @Entity("test")
     * @OA\Post(
     *      path="/testinternal",
     *      summary="An internal Route",
     *      operationId="readInternalTest",
     *      tags={"Store API", "Test"},
     *      @OA\Parameter(name="Api-Basic-Parameters"),
     *      @OA\Response(
     *          response="200",
     *          description="Success"
     *     )
     * )
     * @LoginRequired()
     * @Route("/store-api/v{version}/testinternal", name="store-api.test.internal", methods={"GET", "POST"})
     */
    public function load(Request $request, SalesChannelContext $context, Criteria $criteria): Response
    {
        return new Response('', 200, '');
    }
}
