<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Migration\Reversible;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationException;
use Shopware\Core\Framework\Plugin;

/**
 * @final
 */
#[Package('framework')]
class MigrationRunner
{
    /**
     * @internal
     */
    public function __construct(
        private readonly MigrationProvider $provider,
        private readonly MigrationStateStore $stateStore,
        private readonly MigrationLock $lock,
        private readonly Connection $connection,
    ) {
    }

    /**
     * Applies all pending migrations of the plugin in ascending timestamp order.
     *
     * @return list<class-string<Migration>> the migrations applied by this call, in execution order
     */
    public function up(Plugin $plugin, bool $isInstallation = false): array
    {
        return $this->lock->synchronized($plugin->getName(), function () use ($plugin, $isInstallation): array {
            $migrations = $this->provider->forPlugin($plugin);
            $executed = $this->validatedExecutedMigrations($plugin, $migrations);

            $latestApplied = $executed === [] ? null : max(array_map(
                static fn (ExecutedMigration $migration): int => $migration->creationTimestamp,
                $executed
            ));
            $executedClasses = array_fill_keys(array_map(
                static fn (ExecutedMigration $migration): string => $migration->class,
                $executed
            ), true);

            $context = new UpMigrationContext($this->connection, $isInstallation);

            $applied = [];
            foreach ($migrations as $migration) {
                $class = $migration::class;
                if (isset($executedClasses[$class])) {
                    continue;
                }

                $timestamp = $migration->getCreationTimestamp();
                if ($latestApplied !== null && $timestamp <= $latestApplied) {
                    throw MigrationException::migrationOutOfOrder($plugin->getName(), $class, $timestamp, $latestApplied);
                }

                $migration->up($context);
                $this->stateStore->markExecuted($plugin->getName(), $class, $timestamp);
                $latestApplied = $timestamp;
                $applied[] = $class;
            }

            return $applied;
        });
    }

    /**
     * Rolls back all applied migrations of the plugin in descending timestamp order.
     *
     * Does nothing when user data is kept, so that schema and history survive for a reinstall.
     *
     * @return list<class-string<Migration>> the migrations rolled back by this call, in execution order
     */
    public function down(Plugin $plugin, bool $keepUserData = false): array
    {
        // keeping user data leaves schema and history intact, so a reinstall resumes from the same state
        if ($keepUserData) {
            return [];
        }

        return $this->lock->synchronized($plugin->getName(), function () use ($plugin, $keepUserData): array {
            $migrations = $this->provider->forPlugin($plugin);
            $executed = $this->validatedExecutedMigrations($plugin, $migrations);

            $byClass = [];
            foreach ($migrations as $migration) {
                $byClass[$migration::class] = $migration;
            }

            usort($executed, static fn (ExecutedMigration $first, ExecutedMigration $second): int => $second->creationTimestamp <=> $first->creationTimestamp);

            $context = new DownMigrationContext($this->connection, $keepUserData);

            $removed = [];
            foreach ($executed as $entry) {
                $byClass[$entry->class]->down($context);
                $this->stateStore->remove($plugin->getName(), $entry->class);
                $removed[] = $entry->class;
            }

            return $removed;
        });
    }

    /**
     * @param list<Migration> $migrations
     *
     * @return list<ExecutedMigration>
     */
    private function validatedExecutedMigrations(Plugin $plugin, array $migrations): array
    {
        $available = [];
        foreach ($migrations as $migration) {
            $available[$migration::class] = $migration;
        }

        $executed = $this->stateStore->executed($plugin->getName());
        foreach ($executed as $entry) {
            $migration = $available[$entry->class] ?? null;
            if (!$migration instanceof Migration) {
                throw MigrationException::missingAppliedMigration($plugin->getName(), $entry->class);
            }

            $declared = $migration->getCreationTimestamp();
            if ($declared !== $entry->creationTimestamp) {
                throw MigrationException::migrationTimestampChanged($entry->class, $entry->creationTimestamp, $declared);
            }
        }

        return $executed;
    }
}
