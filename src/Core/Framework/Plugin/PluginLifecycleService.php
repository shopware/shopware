<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Plugin;

use DateTime;
use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\Framework;
use Shopware\Core\Framework\Migration\MigrationCollection;
use Shopware\Core\Framework\Migration\MigrationCollectionLoader;
use Shopware\Core\Framework\Migration\MigrationRuntime;
use Shopware\Core\Framework\Plugin;
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
use Shopware\Core\Framework\Plugin\Exception\PluginNotActivatedException;
use Shopware\Core\Framework\Plugin\Exception\PluginNotInstalledException;
use Shopware\Core\Framework\Util\AssetServiceInterface;
use Shopware\Core\Kernel;
use Symfony\Component\DependencyInjection\ContainerAwareTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

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
     * @var Kernel
     */
    private $kernel;

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
     * @var AssetServiceInterface
     */
    private $assetInstaller;

    public function __construct(
        EntityRepositoryInterface $pluginRepo,
        EventDispatcherInterface $eventDispatcher,
        Kernel $kernel,
        ContainerInterface $container,
        MigrationCollection $migrationCollection,
        MigrationCollectionLoader $migrationLoader,
        MigrationRuntime $migrationRunner,
        Connection $connection,
        AssetServiceInterface $assetInstaller
    ) {
        $this->pluginRepo = $pluginRepo;
        $this->eventDispatcher = $eventDispatcher;
        $this->kernel = $kernel;
        $this->container = $container;
        $this->migrationCollection = $migrationCollection;
        $this->migrationLoader = $migrationLoader;
        $this->migrationRunner = $migrationRunner;
        $this->connection = $connection;
        $this->assetInstaller = $assetInstaller;
    }

    public function installPlugin(PluginEntity $plugin, Context $shopwareContext): InstallContext
    {
        $pluginBaseClass = $this->getPluginBaseClass($plugin->getName());

        $pluginVersion = $plugin->getVersion();
        $installContext = new InstallContext(
            $pluginBaseClass,
            $shopwareContext,
            Framework::VERSION,
            $pluginVersion
        );

        if ($plugin->getInstalledAt()) {
            return $installContext;
        }

        $pluginData['id'] = $plugin->getId();

        // Makes sure the version is updated in the db after a re-installation
        $updateVersion = $plugin->getUpgradeVersion();
        if ($updateVersion !== null && $this->hasPluginUpdate($updateVersion, $pluginVersion)) {
            $pluginData['version'] = $updateVersion;
            $plugin->setVersion($updateVersion);
            $pluginData['upgradeVersion'] = null;
            $plugin->setUpgradeVersion(null);
            $upgradeDate = new DateTime();
            $pluginData['upgradedAt'] = $upgradeDate->format(Defaults::DATE_FORMAT);
            $plugin->setUpgradedAt($upgradeDate);
        }

        $this->eventDispatcher->dispatch(
            PluginPreInstallEvent::NAME,
            new PluginPreInstallEvent($plugin, $installContext)
        );

        $pluginBaseClass->install($installContext);

        $this->runMigrations($pluginBaseClass);

        $installDate = new DateTime();
        $pluginData['installedAt'] = $installDate->format(Defaults::DATE_FORMAT);
        $plugin->setInstalledAt($installDate);

        $this->updatePluginData($pluginData, $shopwareContext);

        $pluginBaseClass->postInstall($installContext);

        $this->eventDispatcher->dispatch(
            PluginPostInstallEvent::NAME,
            new PluginPostInstallEvent($plugin, $installContext)
        );

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
        $pluginName = $plugin->getName();
        if ($plugin->getInstalledAt() === null) {
            throw new PluginNotInstalledException($pluginName);
        }

        $pluginBaseClass = $this->getPluginBaseClass($pluginName);

        $uninstallContext = new UninstallContext(
            $pluginBaseClass,
            $shopwareContext,
            Framework::VERSION,
            $plugin->getVersion(),
            $keepUserData
        );

        $this->eventDispatcher->dispatch(
            PluginPreUninstallEvent::NAME,
            new PluginPreUninstallEvent($plugin, $uninstallContext)
        );

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

        $this->eventDispatcher->dispatch(
            PluginPostUninstallEvent::NAME,
            new PluginPostUninstallEvent($plugin, $uninstallContext)
        );

        return $uninstallContext;
    }

    public function updatePlugin(PluginEntity $plugin, Context $shopwareContext): UpdateContext
    {
        $pluginBaseClass = $this->getPluginBaseClass($plugin->getName());

        $updateContext = new UpdateContext(
            $pluginBaseClass,
            $shopwareContext,
            Framework::VERSION,
            $plugin->getVersion(),
            $plugin->getUpgradeVersion() ?? $plugin->getVersion()
        );

        $this->eventDispatcher->dispatch(
            PluginPreUpdateEvent::NAME,
            new PluginPreUpdateEvent($plugin, $updateContext)
        );

        $pluginBaseClass->update($updateContext);

        $this->runMigrations($pluginBaseClass);

        $updateVersion = $updateContext->getUpdatePluginVersion();
        $updateDate = new DateTime();
        $this->updatePluginData(
            [
                'id' => $plugin->getId(),
                'version' => $updateVersion,
                'upgradeVersion' => null,
                'upgradedAt' => $updateDate->format(Defaults::DATE_FORMAT),
            ],
            $shopwareContext
        );
        $plugin->setVersion($updateVersion);
        $plugin->setUpgradeVersion(null);
        $plugin->setUpgradedAt($updateDate);

        $pluginBaseClass->postUpdate($updateContext);

        $this->eventDispatcher->dispatch(
            PluginPostUpdateEvent::NAME,
            new PluginPostUpdateEvent($plugin, $updateContext)
        );

        return $updateContext;
    }

    /**
     * @throws PluginNotInstalledException
     */
    public function activatePlugin(PluginEntity $plugin, Context $shopwareContext): ActivateContext
    {
        $pluginName = $plugin->getName();
        if ($plugin->getInstalledAt() === null) {
            throw new PluginNotInstalledException($pluginName);
        }

        $pluginBaseClass = $this->getPluginBaseClass($pluginName);

        $activateContext = new ActivateContext(
            $pluginBaseClass,
            $shopwareContext,
            Framework::VERSION,
            $plugin->getVersion()
        );

        if ($plugin->getActive()) {
            return $activateContext;
        }

        $this->eventDispatcher->dispatch(
            PluginPreActivateEvent::NAME,
            new PluginPreActivateEvent($plugin, $activateContext)
        );

        $pluginBaseClass->activate($activateContext);
        $this->assetInstaller->copyAssetsFromBundle($pluginName);

        $this->updatePluginData(
            [
                'id' => $plugin->getId(),
                'active' => true,
            ],
            $shopwareContext
        );
        $plugin->setActive(true);

        $this->eventDispatcher->dispatch(
            PluginPostActivateEvent::NAME,
            new PluginPostActivateEvent($plugin, $activateContext)
        );

        return $activateContext;
    }

    /**
     * @throws PluginNotInstalledException
     * @throws PluginNotActivatedException
     */
    public function deactivatePlugin(PluginEntity $plugin, Context $shopwareContext): DeactivateContext
    {
        $pluginName = $plugin->getName();
        if ($plugin->getInstalledAt() === null) {
            throw new PluginNotInstalledException($pluginName);
        }

        if ($plugin->getActive() === false) {
            throw new PluginNotActivatedException($pluginName);
        }

        $pluginBaseClass = $this->getPluginBaseClass($pluginName);

        $deactivateContext = new DeactivateContext(
            $pluginBaseClass,
            $shopwareContext,
            Framework::VERSION,
            $plugin->getVersion()
        );

        $this->eventDispatcher->dispatch(
            PluginPreDeactivateEvent::NAME,
            new PluginPreDeactivateEvent($plugin, $deactivateContext)
        );

        $pluginBaseClass->deactivate($deactivateContext);
        $this->assetInstaller->removeAssetsOfBundle($pluginName);

        $this->updatePluginData(
            [
                'id' => $plugin->getId(),
                'active' => false,
            ],
            $shopwareContext
        );
        $plugin->setActive(false);

        $this->eventDispatcher->dispatch(
            PluginPostDeactivateEvent::NAME,
            new PluginPostDeactivateEvent($plugin, $deactivateContext)
        );

        return $deactivateContext;
    }

    private function getPluginBaseClass(string $pluginName): Plugin
    {
        /** @var Plugin|ContainerAwareTrait $baseClass */
        $baseClass = $this->kernel::getPlugins()->get($pluginName);
        // set container because the plugin has not been initialized yet and therefore has no container set
        $baseClass->setContainer($this->container);

        return $baseClass;
    }

    private function runMigrations(Plugin $pluginBaseClass): void
    {
        $migrationPath = $pluginBaseClass->getPath() . str_replace(
            $pluginBaseClass->getNamespace(),
            '',
            str_replace('\\', '/', $pluginBaseClass->getMigrationNamespace())
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
}
