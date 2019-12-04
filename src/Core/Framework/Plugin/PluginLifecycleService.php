<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Plugin;

use Doctrine\DBAL\Connection;
use Psr\Cache\CacheItemPoolInterface;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\Migration\MigrationCollection;
use Shopware\Core\Framework\Migration\MigrationCollectionLoader;
use Shopware\Core\Framework\Migration\MigrationRuntime;
use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Plugin\Composer\CommandExecutor;
use Shopware\Core\Framework\Plugin\Context\ActivateContext;
use Shopware\Core\Framework\Plugin\Context\DeactivateContext;
use Shopware\Core\Framework\Plugin\Context\InstallContext;
use Shopware\Core\Framework\Plugin\Context\UninstallContext;
use Shopware\Core\Framework\Plugin\Context\UpdateContext;
use Shopware\Core\Framework\Plugin\Event\PluginPostActivateEvent;
use Shopware\Core\Framework\Plugin\Event\PluginPostDeactivateEvent;
use Shopware\Core\Framework\Plugin\Event\PluginPostInstallEvent;
use Shopware\Core\Framework\Plugin\Event\PluginPostUninstallEvent;
use Shopware\Core\Framework\Plugin\Event\PluginPostUpdateEvent;
use Shopware\Core\Framework\Plugin\Event\PluginPreActivateEvent;
use Shopware\Core\Framework\Plugin\Event\PluginPreDeactivateEvent;
use Shopware\Core\Framework\Plugin\Event\PluginPreInstallEvent;
use Shopware\Core\Framework\Plugin\Event\PluginPreUninstallEvent;
use Shopware\Core\Framework\Plugin\Event\PluginPreUpdateEvent;
use Shopware\Core\Framework\Plugin\Exception\PluginBaseClassNotFoundException;
use Shopware\Core\Framework\Plugin\Exception\PluginNotActivatedException;
use Shopware\Core\Framework\Plugin\Exception\PluginNotInstalledException;
use Shopware\Core\Framework\Plugin\KernelPluginLoader\KernelPluginLoader;
use Shopware\Core\Framework\Plugin\KernelPluginLoader\StaticKernelPluginLoader;
use Shopware\Core\Framework\Plugin\Requirement\Exception\RequirementStackException;
use Shopware\Core\Framework\Plugin\Requirement\RequirementsValidator;
use Shopware\Core\Framework\Plugin\Util\AssetService;
use Shopware\Core\Kernel;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Messenger\EventListener\StopWorkerOnRestartSignalListener;
use function Flag\next1797;

class PluginLifecycleService
{
    /**
     * @var EntityRepositoryInterface
     */
    private $pluginRepo;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * @var KernelPluginCollection
     */
    private $pluginCollection;

    /**
     * @var ContainerInterface
     */
    private $container;

    /**
     * @var MigrationCollection
     */
    private $migrationCollection;

    /**
     * @var MigrationCollectionLoader
     */
    private $migrationLoader;

    /**
     * @var MigrationRuntime
     */
    private $migrationRunner;

    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var AssetService
     */
    private $assetInstaller;

    /**
     * @var CommandExecutor
     */
    private $executor;

    /**
     * @var RequirementsValidator
     */
    private $requirementValidator;

    /**
     * @var string
     */
    private $shopwareVersion;

    /**
     * @var CacheItemPoolInterface
     */
    private $restartSignalCachePool;

    /**
     * @var SystemConfigService
     */
    private $systemConfigService;

    public function __construct(
        EntityRepositoryInterface $pluginRepo,
        EventDispatcherInterface $eventDispatcher,
        KernelPluginCollection $pluginCollection,
        ContainerInterface $container,
        MigrationCollection $migrationCollection,
        MigrationCollectionLoader $migrationLoader,
        MigrationRuntime $migrationRunner,
        Connection $connection,
        AssetService $assetInstaller,
        CommandExecutor $executor,
        RequirementsValidator $requirementValidator,
        CacheItemPoolInterface $restartSignalCachePool,
        string $shopwareVersion,
        SystemConfigService $systemConfigService
    ) {
        $this->pluginRepo = $pluginRepo;
        $this->eventDispatcher = $eventDispatcher;
        $this->pluginCollection = $pluginCollection;
        $this->container = $container;
        $this->migrationCollection = $migrationCollection;
        $this->migrationLoader = $migrationLoader;
        $this->migrationRunner = $migrationRunner;
        $this->connection = $connection;
        $this->assetInstaller = $assetInstaller;
        $this->executor = $executor;
        $this->requirementValidator = $requirementValidator;
        $this->systemConfigService = $systemConfigService;
        $this->shopwareVersion = $shopwareVersion;
        $this->restartSignalCachePool = $restartSignalCachePool;
    }

