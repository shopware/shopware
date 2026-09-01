<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Migration\Reversible;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\Reversible\ExecutedMigration;
use Shopware\Core\Framework\Migration\Reversible\MigrationStateStore;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Tests\Integration\Core\Framework\Migration\Reversible\_fixtures\HistoryOnlyMigrationA;
use Shopware\Tests\Integration\Core\Framework\Migration\Reversible\_fixtures\HistoryOnlyMigrationB;
use Shopware\Tests\Integration\Core\Framework\Migration\Reversible\_fixtures\HistoryOnlyMigrationC;

/**
 * @internal
 */
#[Package('framework')]
class MigrationStateStoreTest extends TestCase
{
    use KernelTestBehaviour;

    private const PLUGIN = 'SwagStateStoreTest';

    private const OTHER_PLUGIN = 'SwagStateStoreOther';

    private Connection $connection;

    private MigrationStateStore $store;

    protected function setUp(): void
    {
        $this->connection = static::getContainer()->get(Connection::class);
        $this->store = new MigrationStateStore($this->connection);

        $this->cleanUp();
    }

    protected function tearDown(): void
    {
        $this->cleanUp();
    }

    public function testReturnsAnEmptyHistoryForAnUnknownPlugin(): void
    {
        static::assertSame([], $this->store->executed(self::PLUGIN));
    }

    public function testRoundTripsHistoryInAscendingTimestampOrder(): void
    {
        $this->store->markExecuted(self::PLUGIN, HistoryOnlyMigrationB::class, 200);
        $this->store->markExecuted(self::PLUGIN, HistoryOnlyMigrationA::class, 100);

        $executed = $this->store->executed(self::PLUGIN);

        static::assertSame(
            [HistoryOnlyMigrationA::class, HistoryOnlyMigrationB::class],
            array_map(static fn (ExecutedMigration $entry): string => $entry->class, $executed)
        );
        static::assertSame(
            [100, 200],
            array_map(static fn (ExecutedMigration $entry): int => $entry->creationTimestamp, $executed)
        );
    }

    public function testPopulatesExecutedAt(): void
    {
        $this->store->markExecuted(self::PLUGIN, HistoryOnlyMigrationA::class, 100);

        $executedAt = $this->connection->fetchOne(
            'SELECT `executed_at` FROM `plugin_migration` WHERE `plugin_name` = :plugin',
            ['plugin' => self::PLUGIN]
        );

        static::assertIsString($executedAt);
        static::assertNotSame('', $executedAt);
    }

    public function testRemoveOnlyDeletesTheMatchingEntry(): void
    {
        $this->store->markExecuted(self::PLUGIN, HistoryOnlyMigrationA::class, 100);
        $this->store->markExecuted(self::PLUGIN, HistoryOnlyMigrationB::class, 200);

        $this->store->remove(self::PLUGIN, HistoryOnlyMigrationA::class);

        static::assertSame(
            [HistoryOnlyMigrationB::class],
            array_map(static fn (ExecutedMigration $entry): string => $entry->class, $this->store->executed(self::PLUGIN))
        );
    }

    public function testHistoryIsIsolatedPerPlugin(): void
    {
        $this->store->markExecuted(self::PLUGIN, HistoryOnlyMigrationA::class, 100);
        $this->store->markExecuted(self::OTHER_PLUGIN, HistoryOnlyMigrationA::class, 100);

        $this->store->remove(self::PLUGIN, HistoryOnlyMigrationA::class);

        static::assertSame([], $this->store->executed(self::PLUGIN));
        static::assertCount(1, $this->store->executed(self::OTHER_PLUGIN));
    }

    public function testRejectsADuplicateTimestampWithinOnePlugin(): void
    {
        $this->store->markExecuted(self::PLUGIN, HistoryOnlyMigrationA::class, 100);

        $this->expectException(UniqueConstraintViolationException::class);

        $this->store->markExecuted(self::PLUGIN, HistoryOnlyMigrationC::class, 100);
    }

    private function cleanUp(): void
    {
        $this->connection->executeStatement(
            'DELETE FROM `plugin_migration` WHERE `plugin_name` IN (:plugins)',
            ['plugins' => [self::PLUGIN, self::OTHER_PLUGIN]],
            ['plugins' => ArrayParameterType::STRING]
        );
    }
}
