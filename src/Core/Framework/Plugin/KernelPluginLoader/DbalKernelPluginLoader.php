<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Plugin\KernelPluginLoader;

use Composer\Autoload\ClassLoader;
use Doctrine\DBAL\Connection;

class DbalKernelPluginLoader extends KernelPluginLoader
{
    /**
     * @var Connection
     */
    private $connection;

    public function __construct(ClassLoader $classLoader, ?string $pluginDir, Connection $connection)
    {
        parent::__construct($classLoader, $pluginDir);

        $this->connection = $connection;
    }

    protected function loadPluginInfos(): void
    {
        $sql = <<<'SQL'
        # dbal-plugin-loader
        SELECT
               `name`,
               `base_class` AS baseClass,
               IF(`active` = 1 AND `installed_at` IS NOT NULL, 1, 0) AS active,
               `path`,
               `version`,
               `autoload`,
               `managed_by_composer` AS managedByComposer,
               composer_name as composerName
        FROM `plugin`
        ORDER BY `installed_at`;
SQL;

        $plugins = $this->connection->executeQuery($sql)->fetchAll();
        foreach ($plugins as $i => $plugin) {
            $plugins[$i]['active'] = (bool) $plugin['active'];
            $plugins[$i]['managedByComposer'] = (bool) $plugin['managedByComposer'];
            $plugins[$i]['autoload'] = json_decode($plugin['autoload'], true);
        }

        $this->pluginInfos = $plugins;
    }
}
