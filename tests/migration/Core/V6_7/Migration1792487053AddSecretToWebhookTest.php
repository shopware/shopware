<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Util\Database\TableHelper;
use Shopware\Core\Migration\V6_7\Migration1792487053AddSecretToWebhook;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(Migration1792487053AddSecretToWebhook::class)]
class Migration1792487053AddSecretToWebhookTest extends TestCase
{
    use KernelTestBehaviour;

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1792487053, (new Migration1792487053AddSecretToWebhook())->getCreationTimestamp());
    }

    public function testMigrationAddsANullableSecretColumnIdempotently(): void
    {
        $connection = self::getContainer()->get(Connection::class);
        if (TableHelper::columnExists($connection, 'webhook', 'secret')) {
            $connection->executeStatement('ALTER TABLE `webhook` DROP COLUMN `secret`');
        }

        $migration = new Migration1792487053AddSecretToWebhook();
        $migration->update($connection);
        $migration->update($connection);

        static::assertTrue(TableHelper::columnExists($connection, 'webhook', 'secret'));
        static::assertFalse(TableHelper::getColumnOfTable($connection, 'webhook', 'secret')->isNotNull);
    }
}
