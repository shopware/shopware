<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Plugin;

use Psr\Cache\CacheItemPoolInterface;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Context\SystemSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Migration\MigrationCollection;
use Shopware\Core\Framework\Migration\MigrationCollectionLoader;
use Shopware\Core\Framework\Migration\MigrationSource;
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
use Shopware\Core\Framework\Plugin\Exception\PluginComposerJsonInvalidException;
use Shopware\Core\Framework\Plugin\Exception\PluginHasActiveDependantsException;
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

/**
 * @internal
 */
class PluginLifecycleService
{
    private EntityRepositoryInterface $pluginRepo;

    private EventDispatcherInterface $eventDispatcher;

    private KernelPluginCollection $pluginCollection;

    private ContainerInterface $container;

    private MigrationCollectionLoader $migrationLoader;

    private AssetService $assetInstaller;

    private CommandExecutor $executor;

    private RequirementsValidator $requirementValidator;

    private string $shopwareVersion;

    private CacheItemPoolInterface $restartSignalCachePool;

    private SystemConfigService $systemConfigService;

    /**
     * @psalm-suppress ContainerDependency
     */
    public function __construct(
        EntityRepositoryInterface $pluginRepo,
        EventDispatcherInterface $eventDispatcher,
        KernelPluginCollection $pluginCollection,
        ContainerInterface $container,
        MigrationCollectionLoader $migrationLoader,
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
        $this->migrationLoader = $migrationLoader;
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
            $pluginVersion,
            $this->createMigrationCollection($pluginBaseClass)
        );

        if ($plugin->getInstalledAt()) {
            return $installContext;
        }

