<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Store\Services;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Plugin\PluginLifecycleService;
use Shopware\Core\Framework\Plugin\PluginManagementService;
use Shopware\Core\Framework\Plugin\PluginService;

/**
 * @internal
 */
#[Package('checkout')]
class ExtensionLifecycleService extends AbstractExtensionLifecycle
{
    public function __construct(
        private readonly AbstractAppExtensionService $appExtensionService,
        private readonly PluginService $pluginService,
        private readonly PluginLifecycleService $pluginLifecycleService,
        private readonly PluginManagementService $pluginManagementService
    ) {
    }

    public function install(string $type, string $technicalName, Context $context): void
    {
        if ($type === 'plugin') {
            $plugin = $this->pluginService->getPluginByName($technicalName, $context);
            $this->pluginLifecycleService->installPlugin($plugin, $context);

            return;
        }

        $this->appExtensionService->installExtension($technicalName, $context);
    }

    public function update(string $type, string $technicalName, bool $allowNewPermissions, Context $context): void
    {
        if ($type === 'plugin') {
            $plugin = $this->pluginService->getPluginByName($technicalName, $context);
            $this->pluginLifecycleService->updatePlugin($plugin, $context);

            return;
        }

        $this->appExtensionService->updateExtension($technicalName, $allowNewPermissions, $context);
    }

    public function uninstall(string $type, string $technicalName, bool $keepUserData, Context $context): void
    {
        if ($type === 'plugin') {
            $plugin = $this->pluginService->getPluginByName($technicalName, $context);
            $this->pluginLifecycleService->uninstallPlugin($plugin, $context, $keepUserData);

            return;
        }

        $this->appExtensionService->uninstallExtension($technicalName, $context, $keepUserData);
    }

    public function activate(string $type, string $technicalName, Context $context): void
    {
        if ($type === 'plugin') {
            $plugin = $this->pluginService->getPluginByName($technicalName, $context);
            $this->pluginLifecycleService->activatePlugin($plugin, $context);

            return;
        }

        $this->appExtensionService->activateExtension($technicalName, $context);
    }

    public function deactivate(string $type, string $technicalName, Context $context): void
    {
        if ($type === 'plugin') {
            $plugin = $this->pluginService->getPluginByName($technicalName, $context);
            $this->pluginLifecycleService->deactivatePlugin($plugin, $context);

            return;
        }

        $this->appExtensionService->deactivateExtension($technicalName, $context);
    }

    public function remove(string $type, string $technicalName, bool $keepUserData, Context $context): void
    {
        if ($type === 'plugin') {
            $plugin = $this->pluginService->getPluginByName($technicalName, $context);
            $this->pluginManagementService->deletePlugin($plugin, $context);

            return;
        }

        $this->appExtensionService->deleteExtension($technicalName, $keepUserData, $context);
    }

    protected function getDecorated(): AbstractExtensionLifecycle
    {
        throw new DecorationPatternException(self::class);
    }
}
