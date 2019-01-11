<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Plugin;

use Composer\IO\IOInterface;
use DateTime;
use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\RepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
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
use Shopware\Core\Framework\Plugin\Exception\PluginNotActivatedException;
use Shopware\Core\Framework\Plugin\Exception\PluginNotFoundException;
use Shopware\Core\Framework\Plugin\Exception\PluginNotInstalledException;
use Shopware\Core\Framework\Plugin\Helper\ComposerPackageProvider;
use Shopware\Core\Kernel;
use Shopware\Core\System\Language\LanguageEntity;
use Symfony\Component\DependencyInjection\ContainerAwareTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Finder\Finder;

class PluginManager
{
    /**
     * @var string
     */
    private $pluginPath;

    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var Kernel
     */
    private $kernel;

    /**
     * @var ContainerInterface
     */
    private $container;

    /**
     * @var MigrationCollectionLoader
     */
    private $migrationLoader;

    /**
     * @var MigrationRuntime
     */
    private $migrationRunner;

    /**
     * @var MigrationCollection
     */
    private $migrationCollection;

    /**
     * @var RepositoryInterface
     */
    private $pluginRepo;

    /**
     * @var RepositoryInterface
     */
    private $languageRepo;

    /**
     * @var ComposerPackageProvider
     */
    private $composerPackageProvider;

    public function __construct(
        string $pluginPath,
        Kernel $kernel,
        Connection $connection,
        ContainerInterface $container,
        MigrationCollectionLoader $migrationLoader,
        MigrationCollection $migrationCollection,
        MigrationRuntime $migrationRunner,
        RepositoryInterface $pluginRepo,
        RepositoryInterface $languageRepo,
        ComposerPackageProvider $composerPackageProvider
    ) {
        $this->pluginPath = $pluginPath;
        $this->kernel = $kernel;
        $this->connection = $connection;
        $this->container = $container;
        $this->migrationLoader = $migrationLoader;
        $this->migrationCollection = $migrationCollection;
        $this->migrationRunner = $migrationRunner;
        $this->pluginRepo = $pluginRepo;
        $this->languageRepo = $languageRepo;
        $this->composerPackageProvider = $composerPackageProvider;
    }

