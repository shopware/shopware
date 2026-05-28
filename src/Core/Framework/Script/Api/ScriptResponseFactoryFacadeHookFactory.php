<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Script\Api;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Script\Execution\Awareness\HookServiceFactory;
use Shopware\Core\Framework\Script\Execution\Awareness\SalesChannelContextAware;
use Shopware\Core\Framework\Script\Execution\Hook;
use Shopware\Core\Framework\Script\Execution\Script;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[Package('framework')]
class ScriptResponseFactoryFacadeHookFactory extends HookServiceFactory
{
    public function __construct(
        private readonly RouterInterface $router,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public function factory(Hook $hook, Script $script): ScriptResponseFactoryFacade
    {
        $salesChannelContext = null;
        if ($hook instanceof SalesChannelContextAware) {
            $salesChannelContext = $hook->getSalesChannelContext();
        }

        return new ScriptResponseFactoryFacade(
            $this->router,
            $this->eventDispatcher,
            $salesChannelContext
        );
    }

    public function getName(): string
    {
        return 'response';
    }
}
