<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Plugin;

use Composer\InstalledVersions;
use Composer\IO\NullIO;
use Composer\Semver\Comparator;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Clock\ClockInterface;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Plugin\Composer\CommandExecutor;
use Shopware\Core\Framework\Plugin\Util\VersionSanitizer;
use Symfony\Component\Messenger\EventListener\StopWorkerOnRestartSignalListener;

/**
 * Call it in a request of its own, before the plugin's install or update and after its uninstall:
 * the request that changes `vendor/` keeps the classes it loaded before, so the plugin lifecycle
 * has to run in a process that loads the new ones.
 *
 * @final
 */
#[Package('framework')]
class PluginComposerDependencyService
{
    /**
     * @internal
     */
    public function __construct(
        private readonly KernelPluginCollection $pluginCollection,
        private readonly CommandExecutor $executor,
        private readonly PluginService $pluginService,
        private readonly VersionSanitizer $versionSanitizer,
        private readonly CacheItemPoolInterface $restartSignalCachePool,
        private readonly ClockInterface $clock,
        private readonly bool $clusterSetup,
        private readonly string $projectDir,
    ) {
    }

    public function requireDependency(PluginEntity $plugin, Context $context): bool
    {
        if (!$this->getPluginBaseClass($plugin)->executeComposerCommands()) {
            return false;
        }

        return $this->requirePackage($plugin, $plugin->getUpgradeVersion() ?? $plugin->getVersion(), $context);
    }

    public function removeDependency(PluginEntity $plugin, Context $context): bool
    {
        if ($this->clusterSetup || !$plugin->getManagedByComposer() || !$plugin->isLocatedInCustomDirectory()) {
            return false;
        }

        // after an uninstall only plugins that asked for Composer, after an update only those that stopped asking
        $isUninstalled = $plugin->getInstalledAt() === null;
        if ($this->getPluginBaseClass($plugin)->executeComposerCommands() !== $isUninstalled) {
            return false;
        }

        $this->removePackage($plugin, $context);

        return true;
    }

    /**
     * @internal
     */
    public function isRequireNeeded(PluginEntity $plugin, string $version): bool
    {
        if ($this->clusterSetup) {
            return false;
        }

        try {
            $installedVersion = InstalledVersions::getVersion($this->getComposerName($plugin));
        } catch (\OutOfBoundsException) {
            return true;
        }

        if ($installedVersion === null) {
            return true;
        }

        return !Comparator::equalTo($this->versionSanitizer->sanitizePluginVersion($installedVersion), $version);
    }

    /**
     * @internal
     */
    public function requirePackage(PluginEntity $plugin, string $version, Context $context): bool
    {
        if (!$this->isRequireNeeded($plugin, $version)) {
            return false;
        }

        $this->executor->require($this->getComposerName($plugin) . ':' . $version, $plugin->getName());
        $this->afterComposerRun($context);

        return true;
    }

    /**
     * @internal
     */
    public function removePackage(PluginEntity $plugin, Context $context): void
    {
        if ($this->clusterSetup) {
            return;
        }

        $this->executor->remove($this->getComposerName($plugin), $plugin->getName());
        $this->afterComposerRun($context);
    }

    private function afterComposerRun(Context $context): void
    {
        // this process cached the installed packages of the replaced vendor directory
        $installed = $this->projectDir . '/vendor/composer/installed.php';
        if (is_file($installed)) {
            InstalledVersions::reload(require $installed);
        }

        $this->pluginService->refreshPlugins($context, new NullIO());

        $cacheItem = $this->restartSignalCachePool->getItem(StopWorkerOnRestartSignalListener::RESTART_REQUESTED_TIMESTAMP_KEY);
        $cacheItem->set((float) $this->clock->now()->format(Defaults::MICROTIME_FORMAT));
        $this->restartSignalCachePool->save($cacheItem);
    }

    private function getComposerName(PluginEntity $plugin): string
    {
        $composerName = $plugin->getComposerName();
        if ($composerName === null) {
            throw PluginException::composerJsonInvalid(
                $plugin->getPath() . '/composer.json',
                ['No name defined in composer.json']
            );
        }

        return $composerName;
    }

    private function getPluginBaseClass(PluginEntity $plugin): Plugin
    {
        $baseClass = $this->pluginCollection->get($plugin->getBaseClass());
        if ($baseClass === null) {
            throw PluginException::baseClassNotFound($plugin->getBaseClass());
        }

        return $baseClass;
    }
}