        if (Feature::isActive('FEATURE_NEXT_1797') && $pluginBaseClass->executeComposerCommands()) {
            $pluginComposerName = $plugin->getComposerName();
            if ($pluginComposerName === null) {
                throw new PluginComposerJsonInvalidException(
                    $pluginBaseClass->getPath() . '/composer.json',
                    ['No name defined in composer.json']
                );
            }

            $this->executor->require($pluginComposerName, $plugin->getName());
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

        $this->runMigrations($installContext);

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
        if ($plugin->getInstalledAt() === null) {
            throw new PluginNotInstalledException($plugin->getName());
        }

        if ($plugin->getActive()) {
            $this->deactivatePlugin($plugin, $shopwareContext);
        }

        $pluginBaseClassString = $plugin->getBaseClass();
        $pluginBaseClass = $this->getPluginBaseClass($pluginBaseClassString);

        if (Feature::isActive('FEATURE_NEXT_1797') && $pluginBaseClass->executeComposerCommands()) {
            $pluginComposerName = $plugin->getComposerName();
            if ($pluginComposerName === null) {
                throw new PluginComposerJsonInvalidException(
                    $pluginBaseClass->getPath() . '/composer.json',
                    ['No name defined in composer.json']
                );
            }
            $this->executor->remove($pluginComposerName, $plugin->getName());
        }

        $uninstallContext = new UninstallContext(
            $pluginBaseClass,
            $shopwareContext,
            $this->shopwareVersion,
            $plugin->getVersion(),
            $this->createMigrationCollection($pluginBaseClass),
            $keepUserData
        );
        $uninstallContext->setAutoMigrate(false);

        $this->eventDispatcher->dispatch(new PluginPreUninstallEvent($plugin, $uninstallContext));
        $this->assetInstaller->removeAssetsOfBundle($pluginBaseClassString);

        $pluginBaseClass->uninstall($uninstallContext);

        if (!$uninstallContext->keepUserData()) {
            $pluginBaseClass->removeMigrations();
        }

        if (!$uninstallContext->keepUserData()) {
            $this->systemConfigService->deletePluginConfiguration($pluginBaseClass);
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
        if ($plugin->getInstalledAt() === null) {
            throw new PluginNotInstalledException($plugin->getName());
        }

        $pluginBaseClassString = $plugin->getBaseClass();
        $pluginBaseClass = $this->getPluginBaseClass($pluginBaseClassString);

        $updateContext = new UpdateContext(
            $pluginBaseClass,
            $shopwareContext,
            $this->shopwareVersion,
            $plugin->getVersion(),
            $this->createMigrationCollection($pluginBaseClass),
            $plugin->getUpgradeVersion() ?? $plugin->getVersion()
        );

        if (Feature::isActive('FEATURE_NEXT_1797') && $pluginBaseClass->executeComposerCommands()) {
            $pluginComposerName = $plugin->getComposerName();
            if ($pluginComposerName === null) {
                throw new PluginComposerJsonInvalidException(
                    $pluginBaseClass->getPath() . '/composer.json',
                    ['No name defined in composer.json']
                );
            }
            $this->executor->require($pluginComposerName, $plugin->getName());
        } else {
            $this->requirementValidator->validateRequirements($plugin, $shopwareContext, 'update');
        }

        $this->eventDispatcher->dispatch(new PluginPreUpdateEvent($plugin, $updateContext));

        $this->systemConfigService->savePluginConfiguration($pluginBaseClass);

        try {
            $pluginBaseClass->update($updateContext);
        } catch (\Throwable $updateException) {
            if ($plugin->getActive()) {
                try {
                    $this->deactivatePlugin($plugin, $shopwareContext);
                } catch (\Throwable $deactivateException) {
                    $this->updatePluginData(
                        [
                            'id' => $plugin->getId(),
                            'active' => false,
                        ],
                        $shopwareContext
                    );
                }
            }

            throw $updateException;
        }

        if ($plugin->getActive()) {
            $this->assetInstaller->copyAssetsFromBundle($pluginBaseClassString);
        }

        $this->runMigrations($updateContext);

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
        if ($plugin->getInstalledAt() === null) {
            throw new PluginNotInstalledException($plugin->getName());
        }

        $pluginBaseClassString = $plugin->getBaseClass();
        $pluginBaseClass = $this->getPluginBaseClass($pluginBaseClassString);

        $activateContext = new ActivateContext(
            $pluginBaseClass,
            $shopwareContext,
            $this->shopwareVersion,
            $plugin->getVersion(),
            $this->createMigrationCollection($pluginBaseClass)
        );

        if ($plugin->getActive()) {
            return $activateContext;
        }

        $this->requirementValidator->validateRequirements($plugin, $shopwareContext, 'activate');

        $this->eventDispatcher->dispatch(new PluginPreActivateEvent($plugin, $activateContext));

        $plugin->setActive(true);

        // only skip rebuild if plugin has overwritten rebuildContainer method and source is system source (CLI)
        if ($pluginBaseClass->rebuildContainer() || !$shopwareContext->getSource() instanceof SystemSource) {
            $this->rebuildContainerWithNewPluginState($plugin);
        }

        $pluginBaseClass = $this->getPluginInstance($pluginBaseClassString);
        $activateContext = new ActivateContext(
            $pluginBaseClass,
            $shopwareContext,
            $this->shopwareVersion,
            $plugin->getVersion(),
            $this->createMigrationCollection($pluginBaseClass)
        );
        $activateContext->setAutoMigrate(false);

        $pluginBaseClass->activate($activateContext);

        $this->runMigrations($activateContext);
        $this->assetInstaller->copyAssetsFromBundle($pluginBaseClassString);

        $this->updatePluginData(
            [
                'id' => $plugin->getId(),
                'active' => true,
            ],
            $shopwareContext
        );

        $this->signalWorkerStopInOldCacheDir();

        $this->eventDispatcher->dispatch(new PluginPostActivateEvent($plugin, $activateContext));

        return $activateContext;
    }

    /**
     * @throws PluginNotInstalledException
     * @throws PluginNotActivatedException
     * @throws PluginHasActiveDependantsException
     */
    public function deactivatePlugin(PluginEntity $plugin, Context $shopwareContext): DeactivateContext
    {
        if ($plugin->getInstalledAt() === null) {
            throw new PluginNotInstalledException($plugin->getName());
        }

        if ($plugin->getActive() === false) {
            throw new PluginNotActivatedException($plugin->getName());
        }

        $dependants = $this->requirementValidator->resolveActiveDependants(
            $plugin,
            $this->getEntities($this->pluginCollection->all(), $shopwareContext)->getElements()
        );

        if (\count($dependants) > 0) {
            throw new PluginHasActiveDependantsException($plugin->getName(), $dependants);
        }

        $pluginBaseClassString = $plugin->getBaseClass();
        $pluginBaseClass = $this->getPluginInstance($pluginBaseClassString);

        $deactivateContext = new DeactivateContext(
            $pluginBaseClass,
            $shopwareContext,
            $this->shopwareVersion,
            $plugin->getVersion(),
            $this->createMigrationCollection($pluginBaseClass)
        );
        $deactivateContext->setAutoMigrate(false);

        $this->eventDispatcher->dispatch(new PluginPreDeactivateEvent($plugin, $deactivateContext));

        $pluginBaseClass->deactivate($deactivateContext);

        $this->assetInstaller->removeAssetsOfBundle($pluginBaseClassString);

        $plugin->setActive(false);

        // only skip rebuild if plugin has overwritten rebuildContainer method and source is system source (CLI)
        if ($pluginBaseClass->rebuildContainer() || !$shopwareContext->getSource() instanceof SystemSource) {
            $this->rebuildContainerWithNewPluginState($plugin);
        }

        $this->updatePluginData(
            [
                'id' => $plugin->getId(),
                'active' => false,
            ],
            $shopwareContext
        );

        $this->signalWorkerStopInOldCacheDir();

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

    private function createMigrationCollection(Plugin $pluginBaseClass): MigrationCollection
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
            return $this->migrationLoader->collect('null');
        }

        $this->migrationLoader->addSource(new MigrationSource($pluginBaseClass->getName(), [
            $migrationPath => $pluginBaseClass->getMigrationNamespace(),
        ]));

        $collection = $this->migrationLoader
            ->collect($pluginBaseClass->getName());

        $collection->sync();

        return $collection;
    }

