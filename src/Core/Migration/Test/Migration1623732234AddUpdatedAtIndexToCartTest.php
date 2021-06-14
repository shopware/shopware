<?php declare(strict_types=1);

namespace Shopware\Core\Migration\Test;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Migration\V6_4\Migration1623732234AddUpdatedAtIndexToCart;

class Migration1623732234AddUpdatedAtIndexToCartTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testMigration(): void
    {
        $connection = $this->getContainer()->get(Connection::class);

        if ($this->hasIndex($connection)) {
            $connection->executeStatement('ALTER TABLE `cart` DROP INDEX `idx.cart.updated_at`;');
        }

        $migration = new Migration1623732234AddUpdatedAtIndexToCart();
        $migration->update($connection);

        static::assertTrue($this->hasIndex($connection));
    }

    private function hasIndex(Connection $connection): bool
    {
        return (bool) $connection->executeQuery(
            <<<'SQL'
                SHOW INDEXES IN `cart`
                WHERE `Key_name` = 'idx.cart.updated_at';
            SQL
        )->fetchOne();
    }
}
