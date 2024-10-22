<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_6;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Migration\V6_6\Migration1716968180AddAppSourceConfig;

/**
 * @internal
 */
#[CoversClass(Migration1716968180AddAppSourceConfig::class)]
class Migration1716968180AddAppSourceConfigTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();

        try {
            $this->connection->executeStatement(
                'ALTER TABLE `app` DROP COLUMN `source_config`;'
            );
        } catch (\Throwable) {
        }
    }

    public function testMigration(): void
    {
        static::assertFalse($this->columnExists());

        $migration = new Migration1716968180AddAppSourceConfig();
        $migration->update($this->connection);
        $migration->update($this->connection);

        static::assertTrue($this->columnExists());
    }

    private function columnExists(): bool
    {
        $field = $this->connection->fetchOne(
            'SHOW COLUMNS FROM `app` WHERE `Field` = "source_config";',
        );

        return $field === 'source_config';
    }
}
