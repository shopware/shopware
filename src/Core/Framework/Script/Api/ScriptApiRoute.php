<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Script\Api;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Script\Execution\ScriptContextValidator;
use Shopware\Core\Framework\Script\Execution\ScriptExecutor;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @internal
 */
#[Route(defaults: ['_routeScope' => ['api']])]
#[Package('core')]
class ScriptApiRoute
{
    public function __construct(
        private readonly ScriptContextValidator $scriptContextValidator,
        private readonly ScriptExecutor $executor,
        private readonly ScriptResponseEncoder $scriptResponseEncoder
    ) {
    }

    #[Route(path: '/api/script/{hook}', name: 'api.script_endpoint', methods: ['POST'], requirements: ['hook' => '.+'])]
    public function execute(string $hook, Request $request, Context $context): Response
    {
        $instance = new ApiHook($hook, $request->request->all(), $context);

        $this->scriptContextValidator->validate($instance, $context);

        $this->executor->execute($instance);

        return $this->scriptResponseEncoder->encodeByHook(
            $instance,
            $request->get('includes', [])
        );
    }
}
