<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Migration\Reversible;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationException;

/**
 * @internal
 */
#[Package('framework')]
class MigrationStateStore
{
    public function __construct(private readonly Connection $connection)
    {
    }

    /**
     * @return list<ExecutedMigration> sorted by creation timestamp, ascending
     */
    public function executed(string $plugin): array
    {
        $rows = $this->connection->fetchAllAssociative(
            <<<'SQL'
                SELECT `migration_class`, `creation_timestamp`
                FROM `plugin_migration`
                WHERE `plugin_name` = :plugin
                ORDER BY `creation_timestamp` ASC
                SQL,
            ['plugin' => $plugin]
        );

        return array_map(static function (array $row): ExecutedMigration {
            $class = $row['migration_class'] ?? null;
            $timestamp = $row['creation_timestamp'] ?? null;
            if (!\is_string($class) || (!\is_int($timestamp) && !\is_string($timestamp))) {
                throw MigrationException::invalidMigrationState();
            }

            /** @var class-string<Migration> $class */
            return new ExecutedMigration($class, (int) $timestamp);
        }, $rows);
    }

    /**
     * @param class-string<Migration> $class
     */
    public function markExecuted(string $plugin, string $class, int $creationTimestamp): void
    {
        $this->connection->insert('plugin_migration', [
            'plugin_name' => $plugin,
            'migration_class' => $class,
            'creation_timestamp' => $creationTimestamp,
        ]);
    }

    /**
     * @param class-string<Migration> $class
     */
    public function remove(string $plugin, string $class): void
    {
        $this->connection->delete('plugin_migration', [
            'plugin_name' => $plugin,
            'migration_class' => $class,
        ]);
    }
}
