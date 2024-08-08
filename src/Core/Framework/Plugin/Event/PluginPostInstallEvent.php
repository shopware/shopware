<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Plugin\Event;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Context\InstallContext;
use Shopware\Core\Framework\Plugin\PluginEntity;
use Shopware\Core\Framework\Telemetry\Metrics\Attribute\Counter;

#[Package('core')]
#[Counter(name: 'plugin.install', value: 1, description: 'Number of plugin installations')]
class PluginPostInstallEvent extends PluginLifecycleEvent
{
    public function __construct(
        PluginEntity $plugin,
        private readonly InstallContext $context
    ) {
        parent::__construct($plugin);
    }

    public function getContext(): InstallContext
    {
        return $this->context;
    }
}
