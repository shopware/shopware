<?php declare(strict_types=1);

namespace Shopware\Core\System\SalesChannel\SalesChannel;

use OpenApi\Annotations as OA;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @RouteScope(scopes={"store-api"})
 */
class ContextLoadRoute implements ContextLoadRouteInterface
{
    /**
     * @OA\Get(
     *      path="/context",
     *      description="Read the context",
     *      operationId="readContext",
     *      tags={"Store API","Context"},
     *      @OA\Response(
     *          response="200",
     *          description="Context",
     *          @OA\JsonContent(ref="#/definitions/SalesChannelContext")
     *     )
     * )
     * @Route("/store-api/v{version}/context", name="store-api.context", methods={"GET"})
     */
    public function load(SalesChannelContext $context): ContextLoadRouteResponse
    {
        return new ContextLoadRouteResponse($context);
    }
}
