<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Script\Api;

use Shopware\Core\Framework\Script\Execution\Awareness\HookServiceFactory;
use Shopware\Core\Framework\Script\Execution\Awareness\SalesChannelContextAware;
use Shopware\Core\Framework\Script\Execution\Hook;
use Shopware\Core\Framework\Script\Execution\Script;
use Shopware\Storefront\Controller\ScriptController;
use Symfony\Component\Routing\RouterInterface;

/**
 * @internal
 */
class ScriptResponseFactoryFacadeHookFactory extends HookServiceFactory
{
    private RouterInterface $router;

    private ?ScriptController $scriptController;

    public function __construct(RouterInterface $router, ?ScriptController $scriptController)
    {
        $this->router = $router;
        $this->scriptController = $scriptController;
    }

    public function factory(Hook $hook, Script $script): ScriptResponseFactoryFacade
    {
        $salesChannelContext = null;
        if ($hook instanceof SalesChannelContextAware) {
            $salesChannelContext = $hook->getSalesChannelContext();
        }

        return new ScriptResponseFactoryFacade(
            $this->router,
            $this->scriptController,
            $salesChannelContext
        );
    }

    public function getName(): string
    {
        return 'response';
    }
}
