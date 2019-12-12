<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Update\Steps;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Plugin\PluginLifecycleService;
use Shopware\Core\Framework\Update\Exception\UpdateFailedException;
use Shopware\Core\Framework\Update\Services\PluginCompatibility;
use Shopware\Core\Framework\Update\Struct\Version;
use Shopware\Core\System\SystemConfig\SystemConfigService;

class ReactivatePluginsStep
{
    public const UPDATE_DEACTIVATED_PLUGINS = 'core.update.deactivatedPlugins';
    public const UPDATE_FAILED_REACTIVATED_PLUGINS = 'core.update.failedReactivatedPlugins';

    /**
     * @var PluginCompatibility
     */
    private $pluginCompatibility;

    /**
     * @var Version
     */
    private $currentVersion;

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
        Version $currentVersion,
        PluginCompatibility $pluginCompatibility,
        PluginLifecycleService $pluginLifecycleService,
        SystemConfigService $systemConfigService,
        Context $context
    ) {
        $this->currentVersion = $currentVersion;
        $this->pluginCompatibility = $pluginCompatibility;
        $this->context = $context;
        $this->pluginLifecycleService = $pluginLifecycleService;
        $this->systemConfigService = $systemConfigService;
    }

    /**
     * @throws UpdateFailedException
     *
     * @return FinishResult|ValidResult
     */
    public function run(int $offset)
    {
        $requestTime = time();

        $deactivatedPlugins = $this->systemConfigService->get(self::UPDATE_DEACTIVATED_PLUGINS) ?: [];
        $failed = $this->systemConfigService->get(self::UPDATE_FAILED_REACTIVATED_PLUGINS) ?: [];

        $deactivatedPlugins = array_unique($deactivatedPlugins);

        $plugins = $this->pluginCompatibility->getPluginsToReactivate($deactivatedPlugins, $this->currentVersion, $this->context);

        $pluginCount = count($deactivatedPlugins);

        foreach ($plugins as $plugin) {
            ++$offset;

            try {
                $this->pluginLifecycleService->activatePlugin($plugin, $this->context);
            } catch (\Throwable $e) {
                $failed[$plugin->getId()] = $e->getMessage();
            } finally {
                $deactivatedPlugins = array_diff($deactivatedPlugins, [$plugin->getId()]);
            }

            if ($offset < $pluginCount && (time() - $requestTime) >= 1) {
                return new ValidResult($offset, $pluginCount + $offset);
            }
        }

        $this->systemConfigService->set(self::UPDATE_DEACTIVATED_PLUGINS, $deactivatedPlugins);
        $this->systemConfigService->set(self::UPDATE_FAILED_REACTIVATED_PLUGINS, $failed);

        return new FinishResult($pluginCount + $offset, $pluginCount + $offset);
    }
}
