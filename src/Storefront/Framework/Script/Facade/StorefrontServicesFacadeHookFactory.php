<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Script\Facade;

use Shopware\Core\Framework\Script\Exception\HookInjectionException;
use Shopware\Core\Framework\Script\Execution\Awareness\HookServiceFactory;
use Shopware\Core\Framework\Script\Execution\Awareness\SalesChannelContextAware;
use Shopware\Core\Framework\Script\Execution\Hook;
use Shopware\Core\Framework\Script\Execution\Script;
use Shopware\Storefront\Controller\ScriptController;

class StorefrontServicesFacadeHookFactory extends HookServiceFactory
{
    private ScriptController $scriptController;

    public function __construct(ScriptController $scriptController)
    {
        $this->scriptController = $scriptController;
    }

    public function factory(Hook $hook, Script $script): StorefrontServicesFacade
    {
        if (!$hook instanceof SalesChannelContextAware) {
            throw new HookInjectionException($hook, self::class, SalesChannelContextAware::class);
        }

        return new StorefrontServicesFacade(
            $this->scriptController,
            $hook->getSalesChannelContext()
        );
    }

    public function getName(): string
    {
        return 'storefront';
    }
}