    /**
     * @throws RequirementStackException
     */
    public function installPlugin(PluginEntity $plugin, Context $shopwareContext): InstallContext
    {
        $pluginBaseClass = $this->getPluginBaseClass($plugin->getBaseClass());
        $pluginVersion = $plugin->getVersion();

        $installContext = new InstallContext(
            $pluginBaseClass,
            $shopwareContext,
            $this->shopwareVersion,
            $pluginVersion
        );

        if ($plugin->getInstalledAt()) {
            return $installContext;
        }

        // TODO NEXT-1797: Not usable with Composer 1.8, Wait for Release of Composer 2.0
        if (next1797()) {
            $this->executor->require($plugin->getComposerName());
        } else {
            $this->requirementValidator->validateRequirements($plugin, $shopwareContext, 'install');
        }

        $pluginData['id'] = $plugin->getId();

        // Makes sure the version is updated in the db after a re-installation
        $updateVersion = $plugin->getUpgradeVersion();
        if ($updateVersion !== null && $this->hasPluginUpdate($updateVersion, $pluginVersion)) {
            $pluginData['version'] = $updateVersion;
            $plugin->setVersion($updateVersion);
            $pluginData['upgradeVersion'] = null;
            $plugin->setUpgradeVersion(null);
            $upgradeDate = new \DateTime();
            $pluginData['upgradedAt'] = $upgradeDate->format(Defaults::STORAGE_DATE_TIME_FORMAT);
            $plugin->setUpgradedAt($upgradeDate);
        }

        $this->eventDispatcher->dispatch(new PluginPreInstallEvent($plugin, $installContext));

        $this->systemConfigService->savePluginConfiguration($pluginBaseClass, true);

        $pluginBaseClass->install($installContext);

        $this->runMigrations($pluginBaseClass);

        $installDate = new \DateTime();
        $pluginData['installedAt'] = $installDate->format(Defaults::STORAGE_DATE_TIME_FORMAT);
        $plugin->setInstalledAt($installDate);

        $this->updatePluginData($pluginData, $shopwareContext);

        $pluginBaseClass->postInstall($installContext);

        $this->eventDispatcher->dispatch(new PluginPostInstallEvent($plugin, $installContext));

        return $installContext;
    }

    /**
     * @throws PluginNotInstalledException
     */
    public function uninstallPlugin(
        PluginEntity $plugin,
        Context $shopwareContext,
        bool $keepUserData = false
    ): UninstallContext {
        $pluginBaseClassString = $plugin->getBaseClass();
        if ($plugin->getInstalledAt() === null) {
            throw new PluginNotInstalledException($plugin->getName());
        }

        if ($plugin->getActive()) {
            $this->deactivatePlugin($plugin, $shopwareContext);
        }

        $pluginBaseClass = $this->getPluginBaseClass($pluginBaseClassString);

        $uninstallContext = new UninstallContext(
            $pluginBaseClass,
            $shopwareContext,
            $this->shopwareVersion,
            $plugin->getVersion(),
            $keepUserData
        );

        $this->eventDispatcher->dispatch(new PluginPreUninstallEvent($plugin, $uninstallContext));
        $this->assetInstaller->removeAssetsOfBundle($pluginBaseClassString);

        $pluginBaseClass->uninstall($uninstallContext);

        if ($keepUserData === false) {
            $this->removeMigrations($pluginBaseClass);
        }

        $this->updatePluginData(
            [
                'id' => $plugin->getId(),
                'active' => false,
                'installedAt' => null,
            ],
            $shopwareContext
        );
        $plugin->setActive(false);
        $plugin->setInstalledAt(null);

        $this->eventDispatcher->dispatch(new PluginPostUninstallEvent($plugin, $uninstallContext));

        return $uninstallContext;
    }

