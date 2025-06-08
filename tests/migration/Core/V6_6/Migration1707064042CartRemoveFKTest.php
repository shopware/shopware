<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_6;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Migration\V6_6\Migration1707064042CartRemoveFK;

/**
 * @internal
 */
#[CoversClass(Migration1707064042CartRemoveFK::class)]
class Migration1707064042CartRemoveFKTest extends TestCase
{
    public function testIndexGetsDropped(): void
    {
        $connection = KernelLifecycleManager::getConnection();

        try {
            $connection->executeStatement(
                '
ALTER TABLE `cart`
ADD FOREIGN KEY (`fk.cart.payment_method_id`) REFERENCES `payment_method` (`id`) ON DELETE CASCADE ON UPDATE CASCADE'
            );
        } catch (\Exception $e) {
        }

        $m = new Migration1707064042CartRemoveFK();
        $m->update($connection);
        $m->update($connection);

        $fks = $connection->createSchemaManager()->listTableForeignKeys('cart');
        static::assertEmpty($fks);
    }

    public function testDescructive(): void
    {
        $connection = KernelLifecycleManager::getConnection();

        try {
            $connection->executeStatement('ALTER TABLE `cart` ADD COLUMN `price` DOUBLE(10, 6) DEFAULT NULL');
        } catch (\Exception) {
        }

        $m = new Migration1707064042CartRemoveFK();
        $m->updateDestructive($connection);
        $m->updateDestructive($connection);

        $columns = $connection->executeQuery('SHOW COLUMNS FROM `cart`')->fetchAllAssociative();
        $columns = array_column($columns, 'Field');

        static::assertNotContains('price', $columns);
    }
}
