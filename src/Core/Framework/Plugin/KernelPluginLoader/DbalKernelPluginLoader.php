<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Plugin\KernelPluginLoader;

use Composer\Autoload\ClassLoader;
use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;

/**
 * @phpstan-import-type PluginInfo from KernelPluginLoader
 */
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

        /** @var array<int, PluginInfo> $result */
        $result = [];

        /** @var array{baseClass: string, name: string, active: int, path: string, version: string|null, autoload: string, managedByComposer: int, composerName: string } $plugin */
        foreach ($plugins as $plugin) {
            $result[] = [
                'baseClass' => $plugin['baseClass'],
                'name' => $plugin['name'],
                'active' => (bool) $plugin['active'],
                'path' => $plugin['path'],
                'version' => $plugin['version'],
                'autoload' => json_decode((string) $plugin['autoload'], true, 512, \JSON_THROW_ON_ERROR),
                'managedByComposer' => (bool) $plugin['managedByComposer'],
                'composerName' => $plugin['composerName'],
            ];
        }

        $this->pluginInfos = $result;
    }
}
