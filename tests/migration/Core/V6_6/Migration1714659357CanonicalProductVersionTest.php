<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_6;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Util\Database\TableHelper;
use Shopware\Core\Migration\V6_6\Migration1714659357CanonicalProductVersion;

/**
 * @internal
 */
#[CoversClass(Migration1714659357CanonicalProductVersion::class)]
class Migration1714659357CanonicalProductVersionTest extends TestCase
{
    use KernelTestBehaviour;

    protected Connection $connection;

    protected function setUp(): void
    {
        $this->connection = self::getContainer()->get(Connection::class);

        $version = strtolower($this->connection->getServerVersion());

        if (!str_contains($version, 'mariadb') && version_compare($version, '8.4.0', '>=')) {
            static::markTestSkipped('Test is only relevant for MariaDB or MySQL < 8.4.0');
        }
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1714659357, (new Migration1714659357CanonicalProductVersion())->getCreationTimestamp());
    }

    public function testMigration(): void
    {
        $this->connection->executeStatement('ALTER TABLE `product` DROP FOREIGN KEY `fk.product.canonical_product_id`');
        $this->connection->executeStatement('ALTER TABLE `product` DROP INDEX `fk.product.canonical_product_id`');

        $this->connection->executeStatement('ALTER TABLE `product` DROP COLUMN `canonical_product_version_id`');
        $this->connection->executeStatement('ALTER TABLE `product`  DROP COLUMN `canonical_product_id`');

        $this->connection->executeStatement('ALTER TABLE `product` ADD COLUMN `canonical_product_id` BINARY(16) NULL');
        $this->connection->executeStatement('ALTER TABLE `product` ADD CONSTRAINT `fk.product.canonical_product_id` FOREIGN KEY (`canonical_product_id`) REFERENCES `product` (`id`) ON DELETE SET NULL');

        $m = new Migration1714659357CanonicalProductVersion();
        $m->update($this->connection);
        $m->update($this->connection);

        static::assertTrue(TableHelper::columnExists($this->connection, 'product', 'canonical_product_version_id'));
        static::assertTrue(TableHelper::columnExists($this->connection, 'product', 'canonical_product_id'));

        static::assertTrue(TableHelper::indexExists($this->connection, 'product', 'fk.product.canonical_product_id'));
    }
}
