<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Store\Services;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Plugin\PluginLifecycleService;
use Shopware\Core\Framework\Plugin\PluginManagementService;
use Shopware\Core\Framework\Plugin\PluginService;

/**
 * @internal
 */
class ExtensionLifecycleService extends AbstractExtensionLifecycle
{
    /**
     * @var AbstractStoreAppLifecycleService
     */
    private $storeAppLifecycleService;

    /**
     * @var PluginLifecycleService
     */
    private $pluginLifecycleService;

    /**
     * @var PluginService
     */
    private $pluginService;

    /**
     * @var PluginManagementService
     */
    private $pluginManagementService;

    public function __construct(
        AbstractStoreAppLifecycleService $storeAppLifecycleService,
        PluginService $pluginService,
        PluginLifecycleService $pluginLifecycleService,
        PluginManagementService $pluginManagementService
    ) {
        $this->storeAppLifecycleService = $storeAppLifecycleService;
        $this->pluginService = $pluginService;
        $this->pluginLifecycleService = $pluginLifecycleService;
        $this->pluginManagementService = $pluginManagementService;
    }

    public function install(string $type, string $technicalName, Context $context): void
    {
        if ($type === 'plugin') {
            $plugin = $this->pluginService->getPluginByName($technicalName, $context);
            $this->pluginLifecycleService->installPlugin($plugin, $context);

            return;
        }

        $this->storeAppLifecycleService->installExtension($technicalName, $context);
    }

    public function update(string $type, string $technicalName, Context $context): void
    {
        if ($type === 'plugin') {
            $plugin = $this->pluginService->getPluginByName($technicalName, $context);
            $this->pluginLifecycleService->updatePlugin($plugin, $context);

            return;
        }

        $this->storeAppLifecycleService->updateExtension($technicalName, true, $context);
    }

    public function uninstall(string $type, string $technicalName, bool $keepUserData, Context $context): void
    {
        if ($type === 'plugin') {
            $plugin = $this->pluginService->getPluginByName($technicalName, $context);
            $this->pluginLifecycleService->uninstallPlugin($plugin, $context, $keepUserData);

            return;
        }

        $this->storeAppLifecycleService->uninstallExtension($technicalName, $context, $keepUserData);
    }

    public function activate(string $type, string $technicalName, Context $context): void
    {
        if ($type === 'plugin') {
            $plugin = $this->pluginService->getPluginByName($technicalName, $context);
            $this->pluginLifecycleService->activatePlugin($plugin, $context);

            return;
        }

        $this->storeAppLifecycleService->activateExtension($technicalName, $context);
    }

    public function deactivate(string $type, string $technicalName, Context $context): void
    {
        if ($type === 'plugin') {
            $plugin = $this->pluginService->getPluginByName($technicalName, $context);
            $this->pluginLifecycleService->deactivatePlugin($plugin, $context);

            return;
        }

        $this->storeAppLifecycleService->deactivateExtension($technicalName, $context);
    }

    public function remove(string $type, string $technicalName, Context $context): void
    {
        if ($type === 'plugin') {
            $plugin = $this->pluginService->getPluginByName($technicalName, $context);
            $this->pluginManagementService->deletePlugin($plugin, $context);

            return;
        }

        $this->storeAppLifecycleService->deleteExtension($technicalName);
    }

    protected function getDecorated(): AbstractExtensionLifecycle
    {
        throw new DecorationPatternException(self::class);
    }
}
