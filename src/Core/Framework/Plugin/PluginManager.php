<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Plugin;

use Doctrine\DBAL\Connection;
use Psr\Container\ContainerInterface;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Framework;
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
use Shopware\Core\Framework\SourceContext;
use Shopware\Core\Kernel;
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

    public function __construct(
        string $pluginPath,
        Kernel $kernel,
        Connection $connection,
        ContainerInterface $container,
        RequirementValidator $requirementValidator,
        MigrationCollectionLoader $migrationLoader,
        MigrationRuntime $migrationRunner
    ) {
        $this->pluginPath = $pluginPath;
        $this->kernel = $kernel;
        $this->connection = $connection;
        $this->container = $container;
        $this->requirementValidator = $requirementValidator;
        $this->migrationLoader = $migrationLoader;
        $this->migrationRunner = $migrationRunner;
    }

    /**
     * @param string $pluginName
     *
     * @throws PluginNotFoundException
     *
     * @return PluginStruct
     */
    public function getPluginByName(string $pluginName): PluginStruct
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

    public function installPlugin(PluginStruct $plugin, string $tenantId): InstallContext
    {
        $pluginBootstrap = $this->getPluginBootstrap($plugin->getName());

        // set container because the plugin has not been initialized yet and therefore has no container set
        $pluginBootstrap->setContainer($this->container);

        $context = new InstallContext($pluginBootstrap, $this->createContext($tenantId), Framework::VERSION, $plugin->getVersion());

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

        $plugin->setInstallationDate(new \DateTime());
        $plugin->setUpdateDate(new \DateTime());

        $updates = [];
        $updates['installation_date'] = $plugin->getInstallationDate()->format(Defaults::DATE_FORMAT);
        $updates['update_date'] = $plugin->getUpdateDate()->format(Defaults::DATE_FORMAT);

        $this->connection->update('plugin', $updates, ['name' => $plugin->getName()]);

        $pluginBootstrap->postInstall($context);

        return $context;
    }

    public function uninstallPlugin(PluginStruct $plugin, string $tenantId, bool $removeUserData = true): UninstallContext
    {
        $pluginBootstrap = $this->getPluginBootstrap($plugin->getName());

        // set container because the plugin has not been initialized yet and therefore has no container set
        $pluginBootstrap->setContainer($this->container);

        $context = new UninstallContext($pluginBootstrap, $this->createContext($tenantId), Framework::VERSION, $plugin->getVersion(), !$removeUserData);

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

    public function updatePlugin(PluginStruct $plugin, string $tenantId): UpdateContext
    {
        $pluginBootstrap = $this->getPluginBootstrap($plugin->getName());
        $this->requirementValidator->validate($pluginBootstrap->getPath() . '/plugin.xml', Framework::VERSION, $this->getPlugins());

        $context = new UpdateContext(
            $pluginBootstrap,
            $this->createContext($tenantId),
            Framework::VERSION,
            $plugin->getVersion(),
            $plugin->getUpdateVersion() ?? $plugin->getVersion()
        );

        $pluginBootstrap->update($context);

        $this->runMigrations($pluginBootstrap);

        $plugin->setVersion($context->getUpdateVersion());
        $plugin->setUpdateVersion(null);
        $plugin->setUpdateSource(null);
        $plugin->setUpdateDate(new \DateTime());

        $updates = [
            'version' => $context->getUpdateVersion(),
            'update_version' => null,
            'update_source' => null,
            'update_date' => $plugin->getUpdateDate()->format(Defaults::DATE_FORMAT),
        ];

        $this->connection->update('plugin', $updates, ['name' => $plugin->getName()]);

        $pluginBootstrap->postUpdate($context);

        return $context;
    }

    public function activatePlugin(PluginStruct $plugin, string $tenantId): ActivateContext
    {
        $pluginBootstrap = $this->getPluginBootstrap($plugin->getName());
        $context = new ActivateContext($pluginBootstrap, $this->createContext($tenantId), Framework::VERSION, $plugin->getVersion());

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

    public function deactivatePlugin(PluginStruct $plugin, string $tenantId): DeactivateContext
    {
        $pluginBootstrap = $this->getPluginBootstrap($plugin->getName());
        $context = new DeactivateContext($pluginBootstrap, $this->createContext($tenantId), Framework::VERSION, $plugin->getVersion());

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
        $refreshDate = new \DateTime();

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
                'changes' => array_key_exists('changelog', $info) ? json_encode($info['changelog']) : null,
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
                $data['id'] = $pluginName;
                $data['created_at'] = $refreshDate->format(Defaults::DATE_FORMAT);
                $data['active'] = 0;

                $this->connection->insert('plugin', $data);
            }
        }
    }

    /**
     * @return PluginStruct[]
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
            $xmlConfigReader = new XmlPluginInfoReader();
            $info = $xmlConfigReader->read($pluginInfoPath);
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

    private function hydrate(array $databasePlugin): PluginStruct
    {
        $plugin = new PluginStruct();

        $plugin->setId($databasePlugin['name']);
        $plugin->setName($databasePlugin['name']);
        $plugin->setLabel($databasePlugin['label']);
        $plugin->setDescription($databasePlugin['description']);
        $plugin->setDescriptionLong($databasePlugin['description_long']);
        $plugin->setActive((bool) $databasePlugin['active']);
        $plugin->setCreatedAt(new \DateTime($databasePlugin['created_at']));
        $plugin->setInstallationDate(
            $databasePlugin['installation_date'] ? new \DateTime($databasePlugin['installation_date']) : null
        );
        $plugin->setUpdateDate($databasePlugin['update_date'] ? new \DateTime($databasePlugin['update_date']) : null);
        $plugin->setRefreshDate(
            $databasePlugin['refresh_date'] ? new \DateTime($databasePlugin['refresh_date']) : null
        );
        $plugin->setAuthor($databasePlugin['author']);
        $plugin->setCopyright($databasePlugin['copyright']);
        $plugin->setLicense($databasePlugin['license']);
        $plugin->setVersion($databasePlugin['version']);
        $plugin->setSupport($databasePlugin['support']);
        $plugin->setChanges($databasePlugin['changes']);
        $plugin->setLink($databasePlugin['link']);
        $plugin->setStoreVersion($databasePlugin['store_version']);
        $plugin->setStoreDate($databasePlugin['store_date'] ? new \DateTime($databasePlugin['store_date']) : null);
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

    private function createContext(string $tenantId): Context
    {
        return new Context(
            $tenantId,
            new SourceContext(),
            null, [],
            Defaults::CURRENCY,
            Defaults::LANGUAGE,
            Defaults::LANGUAGE
        );
    }

    private function runMigrations(Plugin $pluginBootstrap): void
    {
        $migrationPath = $pluginBootstrap->getPath() . str_replace($pluginBootstrap->getNamespace(), '', str_replace('\\', '/', $pluginBootstrap->getMigrationNamespace()));

        if (!is_dir($migrationPath)) {
            return;
        }

        $this->migrationLoader->addDirectory($migrationPath, $pluginBootstrap->getMigrationNamespace());
        $this->migrationLoader->syncMigrationCollection();
        iterator_to_array($this->migrationRunner->migrate());
    }

    private function removeMigrations(Plugin $pluginBootstrap): void
    {
        $class = $pluginBootstrap->getMigrationNamespace() . '\%';
        $class = str_replace('\\', '\\\\', $class);

        $this->connection->executeQuery('DELETE FROM migration WHERE class LIKE :class', ['class' => $class]);
    }
}
