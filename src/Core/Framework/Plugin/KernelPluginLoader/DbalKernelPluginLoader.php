<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Plugin\KernelPluginLoader;

use Composer\Autoload\ClassLoader;
use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;

#[Package('core')]
class DbalKernelPluginLoader extends KernelPluginLoader
{
    public function __construct(
        ClassLoader $classLoader,
        ?string $pluginDir,
        private readonly Connection $connection
    ) {
        parent::__construct($classLoader, $pluginDir);
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

        $plugins = $this->connection->executeQuery($sql)->fetchAllAssociative();
        foreach ($plugins as $i => $plugin) {
            $plugins[$i]['active'] = (bool) $plugin['active'];
            $plugins[$i]['managedByComposer'] = (bool) $plugin['managedByComposer'];
            $plugins[$i]['autoload'] = json_decode((string) $plugin['autoload'], true, 512, \JSON_THROW_ON_ERROR);
        }

        $this->pluginInfos = $plugins;
    }
}
