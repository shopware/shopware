<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Api\ApiDefinition\Generator\OpenApi\_fixtures;

use OpenApi\Annotations as OA;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Routing\Annotation\Entity;
use Shopware\Core\Framework\Routing\Annotation\Since;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\BrowserKit\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @internal
 */
#[Route(defaults: ['_routeScope' => ['api']])]
class StoreApiTestOtherRoute
{
    /**
     * @Since("6.3.4.0")
     * @Entity("test")
     * @OA\Post(
     *      path="/test",
     *      summary="A test route",
     *      operationId="readOtherInternalTest",
     *      tags={"Admin API", "Test"},
     *      @OA\Parameter(name="Api-Basic-Parameters"),
     *      @OA\Response(
     *          response="200",
     *          description="Success"
     *     )
     * )
     */
    #[Route(path: '/api/test', name: 'api.test', methods: ['GET'], defaults: ['_loginRequired' => true])]
    public function load(Request $request, SalesChannelContext $context, Criteria $criteria): Response
    {
        return new Response();
    }
}
