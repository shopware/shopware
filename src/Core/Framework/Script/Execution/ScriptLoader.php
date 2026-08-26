<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Script\Execution;

use Doctrine\DBAL\Connection;
use Shopware\Core\DevOps\Environment\EnvironmentHelper;
use Shopware\Core\Framework\Adapter\Cache\CacheCompressor;
use Shopware\Core\Framework\App\Lifecycle\Handler\ScriptLifecycleHandler;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\Hasher;
use Symfony\Component\Cache\Adapter\TagAwareAdapterInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Contracts\Service\ResetInterface;
use Twig\Cache\FilesystemCache;

/**
 * @internal only for use by the app-system
 *
 * @phpstan-type ScriptInfo = array{app_id: ?string, scriptName: string, script: string, hook: string, appName: ?string, appVersion: ?string, integrationId: ?string, lastModified: string, active: string}
 */
#[Package('framework')]
class ScriptLoader implements EventSubscriberInterface, ResetInterface
{
    final public const CACHE_KEY = 'shopware-executable-app-scripts';

    private readonly string $cacheDir;

    /**
     * @var array<string, list<Script>>|null
     */
    private ?array $scripts = null;

    public function __construct(
        private readonly Connection $connection,
        private readonly ScriptLifecycleHandler $scriptPersister,
        private readonly TagAwareAdapterInterface $cache,
        string $cacheDir,
        private readonly bool $debug
    ) {
        $this->cacheDir = $cacheDir . '/scripts';
    }

    public static function getSubscribedEvents(): array
    {
        return [
            'script.written' => 'invalidateCache',
            'app.written' => 'invalidateCache',
        ];
    }

    /**
     * @return list<Script>
     */
    public function get(string $hook): array
    {
        $hookScripts = $this->getScripts()[$hook] ?? [];

        foreach ($hookScripts as $script) {
            $info = $script->getScriptAppInformation();
            $cachePrefix = $info ? Hasher::hash($info->getAppName() . $info->getAppVersion()) : EnvironmentHelper::getVariable('INSTANCE_ID', '');

            $twigOptions = [];
            if (!$this->debug) {
                $twigOptions['cache'] = new FilesystemCache($this->cacheDir . '/' . $cachePrefix);
            } else {
                $twigOptions['debug'] = true;
            }

            $script->setTwigOptions($twigOptions);
        }

        return $hookScripts;
    }

    public function invalidateCache(): void
    {
        $this->reset();

        $this->cache->deleteItem(self::CACHE_KEY);
    }

    public function reset(): void
    {
        $this->scripts = null;
    }

    /**
     * @return array<string, list<Script>>
     */
    private function getScripts(): array
    {
        if ($this->scripts !== null) {
            return $this->scripts;
        }

        $cacheItem = $this->cache->getItem(self::CACHE_KEY);
        if ($cacheItem->isHit() && $cacheItem->get()) {
            /** @var array<string, list<Script>> $scripts */
            $scripts = CacheCompressor::uncompress($cacheItem);

            return $this->scripts = $scripts;
        }

        $scripts = $this->load();

        $cacheItem = CacheCompressor::compress($cacheItem, $scripts);
        $this->cache->save($cacheItem);

        return $this->scripts = $scripts;
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
                   `app`.`name` AS appName,
                   `app`.`version` AS appVersion,
                   LOWER(HEX(`app`.`integration_id`)) AS integrationId,
                   IFNULL(`script`.`updated_at`, `script`.`created_at`) AS lastModified,
                   IF(`script`.`active` = 1 AND (`app`.id IS NULL OR `app`.`active` = 1), 1, 0) AS active
            FROM `script`
            LEFT JOIN `app` ON `script`.`app_id` = `app`.`id`
            ORDER BY `app`.`created_at`, `app`.`id`, `script`.`name`
        ');

        $executableScripts = [];
        $appIncludes = [];

        foreach ($scripts as $script) {
            if ($script['hook'] === 'include') {
                continue;
            }

            $scriptByAppId = $script['app_id'] ?? '';
            if ($scriptByAppId === '') {
                continue;
            }
            if (!isset($appIncludes[$scriptByAppId])) {
                $includes = array_filter($scripts, static fn (array $include): bool => $include['hook'] === 'include' && $include['app_id'] === $scriptByAppId);

                $appIncludes[$scriptByAppId] = array_map(function (array $include): Script {
                    return new Script(
                        $include['scriptName'],
                        $include['script'],
                        new \DateTimeImmutable($include['lastModified']),
                        $this->getAppInfo($include),
                        [],
                        (bool) $include['active'],
                    );
                }, $includes);
            }

            $includes = $appIncludes[$scriptByAppId];

            $dates = [...[new \DateTimeImmutable($script['lastModified'])], ...array_column($includes, 'lastModified')];

            $executableScripts[$script['hook']][] = new Script(
                $script['scriptName'],
                $script['script'],
                max($dates),
                $this->getAppInfo($script),
                $includes,
                (bool) $script['active'],
            );
        }

        return $executableScripts;
    }

    /**
     * @param ScriptInfo $script
     */
    private function getAppInfo(array $script): ?ScriptAppInformation
    {
        if (!$script['app_id'] || !$script['appName'] || !$script['appVersion'] || !$script['integrationId']) {
            return null;
        }

        return new ScriptAppInformation(
            $script['app_id'],
            $script['appName'],
            $script['appVersion'],
            $script['integrationId'],
        );
    }
}
