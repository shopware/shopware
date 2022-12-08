<?php declare(strict_types=1);

namespace Shopware\Core\System\SystemConfig\Facade;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Script\Execution\Awareness\HookServiceFactory;
use Shopware\Core\Framework\Script\Execution\Awareness\SalesChannelContextAware;
use Shopware\Core\Framework\Script\Execution\Hook;
use Shopware\Core\Framework\Script\Execution\Script;
use Shopware\Core\System\SystemConfig\SystemConfigService;

/**
 * @deprecated tag:v6.5.0 - reason:becomes-internal - will be internal
 *
 * @package system-settings
 */
class SystemConfigFacadeHookFactory extends HookServiceFactory
{
    private SystemConfigService $systemConfigService;

    private Connection $connection;

    /**
     * @internal
     */
    public function __construct(SystemConfigService $systemConfigService, Connection $connection)
    {
        $this->systemConfigService = $systemConfigService;
        $this->connection = $connection;
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
