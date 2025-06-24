<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Migration\V6_7\Migration1717572627RemoveImportExportProfileName;

/**
 * @internal
 */
#[Package('fundamentals@after-sales')]
#[CoversClass(Migration1717572627RemoveImportExportProfileName::class)]
class Migration1717572627RemoveImportExportProfileNameTest extends TestCase
{
    use KernelTestBehaviour;
    private static bool $nameColumnAdded = false;

    private Connection $connection;

    public static function setUpBeforeClass(): void
    {
        $connection = self::getContainer()->get(Connection::class);
        $columns = $connection->fetchAllAssociative('SHOW COLUMNS FROM `import_export_profile`');
        $columns = array_column($columns, 'Field');

        if (!\in_array('name', $columns, true)) {
            $connection->executeStatement('ALTER TABLE `import_export_profile` ADD COLUMN `name` VARCHAR(255) NULL');
            self::$nameColumnAdded = true;
        }

        $connection->executeStatement('ALTER TABLE `import_export_profile` MODIFY COLUMN `technical_name` VARCHAR(255) NULL');
    }

    public static function tearDownAfterClass(): void
    {
        if (self::$nameColumnAdded) {
            self::getContainer()->get(Connection::class)->executeStatement('ALTER TABLE `import_export_profile` DROP COLUMN `name`');
        }
    }

    protected function setUp(): void
    {
        $this->connection = self::getContainer()->get(Connection::class);
    }

    protected function tearDown(): void
    {
        $this->connection->executeStatement('DELETE FROM `import_export_profile` WHERE `system_default` != 1');
    }

    public function testUpdateDestructiveRemovesColumn(): void
    {
        $exists = $this->columnExists();

        if (!$exists) {
            $this->addColumn();
        }

        $migration = new Migration1717572627RemoveImportExportProfileName();
        $migration->updateDestructive($this->connection);
        $migration->updateDestructive($this->connection);

        static::assertFalse($this->columnExists());

        if ($exists) {
            $this->addColumn();
        }
    }

    public function testUpdateAddsColumnTechnicalNameIfNotExists(): void
    {
        $exists = $this->columnExists();

        if ($exists) {
            $this->connection->executeStatement('ALTER TABLE `import_export_profile` DROP COLUMN `technical_name`');
        }

        $migration = new Migration1717572627RemoveImportExportProfileName();
        $migration->update($this->connection);

        static::assertTrue($this->columnExists());

        $indexExists = $this->connection->fetchOne(
            'SHOW INDEX FROM `import_export_profile` WHERE Key_name = \'uniq.import_export_profile.technical_name\''
        );

        static::assertNotFalse($indexExists, 'Unique index on technical_name should be created');
    }

    public function testUpdateGeneratesTechnicalNames(): void
    {
        $this->addTestData();

        $migration = new Migration1717572627RemoveImportExportProfileName();
        $migration->update($this->connection);

        $rows = $this->connection->fetchAllAssociative('SELECT * FROM `import_export_profile` WHERE `system_default` != 1');

        static::assertSame('some-technical-name',$rows[0]['technical_name']);
        static::assertSame('profile_2', $rows[1]['technical_name']);
        static::assertSame('unnamed_profile', $rows[2]['technical_name']);
        static::assertSame('unnamed_profile_1', $rows[3]['technical_name']);

    }

    private function addColumn(): void
    {
        $this->connection->executeStatement(
            'ALTER TABLE `import_export_profile` ADD COLUMN `name` VARCHAR(255) DEFAULT NULL'
        );
    }

    private function columnExists(): bool
    {
        $exists = $this->connection->fetchOne(
            'SHOW COLUMNS FROM `import_export_profile` WHERE `Field` LIKE "name"',
        );

        return !empty($exists);
    }

    private function addTestData(): void
    {
        $profiles = [
            [Uuid::randomBytes(), 'Profile 1', 'some-technical-name'],
            [Uuid::randomBytes(), 'Profile 2', null],
            [Uuid::randomBytes(), null, null],
            [Uuid::randomBytes(), null, null],
        ];

        foreach ($profiles as [$id, $name, $technicalName]) {
            $this->connection->insert('import_export_profile', [
                'id' => $id,
                'name' => $name,
                'technical_name' => $technicalName,
                'source_entity' => 'product',
                'file_type' => 'text/csv',
                'created_at' => "2025-06-24 00:00:00.000"
            ]);
        }

        $rows = $this->connection->fetchAllAssociative('SELECT * FROM `import_export_profile` WHERE `system_default` != 1');
        static::assertCount(4, $rows);
    }
}
