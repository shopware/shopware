<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_5;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityDefinitionQueryHelper;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Migration\V6_5\Migration1678969082DropVariantListingFields;

/**
 * @internal
 */
#[CoversClass(Migration1678969082DropVariantListingFields::class)]
class Migration1678969082DropVariantListingFieldsTest extends TestCase
{
    use KernelTestBehaviour;

    private Connection $connection;

    private Migration1678969082DropVariantListingFields $migration;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
        $this->migration = new Migration1678969082DropVariantListingFields();

        $this->rollbackMigration();
    }

    public function testMigration(): void
    {
        $this->migration->updateDestructive($this->connection);
        $this->migration->updateDestructive($this->connection);

        static::assertFalse(EntityDefinitionQueryHelper::columnExists($this->connection, 'product', 'configurator_group_config'));
        static::assertFalse(EntityDefinitionQueryHelper::columnExists($this->connection, 'product', 'display_parent'));
        static::assertFalse(EntityDefinitionQueryHelper::columnExists($this->connection, 'product', 'main_variant_id'));
    }

    private function rollbackMigration(): void
    {
        if (EntityDefinitionQueryHelper::columnExists($this->connection, 'product', 'variant_listing_config')) {
            $this->connection->executeStatement('ALTER TABLE `product` DROP COLUMN `variant_listing_config`, ADD COLUMN `variant_listing_config` JSON NULL DEFAULT NULL');
        }

        if (!EntityDefinitionQueryHelper::columnExists($this->connection, 'product', 'display_parent')) {
            $this->connection->executeStatement('ALTER TABLE `product` ADD COLUMN `display_parent` TINYINT(1) NULL DEFAULT NULL');
        }

        if (!EntityDefinitionQueryHelper::columnExists($this->connection, 'product', 'main_variant_id')) {
            $this->connection->executeStatement(
                'ALTER TABLE `product` ADD COLUMN `main_variant_id` binary(16) DEFAULT NULL,
                        ADD CONSTRAINT `fk.product.main_variant_id` FOREIGN KEY (`main_variant_id`) REFERENCES `product` (`id`) ON DELETE SET NULL'
            );
        }

        if (!EntityDefinitionQueryHelper::columnExists($this->connection, 'product', 'configurator_group_config')) {
            $this->connection->executeStatement('ALTER TABLE `product` ADD COLUMN `configurator_group_config` json DEFAULT NULL');
        }
    }
}
