<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Script\Api;

use Shopware\Core\Framework\Adapter\Request\RequestParamHelper;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Api\Controller\Exception\PermissionDeniedException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Routing\ApiRouteScope;
use Shopware\Core\Framework\Script\Execution\ScriptAppInformation;
use Shopware\Core\Framework\Script\Execution\ScriptExecutor;
use Shopware\Core\Framework\Script\Execution\ScriptLoader;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SalesChannel\Api\ResponseFields;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @internal
 */
#[Package('framework')]
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [ApiRouteScope::ID]])]
class ScriptApiRoute
{
    public function __construct(
        private readonly ScriptExecutor $executor,
        private readonly ScriptLoader $loader,
        private readonly ScriptResponseEncoder $scriptResponseEncoder
    ) {
    }

    #[Route(path: '/api/script/{hook}', name: 'api.script_endpoint', methods: ['POST', 'GET'], requirements: ['hook' => '.+'])]
    public function execute(string $hook, Request $request, Context $context): Response
    {
        //  blog/update =>  blog-update
        $hook = \str_replace('/', '-', $hook);

        $instance = new ApiHook($hook, $request->request->all(), $context);

        $this->validate($instance, $context);

        // hook: api-{hook}
        $this->executor->execute($instance);

        $fields = new ResponseFields(
            RequestParamHelper::get($request, 'includes', []),
            RequestParamHelper::get($request, 'excludes', []),
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

        $source = $context->getSource();
        $isAllowedForAllApps = $context->isAllowed('app.all');

        foreach ($scripts as $script) {
            $appInfo = $script->getScriptAppInformation();

            if (!$appInfo instanceof ScriptAppInformation) {
                throw new PermissionDeniedException();
            }

            if ($source instanceof AdminApiSource && $source->getIntegrationId() === $appInfo->getIntegrationId()) {
                // allow access to app endpoints from the integration of the same app
                continue;
            }

            if ($isAllowedForAllApps) {
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
