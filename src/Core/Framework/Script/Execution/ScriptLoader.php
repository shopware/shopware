<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Script\Execution;

use Doctrine\DBAL\Connection;
use Shopware\Core\DevOps\Environment\EnvironmentHelper;
use Shopware\Core\Framework\App\Lifecycle\Persister\ScriptPersister;
use Shopware\Core\Framework\DataAbstractionLayer\Doctrine\FetchModeHelper;
use Twig\Cache\FilesystemCache;

/**
 * @internal only for use by the app-system
 */
class ScriptLoader
{
    private Connection $connection;

    private string $cacheDir;

    private ScriptPersister $scriptPersister;

    private string $appEnv;

    private ?array $scripts = null;

    public function __construct(Connection $connection, ScriptPersister $scriptPersister, string $cacheDir, string $appEnv)
    {
        $this->connection = $connection;
        $this->cacheDir = $cacheDir . '/twig/scripts';
        $this->scriptPersister = $scriptPersister;
        $this->appEnv = $appEnv;
    }

    /**
     * @return Script[]
     */
    public function get(string $hook): array
    {
        if ($this->scripts === null) {
            $this->scripts = $this->load();
        }

        return $this->scripts[$hook] ?? [];
    }

    private function load(): array
    {
        if ($this->appEnv === 'dev') {
            $this->scriptPersister->refresh();
        }

        $scripts = $this->connection->fetchAllAssociative("
            SELECT LOWER(HEX(`script`.`app_id`)) as `app_id`,
                   `script`.`name` AS scriptName,
                   `script`.`script` AS script,
                   `script`.`hook` AS hook,
                   IFNULL(`script`.`updated_at`, `script`.`created_at`) AS lastModified,
                   `app`.`name` AS appName,
                   `app`.`version` AS appVersion
            FROM `script`
            LEFT JOIN `app` ON `script`.`app_id` = `app`.`id`
            WHERE `script`.`hook` != 'include'
            ORDER BY `app`.`created_at`, `app`.`id`, `script`.`name`
        ");

        $includes = $this->connection->fetchAllAssociative("
            SELECT LOWER(HEX(`script`.`app_id`)) as `app_id`,
                   `script`.`name` AS name,
                   `script`.`script` AS script,
                   IFNULL(`script`.`updated_at`, `script`.`created_at`) AS lastModified
            FROM `script`
            LEFT JOIN `app` ON `script`.`app_id` = `app`.`id`
            WHERE `script`.`hook` = 'include'
            ORDER BY `app`.`created_at`, `app`.`id`, `script`.`name`
        ");

        $allIncludes = FetchModeHelper::group($includes);

        $executableScripts = [];
        /** @var array $script */
        foreach ($scripts as $script) {
            $appId = $script['app_id'];

            $includes = $allIncludes[$appId] ?? [];

            $dates = array_merge([$script['lastModified']], array_column($includes, 'lastModified'));

            /** @var \DateTimeInterface $lastModified */
            $lastModified = new \DateTimeImmutable(max($dates));

            /** @var string $cachePrefix */
            $cachePrefix = $script['appName'] ? md5($script['appName'] . $script['appVersion']) : EnvironmentHelper::getVariable('INSTANCE_ID', '');

            $includes = array_map(function (array $script) use ($appId) {
                return new Script(
                    $script['name'],
                    $script['script'],
                    new \DateTimeImmutable($script['lastModified']),
                    $appId
                );
            }, $includes);

            $options = [];
            if ($this->appEnv === 'prod') {
                $options['cache'] = new FilesystemCache($this->cacheDir . '/' . $cachePrefix);
            } else {
                $options['debug'] = true;
            }

            $executableScripts[$script['hook']][] = new Script(
                $script['scriptName'],
                $script['script'],
                $lastModified,
                $appId,
                $options,
                $includes
            );
        }

        return $executableScripts;
    }
}
