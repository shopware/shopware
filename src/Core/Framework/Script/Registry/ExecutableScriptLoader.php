<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Script\Registry;

use Doctrine\DBAL\Connection;
use Shopware\Core\DevOps\Environment\EnvironmentHelper;
use Shopware\Core\Framework\App\Lifecycle\Persister\ScriptPersister;
use Shopware\Core\Framework\Script\ExecutableScript;
use Twig\Cache\FilesystemCache;

/**
 * @internal only for use by the app-system
 */
class ExecutableScriptLoader
{
    private Connection $connection;

    private string $cacheDir;

    private ScriptPersister $scriptPersister;

    private string $appEnv;

    public function __construct(Connection $connection, ScriptPersister $scriptPersister, string $cacheDir, string $appEnv)
    {
        $this->connection = $connection;
        $this->cacheDir = $cacheDir . '/twig/scripts';
        $this->scriptPersister = $scriptPersister;
        $this->appEnv = $appEnv;
    }

    public function loadExecutableScripts(): array
    {
        if ($this->appEnv === 'dev') {
            $this->scriptPersister->refresh();
        }

        $scripts = $this->connection->fetchAllAssociative('
            SELECT `script`.`name` AS scriptName, `script`.`script` AS script, `script`.`hook` AS hook, IFNULL(`script`.`updated_at`, `script`.`created_at`) AS lastModified, `app`.`name` AS appName, `app`.`version` AS appVersion
            FROM `script`
            LEFT JOIN `app` ON `script`.`app_id` = `app`.`id`
        ');

        $executableScripts = [];
        /** @var array $script */
        foreach ($scripts as $script) {
            /** @var \DateTimeInterface $lastModified */
            $lastModified = new \DateTimeImmutable($script['lastModified']);
            /** @var string $cachePrefix */
            $cachePrefix = $script['appName'] ? md5($script['appName'] . $script['appVersion']) : EnvironmentHelper::getVariable('INSTANCE_ID', '');
            $executableScripts[$script['hook']][] = new ExecutableScript(
                $script['scriptName'],
                $script['script'],
                $lastModified,
                [
                    'cache' => new FilesystemCache($this->cacheDir . '/' . $cachePrefix),
                ]
            );
        }

        return $executableScripts;
    }
}
