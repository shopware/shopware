<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Plugin;

use DateTime;
use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\JsonFieldSerializer;
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
use Shopware\Core\Framework\Struct\Uuid;
use Shopware\Core\Kernel;
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
     * @var RequirementValidator
     */
    private $requirementValidator;

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

    public function __construct(
        string $pluginPath,
        Kernel $kernel,
        Connection $connection,
        ContainerInterface $container,
        RequirementValidator $requirementValidator,
        MigrationCollectionLoader $migrationLoader,
        MigrationCollection $migrationCollection,
        MigrationRuntime $migrationRunner
    ) {
        $this->pluginPath = $pluginPath;
        $this->kernel = $kernel;
        $this->connection = $connection;
        $this->container = $container;
        $this->requirementValidator = $requirementValidator;
        $this->migrationLoader = $migrationLoader;
        $this->migrationCollection = $migrationCollection;
        $this->migrationRunner = $migrationRunner;
    }

    /**
     * @param string $pluginName
     *
     * @throws PluginNotFoundException
     *
     * @return PluginEntity
     */
    public function getPluginByName(string $pluginName): PluginEntity
    {
        $builder = $this->connection->createQueryBuilder();
        $plugin = $builder->select('*')
            ->from('plugin')
            ->where('name = :pluginName')
            ->setParameter('pluginName', $pluginName)
            ->execute()
            ->fetch();

        if ($plugin === false) {
            throw new PluginNotFoundException($pluginName);
        }

        return $this->hydrate($plugin);
    }

    public function installPlugin(PluginEntity $plugin): InstallContext
    {
        /** @var Plugin|ContainerAwareTrait $pluginBootstrap */
        $pluginBootstrap = $this->getPluginBootstrap($plugin->getName());
        // set container because the plugin has not been initialized yet and therefore has no container set
        $pluginBootstrap->setContainer($this->container);

        $context = new InstallContext($pluginBootstrap, $this->createContext(), Framework::VERSION, $plugin->getVersion());

        if ($plugin->getInstallationDate()) {
            return $context;
        }

        $this->requirementValidator->validate($pluginBootstrap->getPath() . '/plugin.xml', Framework::VERSION, $this->getPlugins());

        // Makes sure the version is updated in the db after a re-installation
        if ($this->hasInfoNewerVersion((string) $plugin->getUpdateVersion(), $plugin->getVersion())) {
            $plugin->setVersion((string) $plugin->getUpdateVersion());
        }

        $pluginBootstrap->install($context);

        $this->runMigrations($pluginBootstrap);

        $plugin->setInstallationDate(new DateTime());
        $plugin->setUpdateDate(new DateTime());

        $updates = [];
        $updates['installation_date'] = $plugin->getInstallationDate()->format(Defaults::DATE_FORMAT);

        $updateDate = $plugin->getUpdateDate();
        if ($updateDate !== null) {
            $updates['update_date'] = $updateDate->format(Defaults::DATE_FORMAT);
        }

        $this->connection->update('plugin', $updates, ['name' => $plugin->getName()]);

        $pluginBootstrap->postInstall($context);

        return $context;
    }

    public function uninstallPlugin(PluginEntity $plugin, bool $removeUserData = true): UninstallContext
    {
        /** @var Plugin|ContainerAwareTrait $pluginBootstrap */
        $pluginBootstrap = $this->getPluginBootstrap($plugin->getName());
        // set container because the plugin has not been initialized yet and therefore has no container set
        $pluginBootstrap->setContainer($this->container);

        $context = new UninstallContext($pluginBootstrap, $this->createContext(), Framework::VERSION, $plugin->getVersion(), !$removeUserData);

        if ($plugin->getInstallationDate() === null) {
            throw new PluginNotInstalledException($plugin->getName());
        }

        $pluginBootstrap->uninstall($context);

        $plugin->setInstallationDate(null);
        $plugin->setActive(false);

        if ($removeUserData) {
            $this->removeMigrations($pluginBootstrap);
        }

        $this->connection->update(
            'plugin',
            ['active' => 0, 'installation_date' => null],
            ['name' => $plugin->getName()]
        );

        return $context;
    }

    public function updatePlugin(PluginEntity $plugin): UpdateContext
    {
        /** @var Plugin|ContainerAwareTrait $pluginBootstrap */
        $pluginBootstrap = $this->getPluginBootstrap($plugin->getName());
        // set container because the plugin has not been initialized yet and therefore has no container set
        $pluginBootstrap->setContainer($this->container);

        $this->requirementValidator->validate($pluginBootstrap->getPath() . '/plugin.xml', Framework::VERSION, $this->getPlugins());

        $context = new UpdateContext(
            $pluginBootstrap,
            $this->createContext(),
            Framework::VERSION,
            $plugin->getVersion(),
            $plugin->getUpdateVersion() ?? $plugin->getVersion()
        );

        $pluginBootstrap->update($context);

        $this->runMigrations($pluginBootstrap);

        $plugin->setVersion($context->getUpdatePluginVersion());
        $plugin->setUpdateVersion(null);
        $plugin->setUpdateSource(null);
        $plugin->setUpdateDate(new DateTime());

        $updates = [
            'version' => $context->getUpdatePluginVersion(),
            'update_version' => null,
            'update_source' => null,
        ];

        $updateDate = $plugin->getUpdateDate();
        if ($updateDate !== null) {
            $updates['update_date'] = $updateDate->format(Defaults::DATE_FORMAT);
        }

        $this->connection->update('plugin', $updates, ['name' => $plugin->getName()]);

        $pluginBootstrap->postUpdate($context);

        return $context;
    }

    public function activatePlugin(PluginEntity $plugin): ActivateContext
    {
        /** @var Plugin|ContainerAwareTrait $pluginBootstrap */
        $pluginBootstrap = $this->getPluginBootstrap($plugin->getName());
        // set container because the plugin has not been initialized yet and therefore has no container set
        $pluginBootstrap->setContainer($this->container);

        $context = new ActivateContext($pluginBootstrap, $this->createContext(), Framework::VERSION, $plugin->getVersion());

        if ($plugin->getActive()) {
            return $context;
        }

        if ($plugin->getInstallationDate() === null) {
            throw new PluginNotInstalledException($plugin->getName());
        }

        $pluginBootstrap->activate($context);

        $this->connection->update(
            'plugin',
            ['active' => 1],
            ['name' => $plugin->getName()]
        );

        return $context;
    }

    public function deactivatePlugin(PluginEntity $plugin): DeactivateContext
    {
        /** @var Plugin|ContainerAwareTrait $pluginBootstrap */
        $pluginBootstrap = $this->getPluginBootstrap($plugin->getName());
        // set container because the plugin has not been initialized yet and therefore has no container set
        $pluginBootstrap->setContainer($this->container);

        $context = new DeactivateContext($pluginBootstrap, $this->createContext(), Framework::VERSION, $plugin->getVersion());

        if (!$plugin->getInstallationDate()) {
            throw new PluginNotInstalledException($plugin->getName());
        }

        if ($plugin->getActive() === false) {
            throw new PluginNotActivatedException($plugin->getName());
        }

        $pluginBootstrap->deactivate($context);

        $plugin->setActive(false);

        $this->connection->update(
            'plugin',
            ['active' => 0],
            ['name' => $plugin->getName()]
        );

        return $context;
    }

    public function updatePlugins(): void
    {
        $refreshDate = new DateTime();

        $finder = new Finder();
        $filesystemPlugins = $finder->directories()->depth(0)->in($this->pluginPath)->getIterator();

        $installedPlugins = $this->getPlugins();

        foreach ($filesystemPlugins as $plugin) {
            $pluginName = $plugin->getFilename();
            $pluginPath = $plugin->getPathname();

            $info = $this->parsePluginInfo($pluginPath);

            $info['label'] = $info['label']['en'] ?? $pluginName;
            $info['description'] = $info['description']['en'] ?? null;
            $info['version'] = $info['version'] ?? '1.0.0';
            $info['author'] = $info['author'] ?? null;
            $info['link'] = $info['link'] ?? null;

            $data = [
                'version' => $info['version'],
                'author' => $info['author'],
                'name' => $pluginName,
                'link' => $info['link'],
                'label' => $info['label'],
                'description' => $info['description'],
                'capability_update' => true,
                'capability_install' => true,
                'capability_enable' => true,
                'capability_secure_uninstall' => true,
                'refresh_date' => $refreshDate->format(Defaults::DATE_FORMAT),
                'changes' => array_key_exists('changelog', $info) ? JsonFieldSerializer::encodeJson($info['changelog']) : null,
            ];

            $currentPluginInfo = $installedPlugins[$pluginName] ?? null;
            if ($currentPluginInfo) {
                if ($this->hasInfoNewerVersion($info['version'], $currentPluginInfo->getVersion())) {
                    $data['version'] = $currentPluginInfo->getVersion();
                    $data['update_version'] = $info['version'];
                }

                $data['refresh_date'] = $refreshDate->format(Defaults::DATE_FORMAT);
                $this->connection->update('plugin', $data, ['name' => $pluginName]);
            } else {
                $data['id'] = Uuid::uuid4()->getBytes();
                $data['created_at'] = $refreshDate->format(Defaults::DATE_FORMAT);
                $data['active'] = 0;

                $this->connection->insert('plugin', $data);
            }
        }
    }

    /**
     * @return PluginEntity[]
     */
    public function getPlugins(): array
    {
        $builder = $this->connection->createQueryBuilder();
        $databasePlugins = $builder->select('*')->from('plugin')->execute()->fetchAll();

        $plugins = [];
        foreach ($databasePlugins as $databasePlugin) {
            $plugin = $this->hydrate($databasePlugin);
            $plugins[$plugin->getName()] = $plugin;
        }

        return $plugins;
    }

    private function parsePluginInfo(string $pluginPath): array
    {
        $pluginInfoPath = $pluginPath . '/plugin.xml';
        $info = [];

        if (is_file($pluginInfoPath)) {
            $info = (new XmlPluginInfoReader())->read($pluginInfoPath);
        }

        return $info;
    }

    /**
     * @param string $updateVersion
     * @param string $currentVersion
     *
     * @return bool
     */
    private function hasInfoNewerVersion(string $updateVersion, string $currentVersion): bool
    {
        return version_compare($updateVersion, $currentVersion, '>');
    }

    private function hydrate(array $databasePlugin): PluginEntity
    {
        $plugin = new PluginEntity();

        $plugin->setId($databasePlugin['name']);
        $plugin->setName($databasePlugin['name']);
        $plugin->setLabel($databasePlugin['label']);
        $plugin->setDescription($databasePlugin['description']);
        $plugin->setDescriptionLong($databasePlugin['description_long']);
        $plugin->setActive((bool) $databasePlugin['active']);
        $plugin->setCreatedAt(new DateTime($databasePlugin['created_at']));
        $plugin->setInstallationDate(
            $databasePlugin['installation_date'] ? new DateTime($databasePlugin['installation_date']) : null
        );
        $plugin->setUpdateDate($databasePlugin['update_date'] ? new DateTime($databasePlugin['update_date']) : null);
        $plugin->setRefreshDate(
            $databasePlugin['refresh_date'] ? new DateTime($databasePlugin['refresh_date']) : null
        );
        $plugin->setAuthor($databasePlugin['author']);
        $plugin->setCopyright($databasePlugin['copyright']);
        $plugin->setLicense($databasePlugin['license']);
        $plugin->setVersion($databasePlugin['version']);
        $plugin->setSupport($databasePlugin['support']);
        $plugin->setChanges($databasePlugin['changes']);
        $plugin->setLink($databasePlugin['link']);
        $plugin->setStoreVersion($databasePlugin['store_version']);
        $plugin->setStoreDate($databasePlugin['store_date'] ? new DateTime($databasePlugin['store_date']) : null);
        $plugin->setCapabilityUpdate((bool) $databasePlugin['capability_update']);
        $plugin->setCapabilityInstall((bool) $databasePlugin['capability_install']);
        $plugin->setCapabilityEnable((bool) $databasePlugin['capability_enable']);
        $plugin->setUpdateSource($databasePlugin['update_source']);
        $plugin->setUpdateVersion($databasePlugin['update_version']);

        return $plugin;
    }

    private function getPluginBootstrap(string $pluginName): Plugin
    {
        return $this->kernel::getPlugins()->get($pluginName);
    }

    private function createContext(): Context
    {
        return Context::createDefaultContext();
    }

    private function runMigrations(Plugin $pluginBootstrap): void
    {
        $migrationPath = $pluginBootstrap->getPath() . str_replace($pluginBootstrap->getNamespace(), '', str_replace('\\', '/', $pluginBootstrap->getMigrationNamespace()));

        if (!is_dir($migrationPath)) {
            return;
        }

        $this->migrationCollection->addDirectory($migrationPath, $pluginBootstrap->getMigrationNamespace());
        $this->migrationLoader->syncMigrationCollection($pluginBootstrap->getNamespace());
        iterator_to_array($this->migrationRunner->migrate());
    }

    private function removeMigrations(Plugin $pluginBootstrap): void
    {
        $class = $pluginBootstrap->getMigrationNamespace() . '\%';
        $class = str_replace('\\', '\\\\', $class);

        $this->connection->executeQuery('DELETE FROM migration WHERE class LIKE :class', ['class' => $class]);
    }
}
