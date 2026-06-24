<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Script\Api;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Script\Execution\Awareness\HookServiceFactory;
use Shopware\Core\Framework\Script\Execution\Awareness\SalesChannelContextAware;
use Shopware\Core\Framework\Script\Execution\Hook;
use Shopware\Core\Framework\Script\Execution\Script;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\Routing\RouterInterface;

/**
 * @internal
 */
#[Package('framework')]
class ScriptResponseFactoryFacadeHookFactory extends HookServiceFactory
{
    public function __construct(
        private readonly RouterInterface $router,
    ) {
    }

    public function factory(Hook $hook, Script $script): ScriptResponseFactoryFacade
    {
        return new ScriptResponseFactoryFacade(
            $this->router,
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
