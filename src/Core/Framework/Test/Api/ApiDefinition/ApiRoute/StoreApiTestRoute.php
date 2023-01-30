<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Api\ApiDefinition\ApiRoute;

use OpenApi\Annotations as OA;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @internal
 */
#[Route(defaults: ['_routeScope' => ['store-api']])]
class StoreApiTestRoute extends AbstractStoreApiTestRoute
{
    public function getDecorated(): AbstractStoreApiTestRoute
    {
        throw new DecorationPatternException(self::class);
    }

    /**
     * @OA\Post(
     *      path="/testinternal",
     *      summary="An internal Route",
     *      operationId="readInternalTest",
     *      tags={"Store API", "Test"},
     *
     *      @OA\Parameter(name="Api-Basic-Parameters"),
     *
     *      @OA\Response(
     *          response="200",
     *          description="Success"
     *     )
     * )
     */
    #[Route(path: '/store-api/v{version}/testinternal', name: 'store-api.test.internal', methods: ['GET', 'POST'], defaults: ['_loginRequired' => true, '_entity' => 'test'])]
    public function load(Request $request, SalesChannelContext $context, Criteria $criteria): Response
    {
        return new Response();
    }
}
