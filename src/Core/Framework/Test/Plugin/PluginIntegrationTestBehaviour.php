<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Plugin;

use Composer\Autoload\ClassLoader;
use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Plugin\PluginEntity;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Kernel;

trait PluginIntegrationTestBehaviour
{
    /**
     * @var ClassLoader
     */
    protected $classLoader;

    /**
     * @var Connection
     */
    protected $connection;

    /**
     * @before
     */
    public function pluginIntegrationSetUp(): void
    {
        $this->connection = Kernel::getConnection();
        $this->connection->beginTransaction();
        $this->connection->exec('DELETE FROM plugin');

        $this->classLoader = clone KernelLifecycleManager::getClassLoader();
        KernelLifecycleManager::getClassLoader()->unregister();
        $this->classLoader->register();
    }

    /**
     * @after
     */
    public function pluginIntegrationTearDown(): void
    {
        $this->classLoader->unregister();
        KernelLifecycleManager::getClassLoader()->register();

        $this->connection->rollBack();
    }

    protected function insertPlugin(PluginEntity $plugin): void
    {
        $data = [
            'id' => Uuid::fromHexToBytes($plugin->getId()),
            'name' => $plugin->getName(),
            'version' => $plugin->getVersion(),
            'active' => $plugin->getActive() ? '1' : '0',
            'managed_by_composer' => $plugin->getManagedByComposer() ? '1' : '0',
            'base_class' => $plugin->getBaseClass(),
            'path' => $plugin->getPath(),
            'autoload' => json_encode($plugin->getAutoload()),
            'created_at' => $plugin->getCreatedAt()->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            'installed_at' => $plugin->getInstalledAt() ? $plugin->getInstalledAt()->format(Defaults::STORAGE_DATE_TIME_FORMAT) : null,
        ];

        $this->connection->insert('plugin', $data);
    }

    protected function getNotInstalledPlugin(): PluginEntity
    {
        $plugin = new PluginEntity();
        $plugin->assign([
            'id' => Uuid::randomHex(),
            'name' => 'SwagTest',
            'baseClass' => 'SwagTest\\SwagTest',
            'version' => '1.0.1',
            'active' => false,
            'path' => __DIR__ . '/_fixture/plugins/SwagTest',
            'autoload' => ['psr-4' => ['SwagTest\\' => 'src/']],
            'createdAt' => new \DateTimeImmutable('2019-01-01'),
            'managedByComposer' => false,
        ]);

        return $plugin;
    }

    protected function getInstalledInactivePlugin(): PluginEntity
    {
        $installed = $this->getNotInstalledPlugin();
        $installed->setInstalledAt(new \DateTimeImmutable());

        return $installed;
    }

    protected function getInstalledInactivePluginRebuildDisabled(): PluginEntity
    {
        $plugin = new PluginEntity();
        $plugin->assign([
            'id' => Uuid::randomHex(),
            'name' => 'SwagTestSkipRebuild',
            'baseClass' => 'SwagTestSkipRebuild\\SwagTestSkipRebuild',
            'version' => '1.0.1',
            'active' => false,
            'path' => __DIR__ . '/_fixture/plugins/SwagTestSkipRebuild',
            'autoload' => ['psr-4' => ['SwagTestSkipRebuild\\' => 'src/']],
            'createdAt' => new \DateTimeImmutable('2019-01-01'),
            'managedByComposer' => false,
        ]);
        $plugin->setInstalledAt(new \DateTimeImmutable());

        return $plugin;
    }

    protected function getActivePlugin(): PluginEntity
    {
        $active = $this->getInstalledInactivePlugin();
        $active->setActive(true);

        return $active;
    }

    protected function getActivePluginWithBundle(): PluginEntity
    {
        $plugin = new PluginEntity();
        $plugin->assign([
            'id' => Uuid::randomHex(),
            'name' => 'SwagTestWithBundle',
            'baseClass' => 'SwagTestWithBundle\\SwagTestWithBundle',
            'version' => '1.0.0',
            'active' => false,
            'path' => __DIR__ . '/_fixture/plugins/SwagTestWithBundle',
            'autoload' => ['psr-4' => ['SwagTestWithBundle\\' => 'src/']],
            'createdAt' => new \DateTimeImmutable('2019-01-01'),
            'managedByComposer' => false,
        ]);

        $plugin->setInstalledAt(new \DateTimeImmutable());
        $plugin->setActive(true);

        return $plugin;
    }
}
