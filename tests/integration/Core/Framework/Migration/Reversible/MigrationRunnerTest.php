<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Migration\Reversible;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\Reversible\MigrationRunner;
use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Tests\Integration\Core\Framework\Migration\Reversible\_fixtures\SwagIntegration\Migration\Migration1900000000CreateTable;
use Shopware\Tests\Integration\Core\Framework\Migration\Reversible\_fixtures\SwagIntegration\SwagIntegration;

/**
 * @internal
 */
#[Package('framework')]
class MigrationRunnerTest extends TestCase
{
    use KernelTestBehaviour;

    private Connection $connection;

    private MigrationRunner $runner;

    protected function setUp(): void
    {
        $this->connection = static::getContainer()->get(Connection::class);
        $this->runner = static::getContainer()->get(MigrationRunner::class);

        $this->cleanUp();
    }

    protected function tearDown(): void
    {
        $this->cleanUp();
    }

    public function testUpAppliesTheMigrationAndRecordsHistory(): void
    {
        $applied = $this->runner->up($this->plugin());

        static::assertSame([Migration1900000000CreateTable::class], $applied);
        static::assertTrue($this->tableExists());
        static::assertSame(
            [Migration1900000000CreateTable::class => '1900000000'],
            $this->history()
        );
    }

    public function testUpIsIdempotent(): void
    {
        $this->runner->up($this->plugin());

        static::assertSame([], $this->runner->up($this->plugin()));
        static::assertTrue($this->tableExists());
    }

    public function testDownRollsBackTheMigrationAndClearsHistory(): void
    {
        $this->runner->up($this->plugin());

        $removed = $this->runner->down($this->plugin());

        static::assertSame([Migration1900000000CreateTable::class], $removed);
        static::assertFalse($this->tableExists());
        static::assertSame([], $this->history());
    }

    public function testDownKeepsSchemaAndHistoryWhenUserDataIsKept(): void
    {
        $this->runner->up($this->plugin());

        static::assertSame([], $this->runner->down($this->plugin(), true));
        static::assertTrue($this->tableExists());
        static::assertCount(1, $this->history());
    }

    public function testTheLegacyMigrationTableIsNotTouched(): void
    {
        $before = $this->connection->fetchOne('SELECT COUNT(*) FROM `migration`');

        $this->runner->up($this->plugin());
        $this->runner->down($this->plugin());

        static::assertSame($before, $this->connection->fetchOne('SELECT COUNT(*) FROM `migration`'));
    }

    private function plugin(): Plugin
    {
        $directory = \dirname((string) (new \ReflectionClass(SwagIntegration::class))->getFileName());

        return new SwagIntegration(true, $directory);
    }

    private function tableExists(): bool
    {
        return $this->connection->fetchOne(
            'SHOW TABLES LIKE :table',
            ['table' => Migration1900000000CreateTable::TABLE]
        ) !== false;
    }

    /**
     * @return array<string, string>
     */
    private function history(): array
    {
        return $this->connection->fetchAllKeyValue(
            'SELECT `migration_class`, `creation_timestamp` FROM `plugin_migration` WHERE `plugin_name` = :plugin',
            ['plugin' => 'SwagIntegration']
        );
    }

    private function cleanUp(): void
    {
        $this->connection->executeStatement(
            \sprintf('DROP TABLE IF EXISTS `%s`', Migration1900000000CreateTable::TABLE)
        );
        $this->connection->executeStatement(
            'DELETE FROM `plugin_migration` WHERE `plugin_name` = :plugin',
            ['plugin' => 'SwagIntegration']
        );
    }
}
