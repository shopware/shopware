<?php declare(strict_types=1);

namespace Shopware\Core\Migration\Test;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Migration\V6_4\Migration1627540693MakeAccessTokenNullable;

class Migration1627540693MakeAccessTokenNullableTest extends TestCase
{
    use KernelTestBehaviour;

    public function testMigrate(): void
    {
        $connection = $this->getContainer()->get(Connection::class);

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
