<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Update\Steps;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Store\Services\AbstractExtensionLifecycle;
use Shopware\Core\Framework\Update\Exception\UpdateFailedException;
use Shopware\Core\Framework\Update\Services\PluginCompatibility;
use Shopware\Core\Framework\Update\Struct\Version;
use Shopware\Core\System\SystemConfig\SystemConfigService;

class DeactivateExtensionsStep
{
    public const UPDATE_DEACTIVATED_PLUGINS = 'core.update.deactivatedPlugins';

    private string $deactivationFilter;

    private PluginCompatibility $pluginCompatibility;

    private Version $toVersion;

    private Context $context;

    private AbstractExtensionLifecycle $extensionLifecycleService;

    private SystemConfigService $systemConfigService;

    public function __construct(
        Version $toVersion,
        string $deactivationFilter,
        PluginCompatibility $pluginCompatibility,
        AbstractExtensionLifecycle $extensionLifecycleService,
        SystemConfigService $systemConfigService,
        Context $context
    ) {
        $this->deactivationFilter = $deactivationFilter;
        $this->pluginCompatibility = $pluginCompatibility;
        $this->toVersion = $toVersion;
        $this->context = $context;
        $this->extensionLifecycleService = $extensionLifecycleService;
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
        $extensions = $this->pluginCompatibility->getExtensionsToDeactivate($this->toVersion, $this->context, $this->deactivationFilter);

        $extensionCount = \count($extensions);
        if ($extensionCount === 0) {
            return new FinishResult($offset, $offset);
        }

        $extension = $extensions[0];
        ++$offset;
        $this->extensionLifecycleService->deactivate($extension->getType(), $extension->getName(), $this->context);

        $deactivatedPlugins = (array) $this->systemConfigService->get(self::UPDATE_DEACTIVATED_PLUGINS) ?: [];
        $deactivatedPlugins[] = $extension->getId();
        $this->systemConfigService->set(self::UPDATE_DEACTIVATED_PLUGINS, $deactivatedPlugins);

        if ($extensionCount === 1) {
            return new FinishResult($offset, $offset);
        }

        return new ValidResult($offset, $extensionCount + $offset);
    }
}
