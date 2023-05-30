<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Script\Execution;

use Doctrine\DBAL\Connection;
use Shopware\Core\DevOps\Environment\EnvironmentHelper;
use Shopware\Core\Framework\Adapter\Cache\CacheCompressor;
use Shopware\Core\Framework\App\Lifecycle\Persister\ScriptPersister;
use Shopware\Core\Framework\DataAbstractionLayer\Doctrine\FetchModeHelper;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Cache\Adapter\TagAwareAdapterInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Twig\Cache\FilesystemCache;

/**
 * @internal only for use by the app-system
 *
 * @phpstan-type ScriptInfo = array{app_id: ?string, scriptName: string, script: string, hook: string, appName: ?string, integrationId: ?string, lastModified: string, appVersion: string, active: bool}
 * @phpstan-type IncludesInfo = array{app_id: ?string, name: string, script: string, appName: ?string, integrationId: ?string, lastModified: string}
 */
#[Package('core')]
class ScriptLoader implements EventSubscriberInterface
{
    final public const CACHE_KEY = 'shopware-app-scripts';

    private readonly string $cacheDir;

    public function __construct(
        private readonly Connection $connection,
        private readonly ScriptPersister $scriptPersister,
        private readonly TagAwareAdapterInterface $cache,
        string $cacheDir,
        private readonly bool $debug
    ) {
        $this->cacheDir = $cacheDir . '/scripts';
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

    /**
     * @return array<string, list<Script>>
     */
    private function load(): array
    {
        if ($this->debug) {
            $this->scriptPersister->refresh();
        }

        /** @var list<ScriptInfo> $scripts */
        $scripts = $this->connection->fetchAllAssociative('
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
            WHERE `script`.`hook` != \'include\'
            ORDER BY `app`.`created_at`, `app`.`id`, `script`.`name`
        ');

        $includes = $this->connection->fetchAllAssociative('
            SELECT LOWER(HEX(`script`.`app_id`)) as `app_id`,
                   `script`.`name` AS name,
                   `script`.`script` AS script,
                   `app`.`name` AS appName,
                   LOWER(HEX(`app`.`integration_id`)) AS integrationId,
                   IFNULL(`script`.`updated_at`, `script`.`created_at`) AS lastModified
            FROM `script`
            LEFT JOIN `app` ON `script`.`app_id` = `app`.`id`
            WHERE `script`.`hook` = \'include\'
            ORDER BY `app`.`created_at`, `app`.`id`, `script`.`name`
        ');

        /** @var array<string, list<IncludesInfo>> $allIncludes */
        $allIncludes = FetchModeHelper::group($includes);

        $executableScripts = [];
        foreach ($scripts as $script) {
            $appId = $script['app_id'];

            $includes = $allIncludes[$appId] ?? [];

            $dates = [...[$script['lastModified']], ...array_column($includes, 'lastModified')];

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

    /**
     * @param ScriptInfo|IncludesInfo $script
     */
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
