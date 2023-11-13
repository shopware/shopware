<?php declare(strict_types=1);

namespace Shopware\Core\System\SystemConfig\Facade;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Script\Execution\Awareness\HookServiceFactory;
use Shopware\Core\Framework\Script\Execution\Awareness\SalesChannelContextAware;
use Shopware\Core\Framework\Script\Execution\Hook;
use Shopware\Core\Framework\Script\Execution\Script;
use Shopware\Core\System\SystemConfig\SystemConfigService;

/**
 * @internal
 */
#[Package('system-settings')]
class SystemConfigFacadeHookFactory extends HookServiceFactory
{
    /**
     * @internal
     */
    public function __construct(
        private readonly SystemConfigService $systemConfigService,
        private readonly Connection $connection
    ) {
    }

    public function getName(): string
    {
        return 'config';
    }

    public function factory(Hook $hook, Script $script): SystemConfigFacade
    {
        $salesChannelId = null;

        if ($hook instanceof SalesChannelContextAware) {
            $salesChannelId = $hook->getSalesChannelContext()->getSalesChannelId();
        }

        return new SystemConfigFacade($this->systemConfigService, $this->connection, $script->getScriptAppInformation(), $salesChannelId);
    }
}