    /**
     * @throws PluginNotFoundException
     */
    public function getPluginByName(string $pluginName, Context $context): PluginEntity
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('name', $pluginName));

        $pluginEntity = $this->getPlugins($criteria, $context)->first();
        if ($pluginEntity === null) {
            throw new PluginNotFoundException($pluginName);
        }

        return $pluginEntity;
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
        $upgradeVersion = $plugin->getUpgradeVersion();
        if ($upgradeVersion !== null && $this->hasInfoNewerVersion($upgradeVersion, $pluginVersion)) {
            $pluginData['version'] = $upgradeVersion;
            $pluginData['upgradedAt'] = (new DateTime())->format(Defaults::DATE_FORMAT);
        }

        $pluginBaseClass->install($installContext);

        $this->runMigrations($pluginBaseClass);

        $pluginData['installedAt'] = (new DateTime())->format(Defaults::DATE_FORMAT);

        $this->updatePlugin($pluginData, $shopwareContext);

        $pluginBaseClass->postInstall($installContext);

        return $installContext;
    }

    public function uninstallPlugin(
        PluginEntity $plugin,
        Context $shopwareContext,
        bool $removeUserData = true
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
            !$removeUserData
        );

        $pluginBaseClass->uninstall($uninstallContext);

        if ($removeUserData) {
            $this->removeMigrations($pluginBaseClass);
        }

        $this->updatePlugin(
            [
                'id' => $plugin->getId(),
                'active' => false,
                'installedAt' => null,
            ],
            $shopwareContext
        );

        return $uninstallContext;
    }

    public function upgradePlugin(PluginEntity $plugin, Context $shopwareContext): UpdateContext
    {
        $pluginBaseClass = $this->getPluginBaseClass($plugin->getName());

        $updateContext = new UpdateContext(
            $pluginBaseClass,
            $shopwareContext,
            Framework::VERSION,
            $plugin->getVersion(),
            $plugin->getUpgradeVersion() ?? $plugin->getVersion()
        );

        $pluginBaseClass->update($updateContext);

        $this->runMigrations($pluginBaseClass);

        $this->updatePlugin(
            [
                'id' => $plugin->getId(),
                'version' => $updateContext->getUpdatePluginVersion(),
                'upgradeVersion' => null,
                'upgradedAt' => (new DateTime())->format(Defaults::DATE_FORMAT),
            ],
            $shopwareContext
        );

        $pluginBaseClass->postUpdate($updateContext);

        return $updateContext;
    }

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

        $pluginBaseClass->activate($activateContext);

        $this->updatePlugin(
            [
                'id' => $plugin->getId(),
                'active' => true,
            ],
            $shopwareContext
        );

        return $activateContext;
    }

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

        $pluginBaseClass->deactivate($deactivateContext);

        $this->updatePlugin(
            [
                'id' => $plugin->getId(),
                'active' => false,
            ],
            $shopwareContext
        );

        return $deactivateContext;
    }

    public function updatePlugins(Context $shopwareContext, IOInterface $composerIO): void
    {
        $finder = new Finder();
        $filesystemPlugins = $finder->directories()->depth(0)->in($this->pluginPath)->getIterator();

        $installedPlugins = $this->getPlugins(new Criteria(), $shopwareContext);

        $plugins = [];
        foreach ($filesystemPlugins as $plugin) {
            $pluginName = $plugin->getFilename();
            $pluginPath = $plugin->getPathname();

            $info = $this->composerPackageProvider->getPluginInformation($pluginPath, $composerIO);
            $pluginVersion = $info->getVersion();
            /** @var array $extra */
            $extra = $info->getExtra();

            $authors = null;
            $composerAuthors = $info->getAuthors();
            if ($composerAuthors !== null) {
                $authorNames = array_column($info->getAuthors(), 'name');
                $authors = implode(', ', $authorNames);
            }
            $license = $info->getLicense();

            $pluginData = [
                'name' => $pluginName,
                'author' => $authors,
                'copyright' => $extra['copyright'] ?? null,
                'license' => implode(', ', $license),
                'version' => $pluginVersion,
            ];

            $pluginData = $this->getTranslation($extra, $pluginData, 'label', 'label', $shopwareContext);
            $pluginData = $this->getTranslation($extra, $pluginData, 'description', 'description', $shopwareContext);
            $pluginData = $this->getTranslation($extra, $pluginData, 'manufacturerLink', 'manufacturerLink', $shopwareContext);
            $pluginData = $this->getTranslation($extra, $pluginData, 'supportLink', 'supportLink', $shopwareContext);

            /** @var PluginEntity $currentPluginEntity */
            $currentPluginEntity = $installedPlugins->filterByProperty('name', $pluginName)->first();
            if ($currentPluginEntity !== null) {
                $currentPluginId = $currentPluginEntity->getId();
                $pluginData['id'] = $currentPluginId;

                $currentPluginVersion = $currentPluginEntity->getVersion();
                if ($this->hasInfoNewerVersion($pluginVersion, $currentPluginVersion)) {
                    $pluginData['version'] = $currentPluginVersion;
                    $pluginData['upgradeVersion'] = $pluginVersion;
                } else {
                    $pluginData['upgradeVersion'] = null;
                }

                $installedPlugins->remove($currentPluginId);
            }

            $plugins[] = $pluginData;
        }

        $this->pluginRepo->upsert($plugins, $shopwareContext);

        // delete plugins, which are in storage but not in filesystem anymore
        $deletePluginIds = $installedPlugins->getIds();
        if (\count($deletePluginIds) !== 0) {
            $deletePlugins = [];
            foreach ($deletePluginIds as $deletePluginId) {
                $deletePlugins[] = ['id' => $deletePluginId];
            }
            $this->pluginRepo->delete($deletePlugins, $shopwareContext);
        }
    }

    public function getPlugins(Criteria $criteria, Context $context): PluginCollection
    {
        /** @var PluginCollection $pluginCollection */
        $pluginCollection = $this->pluginRepo->search($criteria, $context)->getEntities();

        return $pluginCollection;
    }

    private function hasInfoNewerVersion(string $upgradeVersion, string $currentVersion): bool
    {
        return version_compare($upgradeVersion, $currentVersion, '>');
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

    private function getTranslation(
        array $composerExtra,
        array $pluginData,
        string $composerProperty,
        string $pluginField,
        Context $shopwareContext
    ): array {
        foreach ($composerExtra[$composerProperty] ?? [] as $locale => $labelTranslation) {
            $languageId = $this->getLanguageIdForLocale($locale, $shopwareContext);
            if ($languageId === '') {
                continue;
            }

            $pluginData['translations'][$languageId][$pluginField] = $labelTranslation;
        }

        return $pluginData;
    }

    private function getLanguageIdForLocale(string $locale, Context $context): string
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('language.translationCode.code', $locale));
        $result = $this->languageRepo->search($criteria, $context);

        if ($result->getTotal() === 0) {
            return '';
        }

        /** @var LanguageEntity $languageEntity */
        $languageEntity = $result->getEntities()->first();

        return $languageEntity->getId();
    }

    private function updatePlugin(array $pluginData, Context $context): void
    {
        $this->pluginRepo->update([$pluginData], $context);
    }
}
