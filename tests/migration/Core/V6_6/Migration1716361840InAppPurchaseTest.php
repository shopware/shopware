<?php declare(strict_types=1);
/*
 * (c) shopware AG <info@shopware.com>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Shopware\Tests\Migration\Core\V6_6;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Migration\V6_6\Migration1716361840InAppPurchase;

/**
 * @internal
 */
#[CoversClass(Migration1716361840InAppPurchase::class)]
#[Package('checkout')]
class Migration1716361840InAppPurchaseTest extends TestCase
{
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;

    public function testMigration(): void
    {
        $connection = $this->getContainer()->get(Connection::class);
        $connection->rollBack();

        $this->rollback($connection);

        $migration = new Migration1716361840InAppPurchase();
        $migration->update($connection);
        $migration->update($connection);

        $manager = $connection->createSchemaManager();

        static::assertTrue($manager->tablesExist(['in_app_purchase']));

        $columns = $manager->listTableColumns('in_app_purchase');

        static::assertCount(7, $columns);
        static::assertArrayHasKey('app_id', $columns);
        static::assertArrayHasKey('plugin_id', $columns);
        static::assertArrayHasKey('active', $columns);
        static::assertArrayHasKey('identifier', $columns);
        static::assertArrayHasKey('expires_at', $columns);
        static::assertArrayHasKey('created_at', $columns);
        static::assertArrayHasKey('updated_at', $columns);

        $indexes = $manager->listTableIndexes('in_app_purchase');

        static::assertCount(6, $indexes);
        static::assertArrayHasKey('primary', $indexes);
        static::assertArrayHasKey('expires_at', $indexes);
        static::assertArrayHasKey('active', $indexes);
        static::assertArrayHasKey('uniq.in_app_purchase.identifier', $indexes);
        static::assertArrayHasKey('fk.in_app_purchase.app_id', $indexes);
        static::assertArrayHasKey('fk.in_app_purchase.plugin_id', $indexes);

        (new Migration1716361840InAppPurchase())->update($connection);
        $connection->beginTransaction();
    }

    private function rollback(Connection $connection): void
    {
        $connection->executeStatement('DROP TABLE IF EXISTS `in_app_purchase`');
    }
}
