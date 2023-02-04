<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_4;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Migration\V6_4\Migration1627540693MakeAccessTokenNullable;

/**
 * @internal
 *
 * @covers \Shopware\Core\Migration\V6_4\Migration1627540693MakeAccessTokenNullable
 */
class Migration1627540693MakeAccessTokenNullableTest extends TestCase
{
    public function testMigrate(): void
    {
        $connection = KernelLifecycleManager::getConnection();

        // Make it not nullable
        $connection->executeStatement('ALTER TABLE `import_export_file` CHANGE `access_token` `access_token` varchar(255) COLLATE \'utf8mb4_unicode_ci\' NOT NULL AFTER `created_at`;');

        $m = new Migration1627540693MakeAccessTokenNullable();
        $m->update($connection);

        $columns = $connection->getSchemaManager()->listTableColumns('import_export_file');

        $foundColumn = false;
        foreach ($columns as $column) {
            if ($column->getName() === 'access_token') {
                $foundColumn = true;

                static::assertFalse($column->getNotnull());
            }
        }

        static::assertTrue($foundColumn, 'Could not find column access_token');
    }
}
