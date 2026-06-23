<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Script\Api;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Script\Api\ScriptResponseFactoryFacade;
use Shopware\Core\Framework\Script\Api\ScriptResponseFactoryFacadeHookFactory;
use Shopware\Core\Framework\Script\Execution\Hook;
use Shopware\Core\Framework\Script\Execution\Script;
use Shopware\Storefront\Controller\ScriptController;
use Symfony\Component\Routing\RouterInterface;

/**
 * Decorates the core `response` hook-service factory so that, when the Storefront bundle is installed,
 * scripts receive a {@see StorefrontScriptResponseFactoryFacade} that can render Twig views.
 *
 * @internal
 */
#[Package('framework')]
class StorefrontScriptResponseFactoryFacadeHookFactory extends ScriptResponseFactoryFacadeHookFactory
{
    public function __construct(
        private readonly RouterInterface $router,
        private readonly ScriptController $scriptController,
    ) {
        parent::__construct($router);
    }

    public function factory(Hook $hook, Script $script): ScriptResponseFactoryFacade
    {
        return new StorefrontScriptResponseFactoryFacade(
            $this->router,
            $this->scriptController,
            $this->resolveSalesChannelContext($hook)
        );
    }
}
