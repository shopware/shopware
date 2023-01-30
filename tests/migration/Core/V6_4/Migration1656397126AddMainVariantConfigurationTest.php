<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_4;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityDefinitionQueryHelper;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Migration\V6_4\Migration1656397126AddMainVariantConfiguration;

/**
 * @internal
 *
 * @covers \Shopware\Core\Migration\V6_4\Migration1656397126AddMainVariantConfiguration
 */
class Migration1656397126AddMainVariantConfigurationTest extends TestCase
{
    use KernelTestBehaviour;

    private Connection $connection;

    private Migration1656397126AddMainVariantConfiguration $migration;

    protected function setUp(): void
    {
        parent::setUp();
        $this->connection = $this->getContainer()->get(Connection::class);
        $this->migration = new Migration1656397126AddMainVariantConfiguration();
        $this->prepare();
    }

    public function testMigrationOnceOrMultipleTimes(): void
    {
        static::assertFalse(EntityDefinitionQueryHelper::columnExists($this->connection, 'product', 'display_parent'));
        static::assertFalse(EntityDefinitionQueryHelper::columnExists($this->connection, 'product', 'variant_listing_config'));

        $this->migration->update($this->connection);
        $this->migration->update($this->connection);

        static::assertTrue(EntityDefinitionQueryHelper::columnExists($this->connection, 'product', 'display_parent'));
        static::assertTrue(EntityDefinitionQueryHelper::columnExists($this->connection, 'product', 'variant_listing_config'));
    }

    private function prepare(): void
    {
        if (EntityDefinitionQueryHelper::columnExists($this->connection, 'product', 'variant_listing_config')) {
            $this->connection->executeStatement('ALTER TABLE `product` DROP COLUMN `variant_listing_config`');
        }

        if (EntityDefinitionQueryHelper::columnExists($this->connection, 'product', 'display_parent')) {
            $this->connection->executeStatement('ALTER TABLE `product` DROP COLUMN `display_parent`');
        }
    }
}
