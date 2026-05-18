<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_6;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Util\Database\TableHelper;
use Shopware\Core\Migration\V6_6\Migration1714659357CanonicalProductVersion;
use Shopware\Tests\Migration\MySql84FkGuardTestTrait;

/**
 * @internal
 */
#[CoversClass(Migration1714659357CanonicalProductVersion::class)]
class Migration1714659357CanonicalProductVersionTest extends TestCase
{
    use KernelTestBehaviour;
    use MySql84FkGuardTestTrait;

    protected Connection $connection;

    protected function setUp(): void
    {
        $this->connection = self::getContainer()->get(Connection::class);
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1714659357, (new Migration1714659357CanonicalProductVersion())->getCreationTimestamp());
    }

    public function testMigration(): void
    {
        $version = strtolower($this->connection->getServerVersion());

        if (!str_contains($version, 'mariadb') && version_compare($version, '8.4.0', '>=')) {
            static::markTestSkipped(
                'Legacy fixture creates a non-standard FK that MySQL 8.4 blocks by default; '
                . 'covered by testMigrationRunsOnMysql84WithNonStandardCanonicalProductFk instead.'
            );
        }

        $this->setUpLegacyBrokenState();

        $m = new Migration1714659357CanonicalProductVersion();
        $m->update($this->connection);
        $m->update($this->connection);

        static::assertTrue(TableHelper::columnExists($this->connection, 'product', 'canonical_product_version_id'));
        static::assertTrue(TableHelper::columnExists($this->connection, 'product', 'canonical_product_id'));

        static::assertTrue(TableHelper::indexExists($this->connection, 'product', 'fk.product.canonical_product_id'));
    }

    /**
     * Regression coverage for issue #16240 / MySQL bug #118151. The migration
     * itself repairs a pre-existing non-standard FK on `product`, so this test
     * temporarily disables the guard to install the broken state, then asserts
     * the migration completes (using its own internal guard relax) and
     * restores the canonical standard FK.
     */
    public function testMigrationRunsOnMysql84WithNonStandardCanonicalProductFk(): void
    {
        $this->skipUnlessMysql84WithFkGuardOn($this->connection);

        $this->connection->executeStatement('SET SESSION restrict_fk_on_non_standard_key = OFF');
        try {
            $this->setUpLegacyBrokenState();
        } finally {
            $this->connection->executeStatement('SET SESSION restrict_fk_on_non_standard_key = ON');
        }

        $m = new Migration1714659357CanonicalProductVersion();
        $m->update($this->connection);
        $m->update($this->connection);

        static::assertTrue(TableHelper::columnExists($this->connection, 'product', 'canonical_product_version_id'));
        static::assertTrue(TableHelper::columnExists($this->connection, 'product', 'canonical_product_id'));
        static::assertTrue(TableHelper::indexExists($this->connection, 'product', 'fk.product.canonical_product_id'));
        static::assertSame(
            '1',
            (string) $this->connection->fetchOne('SELECT @@SESSION.restrict_fk_on_non_standard_key'),
            'Migration must restore the FK guard to its previous (ON) state'
        );
    }

    private function setUpLegacyBrokenState(): void
    {
        $this->connection->executeStatement('ALTER TABLE `product` DROP FOREIGN KEY `fk.product.canonical_product_id`');
        $this->connection->executeStatement('ALTER TABLE `product` DROP INDEX `fk.product.canonical_product_id`');

        $this->connection->executeStatement('ALTER TABLE `product` DROP COLUMN `canonical_product_version_id`');
        $this->connection->executeStatement('ALTER TABLE `product`  DROP COLUMN `canonical_product_id`');

        $this->connection->executeStatement('ALTER TABLE `product` ADD COLUMN `canonical_product_id` BINARY(16) NULL');
        $this->connection->executeStatement('ALTER TABLE `product` ADD CONSTRAINT `fk.product.canonical_product_id` FOREIGN KEY (`canonical_product_id`) REFERENCES `product` (`id`) ON DELETE SET NULL');
    }
}
