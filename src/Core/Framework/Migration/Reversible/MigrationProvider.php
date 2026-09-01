<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Migration\Reversible;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationException;
use Shopware\Core\Framework\Plugin;

/**
 * @internal
 */
#[Package('framework')]
class MigrationProvider
{
    /**
     * @var \WeakMap<Plugin, list<Migration>>
     */
    private \WeakMap $cache;

    public function __construct()
    {
        $this->cache = new \WeakMap();
    }

    /**
     * @return list<Migration> sorted by creation timestamp, ascending
     */
    public function forPlugin(Plugin $plugin): array
    {
        if (isset($this->cache[$plugin])) {
            return $this->cache[$plugin];
        }

        $directory = $plugin->getMigrationPath();
        if (!is_dir($directory)) {
            return $this->cache[$plugin] = [];
        }

        $files = scandir($directory, \SCANDIR_SORT_ASCENDING);
        if ($files === false) {
            throw MigrationException::migrationDirectoryNotReadable($directory);
        }

        $migrations = [];
        $timestamps = [];
        foreach ($files as $file) {
            $path = $directory . \DIRECTORY_SEPARATOR . $file;
            if (pathinfo($path, \PATHINFO_EXTENSION) !== 'php') {
                continue;
            }

            $class = $plugin->getMigrationNamespace() . '\\' . pathinfo($file, \PATHINFO_FILENAME);
            if (!class_exists($class) && !interface_exists($class) && !trait_exists($class)) {
                throw MigrationException::invalidMigrationClass($class, $path);
            }

            // legacy MigrationStep classes live in the same directory and are handled by MigrationCollection
            if (!is_subclass_of($class, Migration::class)) {
                continue;
            }

            $reflection = new \ReflectionClass($class);
            $constructor = $reflection->getConstructor();
            if (!$reflection->isInstantiable() || ($constructor !== null && $constructor->getNumberOfRequiredParameters() > 0)) {
                throw MigrationException::reversibleMigrationNotInstantiable($class);
            }

            $migration = $reflection->newInstance();
            \assert($migration instanceof Migration);

            $timestamp = $migration->getCreationTimestamp();
            if ($timestamp <= 0 || $timestamp > 2147483647) {
                throw MigrationException::reversibleMigrationInvalidTimestamp($class, $timestamp);
            }
            if (isset($timestamps[$timestamp])) {
                throw MigrationException::duplicateMigrationTimestamp($plugin->getName(), $timestamp, $timestamps[$timestamp], $class);
            }

            $timestamps[$timestamp] = $class;
            $migrations[] = $migration;
        }

        usort($migrations, static fn (Migration $first, Migration $second): int => $first->getCreationTimestamp() <=> $second->getCreationTimestamp());

        return $this->cache[$plugin] = $migrations;
    }
}