    /**
     * @throws RequirementStackException
     */
    public function updatePlugin(PluginEntity $plugin, Context $shopwareContext): UpdateContext
    {
        $pluginBaseClassString = $plugin->getBaseClass();
        $pluginBaseClass = $this->getPluginBaseClass($pluginBaseClassString);

        $updateContext = new UpdateContext(
            $pluginBaseClass,
            $shopwareContext,
            $this->shopwareVersion,
            $plugin->getVersion(),
            $plugin->getUpgradeVersion() ?? $plugin->getVersion()
        );

        // TODO NEXT-1797: Not usable with Composer 1.8, Wait for Release of Composer 2.0
        if (next1797()) {
            $this->executor->require($plugin->getComposerName());
        } else {
            $this->requirementValidator->validateRequirements($plugin, $shopwareContext, 'update');
        }

        $this->eventDispatcher->dispatch(new PluginPreUpdateEvent($plugin, $updateContext));

        $this->systemConfigService->savePluginConfiguration($pluginBaseClass);

        $pluginBaseClass->update($updateContext);
        if ($plugin->getInstalledAt() && $plugin->getActive()) {
            $this->assetInstaller->copyAssetsFromBundle($pluginBaseClassString);
        }

        $this->runMigrations($pluginBaseClass);

        $updateVersion = $updateContext->getUpdatePluginVersion();
        $updateDate = new \DateTime();
        $this->updatePluginData(
            [
                'id' => $plugin->getId(),
                'version' => $updateVersion,
                'upgradeVersion' => null,
                'upgradedAt' => $updateDate->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ],
            $shopwareContext
        );
        $plugin->setVersion($updateVersion);
        $plugin->setUpgradeVersion(null);
        $plugin->setUpgradedAt($updateDate);

        $pluginBaseClass->postUpdate($updateContext);

        $this->eventDispatcher->dispatch(new PluginPostUpdateEvent($plugin, $updateContext));

        return $updateContext;
    }

    /**
     * @throws PluginNotInstalledException
     */
    public function activatePlugin(PluginEntity $plugin, Context $shopwareContext): ActivateContext
    {
        $pluginBaseClassString = $plugin->getBaseClass();
        if ($plugin->getInstalledAt() === null) {
            throw new PluginNotInstalledException($plugin->getName());
        }

        $pluginBaseClass = $this->getPluginBaseClass($pluginBaseClassString);

        $activateContext = new ActivateContext(
            $pluginBaseClass,
            $shopwareContext,
            $this->shopwareVersion,
            $plugin->getVersion()
        );

        if ($plugin->getActive()) {
            return $activateContext;
        }

        $this->eventDispatcher->dispatch(new PluginPreActivateEvent($plugin, $activateContext));

        $plugin->setActive(true);

        $this->rebuildContainerWithNewPluginState($plugin);

        $pluginBaseClass = $this->getPluginInstance($pluginBaseClassString);
        $activateContext = new ActivateContext(
            $pluginBaseClass,
            $shopwareContext,
            $this->shopwareVersion,
            $plugin->getVersion()
        );

        $pluginBaseClass->activate($activateContext);
        $this->assetInstaller->copyAssetsFromBundle($pluginBaseClassString);

        $this->updatePluginData(
            [
                'id' => $plugin->getId(),
                'active' => true,
            ],
            $shopwareContext
        );

        // stop in old cache dir
        $cacheItem = $this->restartSignalCachePool->getItem(StopWorkerOnRestartSignalListener::RESTART_REQUESTED_TIMESTAMP_KEY);
        $cacheItem->set(microtime(true));
        $this->restartSignalCachePool->save($cacheItem);

        $this->eventDispatcher->dispatch(new PluginPostActivateEvent($plugin, $activateContext));

        return $activateContext;
    }

    /**
     * @throws PluginNotInstalledException
     * @throws PluginNotActivatedException
     */
    public function deactivatePlugin(PluginEntity $plugin, Context $shopwareContext): DeactivateContext
    {
        $pluginBaseClassString = $plugin->getBaseClass();
        if ($plugin->getInstalledAt() === null) {
            throw new PluginNotInstalledException($plugin->getName());
        }

        if ($plugin->getActive() === false) {
            throw new PluginNotActivatedException($plugin->getName());
        }

        $pluginBaseClass = $this->getPluginInstance($pluginBaseClassString);

        $deactivateContext = new DeactivateContext(
            $pluginBaseClass,
            $shopwareContext,
            $this->shopwareVersion,
            $plugin->getVersion()
        );

        $this->eventDispatcher->dispatch(new PluginPreDeactivateEvent($plugin, $deactivateContext));

        $pluginBaseClass->deactivate($deactivateContext);
        $this->assetInstaller->removeAssetsOfBundle($pluginBaseClassString);

        $plugin->setActive(false);
        $this->rebuildContainerWithNewPluginState($plugin);

        $this->updatePluginData(
            [
                'id' => $plugin->getId(),
                'active' => false,
            ],
            $shopwareContext
        );

        // signal worker stop in old cache dir
        $cacheItem = $this->restartSignalCachePool->getItem(StopWorkerOnRestartSignalListener::RESTART_REQUESTED_TIMESTAMP_KEY);
        $cacheItem->set(microtime(true));
        $this->restartSignalCachePool->save($cacheItem);

        $this->eventDispatcher->dispatch(new PluginPostDeactivateEvent($plugin, $deactivateContext));

        return $deactivateContext;
    }

