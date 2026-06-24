<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Script\Api;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Script\Execution\Awareness\HookServiceFactory;
use Shopware\Core\Framework\Script\Execution\Awareness\SalesChannelContextAware;
use Shopware\Core\Framework\Script\Execution\Hook;
use Shopware\Core\Framework\Script\Execution\Script;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Controller\ScriptController;
use Symfony\Component\Routing\RouterInterface;

/**
 * @internal
 */
#[Package('framework')]
class ScriptResponseFactoryFacadeHookFactory extends HookServiceFactory
{
    public function __construct(
        private readonly RouterInterface $router,
        /**
         * @phpstan-ignore phpat.restrictNamespacesInCore (Storefront dependency is nullable. Don't do that! Will be removed with v6.8.0 when render() is removed from the core response facade)
         */
        private readonly ?ScriptController $scriptController = null,
    ) {
    }

    public function factory(Hook $hook, Script $script): ScriptResponseFactoryFacade
    {
        return new ScriptResponseFactoryFacade(
            $this->router,
            $this->scriptController,
            // @deprecated tag:v6.8.0 - only needed for the deprecated render() BC path.
            $this->resolveSalesChannelContext($hook)
        );
    }

    public function getName(): string
    {
        return 'response';
    }

    protected function resolveSalesChannelContext(Hook $hook): ?SalesChannelContext
    {
        if ($hook instanceof SalesChannelContextAware) {
            return $hook->getSalesChannelContext();
        }

        return null;
    }
}