    private function runMigrations(InstallContext $context): void
    {
        if (!$context->isAutoMigrate()) {
            return;
        }

        $context->getMigrationCollection()->migrateInPlace();
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

        $pluginDir = $kernel->getContainer()->getParameter('kernel.plugin_dir');
        if (!\is_string($pluginDir)) {
            throw new \RuntimeException('Container parameter "kernel.plugin_dir" needs to be a string');
        }

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
        $tmpStaticPluginLoader = new StaticKernelPluginLoader($pluginLoader->getClassLoader(), $pluginDir, $plugins);
        $kernel->reboot(null, $tmpStaticPluginLoader);

        // If symfony throws an exception when calling getContainer on a not booted kernel and catch it here
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

    private function signalWorkerStopInOldCacheDir(): void
    {
        $cacheItem = $this->restartSignalCachePool->getItem(StopWorkerOnRestartSignalListener::RESTART_REQUESTED_TIMESTAMP_KEY);
        $cacheItem->set(microtime(true));
        $this->restartSignalCachePool->save($cacheItem);
    }

    /**
     * Takes plugin base classes and returns the corresponding entities.
     *
     * @param Plugin[] $plugins
     */
    private function getEntities(array $plugins, Context $context): EntitySearchResult
    {
        $names = array_map(static function (Plugin $plugin) {
            return $plugin->getName();
        }, $plugins);

        return $this->pluginRepo->search(
            (new Criteria())->addFilter(new EqualsAnyFilter('name', $names)),
            $context
        );
    }
}
