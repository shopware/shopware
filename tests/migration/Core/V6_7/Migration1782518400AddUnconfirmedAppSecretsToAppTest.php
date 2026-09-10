<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Util\Database\TableHelper;
use Shopware\Core\Migration\V6_7\Migration1782518400AddUnconfirmedAppSecretsToApp;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(Migration1782518400AddUnconfirmedAppSecretsToApp::class)]
class Migration1782518400AddUnconfirmedAppSecretsToAppTest extends TestCase
{
    use KernelTestBehaviour;

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1782518400, (new Migration1782518400AddUnconfirmedAppSecretsToApp())->getCreationTimestamp());
    }

    public function testMigrationAddsNullableUnconfirmedAppSecretsColumnsIdempotently(): void
    {
        $connection = self::getContainer()->get(Connection::class);

        $this->dropUnconfirmedAppSecretsColumnIfExists($connection);

        $migration = new Migration1782518400AddUnconfirmedAppSecretsToApp();
        // running it twice must be safe: the second run should add nothing and not fail
        $migration->update($connection);
        $migration->update($connection);

        static::assertTrue(TableHelper::columnExists($connection, 'app', 'unconfirmed_app_secrets'));
        static::assertFalse(TableHelper::getColumnOfTable($connection, 'app', 'unconfirmed_app_secrets')->isNotNull);
    }

    private function dropUnconfirmedAppSecretsColumnIfExists(Connection $connection): void
    {
        if (TableHelper::columnExists($connection, 'app', 'unconfirmed_app_secrets')) {
            $connection->executeStatement('ALTER TABLE `app` DROP COLUMN `unconfirmed_app_secrets`');
        }
    }
}
