<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Util\Database\TableHelper;
use Shopware\Core\Migration\V6_7\Migration1786365555AddUnconfirmedSecretsToDeletedApps;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(Migration1786365555AddUnconfirmedSecretsToDeletedApps::class)]
class Migration1786365555AddUnconfirmedSecretsToDeletedAppsTest extends TestCase
{
    use KernelTestBehaviour;

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1786365555, (new Migration1786365555AddUnconfirmedSecretsToDeletedApps())->getCreationTimestamp());
    }

    public function testMigrationAddsNullableUnconfirmedSecretsColumnIdempotently(): void
    {
        $connection = self::getContainer()->get(Connection::class);

        $this->dropUnconfirmedSecretsColumnIfExists($connection);

        $migration = new Migration1786365555AddUnconfirmedSecretsToDeletedApps();
        $migration->update($connection);
        $migration->update($connection);

        static::assertTrue(TableHelper::columnExists($connection, 'deleted_apps', 'unconfirmed_app_secrets'));
        static::assertFalse(TableHelper::getColumnOfTable($connection, 'deleted_apps', 'unconfirmed_app_secrets')->isNotNull);
    }

    private function dropUnconfirmedSecretsColumnIfExists(Connection $connection): void
    {
        if (TableHelper::columnExists($connection, 'deleted_apps', 'unconfirmed_app_secrets')) {
            $connection->executeStatement('ALTER TABLE `deleted_apps` DROP COLUMN `unconfirmed_app_secrets`');
        }
    }
}
