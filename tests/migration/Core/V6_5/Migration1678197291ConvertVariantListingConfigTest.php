<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_5;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityDefinitionQueryHelper;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Test\IdsCollection;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Migration\V6_5\Migration1678197291ConvertVariantListingConfig;

/**
 * @internal
 */
#[CoversClass(Migration1678197291ConvertVariantListingConfig::class)]
class Migration1678197291ConvertVariantListingConfigTest extends TestCase
{
    use KernelTestBehaviour;

    private Connection $connection;

    private Migration1678197291ConvertVariantListingConfig $migration;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
        $this->migration = new Migration1678197291ConvertVariantListingConfig();

        $this->rollbackMigration();
    }

    protected function tearDown(): void
    {
        $this->connection->executeStatement('DELETE FROM `product`');
    }

    public function testMigration(): void
    {
        $this->migration->update($this->connection);
        $this->migration->update($this->connection);

        static::assertTrue(EntityDefinitionQueryHelper::columnExists($this->connection, 'product', 'variant_listing_config'));
        static::assertFalse(EntityDefinitionQueryHelper::tableExists($this->connection, 'product_tmp'));

        /** @var array{EXTRA:string} $column */
        $column = $this->connection->fetchAssociative('
            SELECT EXTRA
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE
             TABLE_SCHEMA = :dbName AND
             TABLE_NAME   = \'product\' AND
             COLUMN_NAME  = \'variant_listing_config\'
        ', ['dbName' => $this->connection->getDatabase()]);

        static::assertEmpty($column['EXTRA']);
    }

    public function testMigrationWithExistingData(): void
    {
        $ids = new IdsCollection();

        $this->connection->insert('product', [
            'id' => Uuid::fromHexToBytes($ids->get('product-1')),
            'version_id' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
            'stock' => 1,
        ]);
        $this->connection->insert('product', [
            'id' => Uuid::fromHexToBytes($ids->get('variant-1-1')),
            'version_id' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
            'stock' => 1,
            'parent_id' => Uuid::fromHexToBytes($ids->get('product-1')),
        ]);
        $this->connection->insert('product', [
            'id' => Uuid::fromHexToBytes($ids->get('variant-1-2')),
            'version_id' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
            'stock' => 1,
            'parent_id' => Uuid::fromHexToBytes($ids->get('product-1')),
        ]);
        $this->connection->update('product', [
            'display_parent' => 0,
            'main_variant_id' => Uuid::fromHexToBytes($ids->get('variant-1-2')),
            'configurator_group_config' => json_encode([], \JSON_THROW_ON_ERROR),
        ], [
            'id' => Uuid::fromHexToBytes($ids->get('product-1')),
        ]);

        /** @var EntityRepository $productRepository */
        $productRepository = self::getContainer()->get('product.repository');
        /** @var ProductEntity $product */
        $product = $productRepository->search(new Criteria([$ids->get('product-1')]), Context::createDefaultContext())->first();
        static::assertNotNull($product);

        $config = $product->getVariantListingConfig();
        static::assertNotNull($config);
        static::assertFalse($config->getDisplayParent());
        static::assertEquals($ids->get('variant-1-2'), $config->getMainVariantId());
        static::assertEquals([], $config->getConfiguratorGroupConfig());

        $this->migration->update($this->connection);

        /** @var ProductEntity $product */
        $product = $productRepository->search(new Criteria([$ids->get('product-1')]), Context::createDefaultContext())->first();
        static::assertNotNull($product);

        $config = $product->getVariantListingConfig();
        static::assertNotNull($config);
        static::assertFalse($config->getDisplayParent());
        static::assertEquals($ids->get('variant-1-2'), $config->getMainVariantId());
        static::assertEquals([], $config->getConfiguratorGroupConfig());
    }

    private function rollbackMigration(): void
    {
        if (!EntityDefinitionQueryHelper::columnExists($this->connection, 'product', 'display_parent')) {
            $this->connection->executeStatement('ALTER TABLE `product` ADD COLUMN `display_parent` TINYINT(1) NULL DEFAULT NULL');
        }

        if (!EntityDefinitionQueryHelper::columnExists($this->connection, 'product', 'main_variant_id')) {
            $this->connection->executeStatement(
                'ALTER TABLE `product` ADD COLUMN `main_variant_id` binary(16) DEFAULT NULL'
            );
        }

        if (!EntityDefinitionQueryHelper::columnExists($this->connection, 'product', 'configurator_group_config')) {
            $this->connection->executeStatement('ALTER TABLE `product` ADD COLUMN `configurator_group_config` json DEFAULT NULL');
        }

        if (EntityDefinitionQueryHelper::columnExists($this->connection, 'product', 'variant_listing_config')) {
            $this->connection->executeStatement('ALTER TABLE `product` DROP COLUMN `variant_listing_config`');
        }

        $this->connection->executeStatement(
            'ALTER TABLE `product` ADD COLUMN `variant_listing_config` JSON
                    GENERATED ALWAYS AS (CASE WHEN `display_parent` IS NOT NULL OR `main_variant_id` IS NOT NULL OR `configurator_group_config` IS NOT NULL
                        THEN (JSON_OBJECT( \'displayParent\', `display_parent`, \'mainVariantId\', LOWER(HEX(`main_variant_id`)) ,\'configuratorGroupConfig\', JSON_EXTRACT(`configurator_group_config`, \'$\')))
                    END) VIRTUAL'
        );
    }
}
