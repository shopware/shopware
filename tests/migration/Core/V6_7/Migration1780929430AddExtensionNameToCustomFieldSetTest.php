<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Util\Database\TableHelper;
use Shopware\Core\Migration\V6_7\Migration1780929430AddExtensionNameToCustomFieldSet;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(Migration1780929430AddExtensionNameToCustomFieldSet::class)]
class Migration1780929430AddExtensionNameToCustomFieldSetTest extends TestCase
{
    use KernelTestBehaviour;

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1780929430, (new Migration1780929430AddExtensionNameToCustomFieldSet())->getCreationTimestamp());
    }

    public function testMigrationAddsNullableColumn(): void
    {
        $connection = self::getContainer()->get(Connection::class);

        $this->dropColumnIfExists($connection);

        $migration = new Migration1780929430AddExtensionNameToCustomFieldSet();
        // run twice to ensure the migration is idempotent
        $migration->update($connection);
        $migration->update($connection);

        static::assertTrue(TableHelper::columnExists($connection, 'custom_field_set', 'extension_name'));

        $column = TableHelper::getColumnOfTable($connection, 'custom_field_set', 'extension_name');
        static::assertFalse($column->isNotNull);
        static::assertSame('string', $column->type);
        static::assertSame(255, $column->length);
    }

    private function dropColumnIfExists(Connection $connection): void
    {
        if (TableHelper::columnExists($connection, 'custom_field_set', 'extension_name')) {
            $connection->executeStatement('ALTER TABLE `custom_field_set` DROP COLUMN `extension_name`');
        }
    }
}
