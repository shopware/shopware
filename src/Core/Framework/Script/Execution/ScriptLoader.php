<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Script\Execution;

use Doctrine\DBAL\Connection;
use Shopware\Core\DevOps\Environment\EnvironmentHelper;
use Shopware\Core\Framework\Adapter\Cache\CacheCompressor;
use Shopware\Core\Framework\App\Lifecycle\Persister\ScriptPersister;
use Shopware\Core\Framework\DataAbstractionLayer\Doctrine\FetchModeHelper;
use Symfony\Component\Cache\Adapter\TagAwareAdapterInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Twig\Cache\FilesystemCache;

/**
 * @internal only for use by the app-system
 */
class ScriptLoader implements EventSubscriberInterface
{
    public const CACHE_KEY = 'shopware-app-scripts';

    private Connection $connection;

    private string $cacheDir;

    private ScriptPersister $scriptPersister;

    private bool $debug;

    private TagAwareAdapterInterface $cache;

    public function __construct(Connection $connection, ScriptPersister $scriptPersister, TagAwareAdapterInterface $cache, string $cacheDir, bool $debug)
    {
        $this->connection = $connection;
        $this->cacheDir = $cacheDir . '/twig/scripts';
        $this->scriptPersister = $scriptPersister;
        $this->debug = $debug;
        $this->cache = $cache;
    }

    public static function getSubscribedEvents(): array
    {
        return ['script.written' => 'invalidateCache'];
    }

    /**
     * @return Script[]
     */
    public function get(string $hook): array
    {
        $cacheItem = $this->cache->getItem(self::CACHE_KEY);
        if ($cacheItem->isHit() && $cacheItem->get() && !$this->debug) {
            return CacheCompressor::uncompress($cacheItem)[$hook] ?? [];
        }

        $scripts = $this->load();

        $cacheItem = CacheCompressor::compress($cacheItem, $scripts);
        $this->cache->save($cacheItem);

        return $scripts[$hook] ?? [];
    }

    public function invalidateCache(): void
    {
        $this->cache->deleteItem(self::CACHE_KEY);
    }

    private function load(): array
    {
        if ($this->debug) {
            $this->scriptPersister->refresh();
        }

        $scripts = $this->connection->fetchAllAssociative("
            SELECT LOWER(HEX(`script`.`app_id`)) as `app_id`,
                   `script`.`name` AS scriptName,
                   `script`.`script` AS script,
                   `script`.`hook` AS hook,
                   IFNULL(`script`.`updated_at`, `script`.`created_at`) AS lastModified,
                   `app`.`name` AS appName,
                   LOWER(HEX(`app`.`integration_id`)) AS integrationId,
                   `app`.`version` AS appVersion,
                   `script`.`active` AS active
            FROM `script`
            LEFT JOIN `app` ON `script`.`app_id` = `app`.`id`
            WHERE `script`.`hook` != 'include'
            ORDER BY `app`.`created_at`, `app`.`id`, `script`.`name`
        ");

        $includes = $this->connection->fetchAllAssociative("
            SELECT LOWER(HEX(`script`.`app_id`)) as `app_id`,
                   `script`.`name` AS name,
                   `script`.`script` AS script,
                   `app`.`name` AS appName,
                   LOWER(HEX(`app`.`integration_id`)) AS integrationId,
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
                $script['app_id'] = $appId;

                return new Script(
                    $script['name'],
                    $script['script'],
                    new \DateTimeImmutable($script['lastModified']),
                    $this->getAppInfo($script)
                );
            }, $includes);

            $options = [];
            if (!$this->debug) {
                $options['cache'] = new FilesystemCache($this->cacheDir . '/' . $cachePrefix);
            } else {
                $options['debug'] = true;
            }

            $executableScripts[$script['hook']][] = new Script(
                $script['scriptName'],
                $script['script'],
                $lastModified,
                $this->getAppInfo($script),
                $options,
                $includes,
                (bool) $script['active']
            );
        }

        return $executableScripts;
    }

    private function getAppInfo(array $script): ?ScriptAppInformation
    {
        if (!$script['app_id'] || !$script['appName'] || !$script['integrationId']) {
            return null;
        }

        return new ScriptAppInformation(
            $script['app_id'],
            $script['appName'],
            $script['integrationId']
        );
    }
}
