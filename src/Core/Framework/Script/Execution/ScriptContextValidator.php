<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Script\Execution;

use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Api\Controller\Exception\PermissionDeniedException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('core')]
class ScriptContextValidator
{
    public function __construct(
        private readonly ScriptLoader $loader
    ) {
    }

    public function validate(Hook $hook, Context $context): void
    {
        $scripts = $this->loader->get($hook->getName());

        foreach ($scripts as $script) {
            // todo@dr after implementing UI in admin, we can allow "private scripts"
            if (!$script->isAppScript()) {
                throw new PermissionDeniedException();
            }

            $appInfo = $script->getScriptAppInformation();

            $source = $context->getSource();
            if ($source instanceof AdminApiSource && $source->getIntegrationId() === $appInfo->getIntegrationId()) {
                // allow access to app endpoints from the integration of the same app
                continue;
            }

            if ($context->isAllowed('app.all')) {
                continue;
            }

            if ($context->isAllowed('app.' . $appInfo->getAppName())) {
                continue;
            }

            throw new PermissionDeniedException();
        }
    }
}
