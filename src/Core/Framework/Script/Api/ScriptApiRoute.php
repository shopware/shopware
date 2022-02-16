<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Script\Api;

use OpenApi\Annotations as OA;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Api\Controller\Exception\PermissionDeniedException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\Framework\Routing\Annotation\Since;
use Shopware\Core\Framework\Script\Execution\Script;
use Shopware\Core\Framework\Script\Execution\ScriptAppInformation;
use Shopware\Core\Framework\Script\Execution\ScriptExecutor;
use Shopware\Core\Framework\Script\Execution\ScriptLoader;
use Shopware\Core\System\SalesChannel\Api\ResponseFields;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @internal
 * @RouteScope(scopes={"api"})
 */
class ScriptApiRoute
{
    private ScriptExecutor $executor;

    private ScriptLoader $loader;

    private ScriptResponseEncoder $scriptResponseEncoder;

    public function __construct(ScriptExecutor $executor, ScriptLoader $loader, ScriptResponseEncoder $scriptResponseEncoder)
    {
        $this->executor = $executor;
        $this->loader = $loader;
        $this->scriptResponseEncoder = $scriptResponseEncoder;
    }

    /**
     * @Since("6.4.9.0")
     * @OA\Post(
     *      path="/script/{hook}",
     *      summary="Access point for different api logics which are provided by apps over script hooks",
     *      operationId="scriptApiRoute",
     *      tags={"API","Script", "App"},
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
     * @Route("/api/script/{hook}", name="api.script_endpoint", methods={"POST"}, requirements={"hook"=".+"})
     */
    public function execute(string $hook, Request $request, Context $context): Response
    {
        //  blog/update =>  blog-update
        $hook = \str_replace('/', '-', $hook);

        $instance = new ApiHook($hook, $request->request->all(), $context);

        $this->validate($instance, $context);

        // hook: api-{hook}
        $this->executor->execute($instance);

        $fields = new ResponseFields(
            $request->get('includes', [])
        );

        return $this->scriptResponseEncoder->encodeToSymfonyResponse(
            $instance->getScriptResponse(),
            $fields,
            \str_replace('-', '_', 'api_' . $hook . '_response')
        );
    }

    private function validate(ApiHook $hook, Context $context): void
    {
        $scripts = $this->loader->get($hook->getName());

        /** @var Script $script */
        foreach ($scripts as $script) {
            // todo@dr after implementing UI in admin, we can allow "private scripts"
            if (!$script->isAppScript()) {
                throw new PermissionDeniedException();
            }

            /** @var ScriptAppInformation $appInfo */
            $appInfo = $script->getScriptAppInformation();

            $source = $context->getSource();
            if ($source instanceof AdminApiSource && $source->getIntegrationId() === $appInfo->getIntegrationId()) {
                // allow access to app endpoints from the integration of the same app
                continue;
            }

            if ($context->isAllowed('app.all')) {
                continue;
            }

//            $name = $script->getAppName() ?? 'shop-owner-scripts';
            if ($context->isAllowed('app.' . $appInfo->getAppName())) {
                continue;
            }

            throw new PermissionDeniedException();
        }
    }
}
