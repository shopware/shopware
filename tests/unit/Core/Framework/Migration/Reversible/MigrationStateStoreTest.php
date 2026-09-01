<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Migration\Reversible;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationException;
use Shopware\Core\Framework\Migration\Reversible\ExecutedMigration;
use Shopware\Core\Framework\Migration\Reversible\MigrationStateStore;
use Shopware\Tests\Unit\Core\Framework\Migration\Reversible\_fixtures\RecordingMigrationA;
use Shopware\Tests\Unit\Core\Framework\Migration\Reversible\_fixtures\RecordingMigrationB;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(MigrationStateStore::class)]
class MigrationStateStoreTest extends TestCase
{
    public function testExecutedMapsRowsToExecutedMigrations(): void
    {
        $connection = static::createStub(Connection::class);
        $connection->method('fetchAllAssociative')->willReturn([
            // the creation timestamp comes back as a string on some PDO drivers
            ['migration_class' => RecordingMigrationA::class, 'creation_timestamp' => '100'],
            ['migration_class' => RecordingMigrationB::class, 'creation_timestamp' => 200],
        ]);

        $executed = (new MigrationStateStore($connection))->executed('SwagReversible');

        static::assertSame(
            [RecordingMigrationA::class, RecordingMigrationB::class],
            array_map(static fn (ExecutedMigration $entry): string => $entry->class, $executed)
        );
        static::assertSame(
            [100, 200],
            array_map(static fn (ExecutedMigration $entry): int => $entry->creationTimestamp, $executed)
        );
    }

    /**
     * @param list<array<string, mixed>> $rows
     */
    #[DataProvider('malformedRows')]
    public function testExecutedRejectsMalformedRows(array $rows): void
    {
        $connection = static::createStub(Connection::class);
        $connection->method('fetchAllAssociative')->willReturn($rows);

        $this->expectExceptionObject(MigrationException::invalidMigrationState());

        (new MigrationStateStore($connection))->executed('SwagReversible');
    }

    /**
     * @return iterable<string, array{list<array<string, mixed>>}>
     */
    public static function malformedRows(): iterable
    {
        yield 'migration class is missing' => [
            [['creation_timestamp' => 100]],
        ];

        yield 'creation timestamp is not a number' => [
            [['migration_class' => RecordingMigrationA::class, 'creation_timestamp' => ['100']]],
        ];
    }

    public function testMarkExecutedWritesTheHistoryRow(): void
    {
        $writes = [];

        (new MigrationStateStore($this->connectionCapturingWrites($writes)))
            ->markExecuted('SwagReversible', RecordingMigrationA::class, 100);

        static::assertSame([[
            'plugin_name' => 'SwagReversible',
            'migration_class' => RecordingMigrationA::class,
            'creation_timestamp' => 100,
        ]], $writes);
    }

    public function testRemoveDeletesTheHistoryRow(): void
    {
        $writes = [];

        (new MigrationStateStore($this->connectionCapturingWrites($writes)))
            ->remove('SwagReversible', RecordingMigrationA::class);

        static::assertSame([[
            'plugin_name' => 'SwagReversible',
            'migration_class' => RecordingMigrationA::class,
        ]], $writes);
    }

    /**
     * @param list<array<string, mixed>> $writes
     */
    private function connectionCapturingWrites(array &$writes): Connection
    {
        $connection = static::createStub(Connection::class);
        $connection->method('insert')->willReturnCallback(static function (string $table, array $values) use (&$writes): int {
            $writes[] = $values;

            return 1;
        });
        $connection->method('delete')->willReturnCallback(static function (string $table, array $criteria) use (&$writes): int {
            $writes[] = $criteria;

            return 1;
        });

        return $connection;
    }
}
