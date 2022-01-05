<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Script\Api;

use OpenApi\Annotations as OA;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\Framework\Routing\Annotation\Since;
use Shopware\Core\Framework\Script\Execution\ScriptExecutor;
use Shopware\Core\Framework\Struct\ArrayStruct;
use Shopware\Core\System\SalesChannel\GenericStoreApiResponse;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @internal
 * @RouteScope(scopes={"store-api"})
 */
class ScriptStoreApiRoute
{
    private ScriptExecutor $executor;

    public function __construct(ScriptExecutor $executor)
    {
        $this->executor = $executor;
    }

    /**
     * @Since("6.4.9.0")
     * @OA\Post(
     *      path="/store-api/script/{hook}",
     *      summary="Access point for different api logics which are provided by apps over script hooks",
     *      operationId="scriptStoreApiRoute",
     *      tags={"API","Script","Store API","App"},
     *      @OA\Parameter(
     *          name="hook",
     *          description="Dynamic hook which used to build the hook name",
     *          @OA\Schema(type="string"),
     *          in="path",
     *          required=true
     *      ),
     *      @OA\Response(
     *          response="200",
     *          description="Returns different structures of results based on the called script.",
     *     )
     * )
     * @Route("/store-api/script/{hook}", name="store-api.script_endpoint", methods={"POST"})
     */
    public function load(string $hook, Request $request, SalesChannelContext $context): GenericStoreApiResponse
    {
        //  blog/update =>  blog-update
        $hook = \str_replace('/', '-', $hook);

        $response = new ScriptResponse();

        // hook: store-api-{hook}
        $this->executor->execute(
            new StoreApiHook($hook, $request->request->all(), $response, $context)
        );

        return new GenericStoreApiResponse(
            $response->code,
            new ArrayStruct($response->body->all(), 'store_api_' . $hook . '_response')
        );
    }
}
