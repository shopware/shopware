<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\Controller;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Script\Api\AdminHook;
use Shopware\Core\Framework\Script\Api\ScriptResponseEncoder;
use Shopware\Core\Framework\Script\Execution\ScriptContextValidator;
use Shopware\Core\Framework\Script\Execution\ScriptExecutor;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @internal
 */
#[Route(defaults: ['_routeScope' => ['administration', 'api']])]
#[Package('core')]
class ScriptController
{
    public const ROUTE = 'administration.script';

    public const PATH = '/api/app';

    public function __construct(
        private readonly ScriptContextValidator $scriptContextValidator,
        private readonly ScriptExecutor $scriptExecutor,
        private readonly ScriptResponseEncoder $scriptResponseEncoder
    ) {
    }

    #[Route(path: self::PATH . '/{hook}', name: self::ROUTE, requirements: ['hook' => '.+'], defaults: ['admin_script' => true], methods: ['GET', 'POST'])]
    public function execute(string $hook, Request $request, Context $context): Response
    {
        $hook = new AdminHook(
            $hook,
            $request->request->all(),
            $request->query->all(),
            $context
        );

        $this->scriptContextValidator->validate($hook, $context);

        $this->scriptExecutor->execute($hook);

        return $this->scriptResponseEncoder->encodeByHook($hook, $request->get('includes', []));
    }
}