    private function getPluginBaseClass(string $pluginBaseClassString): Plugin
    {
        $baseClass = $this->pluginCollection->get($pluginBaseClassString);

        if ($baseClass === null) {
            throw new PluginBaseClassNotFoundException($pluginBaseClassString);
        }

        // set container because the plugin has not been initialized yet and therefore has no container set
        $baseClass->setContainer($this->container);

        return $baseClass;
    }

    private function runMigrations(Plugin $pluginBaseClass): void
    {
        $migrationPath = str_replace(
            '\\',
            '/',
            $pluginBaseClass->getPath() . str_replace(
                $pluginBaseClass->getNamespace(),
                '',
                $pluginBaseClass->getMigrationNamespace()
            )
        );

        if (!is_dir($migrationPath)) {
            return;
        }

        $this->migrationCollection->addDirectory($migrationPath, $pluginBaseClass->getMigrationNamespace());
        $this->migrationLoader->syncMigrationCollection($pluginBaseClass->getNamespace());
        iterator_to_array($this->migrationRunner->migrate());
    }

    private function removeMigrations(Plugin $pluginBaseClass): void
    {
        $class = $pluginBaseClass->getMigrationNamespace() . '\%';
        $class = str_replace('\\', '\\\\', $class);

        $this->connection->executeQuery('DELETE FROM migration WHERE class LIKE :class', ['class' => $class]);
    }

    private function hasPluginUpdate(string $updateVersion, string $currentVersion): bool
    {
        return version_compare($updateVersion, $currentVersion, '>');
    }

    private function updatePluginData(array $pluginData, Context $context): void
    {
        $this->pluginRepo->update([$pluginData], $context);
    }

    private function rebuildContainerWithNewPluginState(PluginEntity $plugin): void
    {
        /** @var Kernel $kernel */
        $kernel = $this->container->get('kernel');

        /** @var KernelPluginLoader $pluginLoader */
        $pluginLoader = $this->container->get(KernelPluginLoader::class);

        $plugins = $pluginLoader->getPluginInfos();
        foreach ($plugins as $i => $pluginData) {
            if ($pluginData['baseClass'] === $plugin->getBaseClass()) {
                $plugins[$i]['active'] = $plugin->getActive();
            }
        }

        /*
         * Reboot kernel with $plugin active=true.
         *
         * All other Requests wont have this plugin active until its updated in the db
         */
        $tmpStaticPluginLoader = new StaticKernelPluginLoader(
            $pluginLoader->getClassLoader(),
            $kernel->getContainer()->getParameter('kernel.plugin_dir'),
            $plugins
        );
        $kernel->reboot(null, $tmpStaticPluginLoader);

        // If symfony throws an exception when calling getContainer on an not booted kernel and catch it here
        /** @var ContainerInterface|null $newContainer */
        $newContainer = $kernel->getContainer();
        if (!$newContainer) {
            throw new \RuntimeException('Failed to reboot the kernel');
        }

        $this->container = $newContainer;
        $this->eventDispatcher = $this->container->get('event_dispatcher');
    }

    private function getPluginInstance(string $pluginBaseClassString): Plugin
    {
        if ($this->container->has($pluginBaseClassString)) {
            $containerPlugin = $this->container->get($pluginBaseClassString);
            if (!$containerPlugin instanceof Plugin) {
                throw new \RuntimeException($pluginBaseClassString . ' in the container should be an instance of ' . Plugin::class);
            }

            return $containerPlugin;
        }

        return $this->getPluginBaseClass($pluginBaseClassString);
    }
}
