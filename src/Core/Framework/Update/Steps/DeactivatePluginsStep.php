<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Update\Steps;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Plugin\PluginLifecycleService;
use Shopware\Core\Framework\Update\Exception\UpdateFailedException;
use Shopware\Core\Framework\Update\Services\PluginCompatibility;
use Shopware\Core\Framework\Update\Struct\Version;
use Shopware\Core\System\SystemConfig\SystemConfigService;

class DeactivatePluginsStep
{
    public const UPDATE_DEACTIVATED_PLUGINS = 'core.update.deactivatedPlugins';

    /**
     * @var string
     */
    private $deactivationFilter;

    /**
     * @var PluginCompatibility
     */
    private $pluginCompatibility;

    /**
     * @var Version
     */
    private $toVersion;

    /**
     * @var Context
     */
    private $context;

    /**
     * @var PluginLifecycleService
     */
    private $pluginLifecycleService;

    /**
     * @var SystemConfigService
     */
    private $systemConfigService;

    public function __construct(
        Version $toVersion,
        string $deactivationFilter,
        PluginCompatibility $pluginCompatibility,
        PluginLifecycleService $pluginLifecycleService,
        SystemConfigService $systemConfigService,
        Context $context
    ) {
        $this->deactivationFilter = $deactivationFilter;
        $this->pluginCompatibility = $pluginCompatibility;
        $this->toVersion = $toVersion;
        $this->context = $context;
        $this->pluginLifecycleService = $pluginLifecycleService;
        $this->systemConfigService = $systemConfigService;
    }

    /**
     * @throws UpdateFailedException
     *
     * @return FinishResult|ValidResult
     *
     * Remove one plugin per run call, as this action can take some time we make a new request for each plugin
     */
    public function run(int $offset)
    {
        $plugins = $this->pluginCompatibility->getPluginsToDeactivate($this->toVersion, $this->context, $this->deactivationFilter);

        $pluginCount = \count($plugins);
        if ($pluginCount === 0) {
            return new FinishResult($offset, $offset);
        }

        $plugin = $plugins[0];
        ++$offset;
        $this->pluginLifecycleService->deactivatePlugin($plugin, $this->context);
        $deactivatedPlugins = (array) $this->systemConfigService->get(self::UPDATE_DEACTIVATED_PLUGINS) ?: [];
        $deactivatedPlugins[] = $plugin->getId();
        $this->systemConfigService->set(self::UPDATE_DEACTIVATED_PLUGINS, $deactivatedPlugins);

        if ($pluginCount === 1) {
            return new FinishResult($offset, $offset);
        }

        return new ValidResult($offset, $pluginCount + $offset);
    }
}
