<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Migration\IndexerQueuer;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Util\Database\TableHelper;
use Shopware\Core\Migration\V6_7\Migration1780645634AddProductDescriptionTeaser;

/**
 * @internal
 */
#[CoversClass(Migration1780645634AddProductDescriptionTeaser::class)]
class Migration1780645634AddProductDescriptionTeaserTest extends TestCase
{
    private const CONFIG_KEY = 'core.listing.partialDataLoading';

    private Connection $connection;

    protected function setUp(): void
    {
        parent::setUp();

        $this->connection = KernelLifecycleManager::getConnection();

        unset($_SERVER[MigrationStep::INSTALL_ENVIRONMENT_VARIABLE], $_ENV[MigrationStep::INSTALL_ENVIRONMENT_VARIABLE]);
    }

    protected function tearDown(): void
    {
        unset($_SERVER[MigrationStep::INSTALL_ENVIRONMENT_VARIABLE], $_ENV[MigrationStep::INSTALL_ENVIRONMENT_VARIABLE]);

        $this->connection->executeStatement(
            'DELETE FROM `system_config` WHERE `configuration_key` = :key',
            ['key' => self::CONFIG_KEY]
        );

        parent::tearDown();
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1780645634, (new Migration1780645634AddProductDescriptionTeaser())->getCreationTimestamp());
    }

    public function testUpdateAddsPlainColumn(): void
    {
        $this->dropTeaserColumnIfExists();

        $migration = new Migration1780645634AddProductDescriptionTeaser();
        $migration->update($this->connection);
        $migration->update($this->connection);

        $column = $this->connection->fetchAssociative(
            'SELECT DATA_TYPE, CHARACTER_MAXIMUM_LENGTH, EXTRA FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = \'product_translation\' AND COLUMN_NAME = \'description_teaser\''
        );

        static::assertIsArray($column);
        static::assertSame('varchar', $column['DATA_TYPE']);
        static::assertSame(512, (int) $column['CHARACTER_MAXIMUM_LENGTH']);
        static::assertStringNotContainsStringIgnoringCase('generated', (string) $column['EXTRA']);
    }

    public function testUpdateRegistersBackfillIndexer(): void
    {
        $this->dropTeaserColumnIfExists();

        try {
            $migration = new Migration1780645634AddProductDescriptionTeaser();
            $migration->update($this->connection);
            $migration->update($this->connection);

            $indexers = (new IndexerQueuer($this->connection))->getIndexers();

            static::assertArrayHasKey('product.description_teaser.indexer', $indexers);
        } finally {
            (new IndexerQueuer($this->connection))->finishIndexer(['product.description_teaser.indexer']);
        }
    }

    public function testFreshInstallationEnablesPartialDataLoading(): void
    {
        $_SERVER[MigrationStep::INSTALL_ENVIRONMENT_VARIABLE] = true;

        $migration = new Migration1780645634AddProductDescriptionTeaser();
        $migration->update($this->connection);
        $migration->update($this->connection);

        $values = $this->connection->fetchFirstColumn(
            'SELECT `configuration_value` FROM `system_config` WHERE `configuration_key` = :key',
            ['key' => self::CONFIG_KEY]
        );

        static::assertCount(1, $values, 'config must be written exactly once');
        static::assertSame(['_value' => true], json_decode((string) $values[0], true));
    }

    public function testUpdateOnExistingInstallationDoesNotEnablePartialDataLoading(): void
    {
        $migration = new Migration1780645634AddProductDescriptionTeaser();
        $migration->update($this->connection);

        $value = $this->connection->fetchOne(
            'SELECT 1 FROM `system_config` WHERE `configuration_key` = :key',
            ['key' => self::CONFIG_KEY]
        );

        static::assertFalse($value, 'existing shops must keep full listing loading (opt-in)');
    }

    public function testFreshInstallationKeepsExistingConfigValue(): void
    {
        $_SERVER[MigrationStep::INSTALL_ENVIRONMENT_VARIABLE] = true;

        $this->connection->executeStatement(
            'INSERT INTO `system_config` (`id`, `configuration_key`, `configuration_value`, `created_at`)
             VALUES (0x11111111111111111111111111111111, :key, :value, NOW(3))',
            ['key' => self::CONFIG_KEY, 'value' => '{"_value": false}']
        );

        $migration = new Migration1780645634AddProductDescriptionTeaser();
        $migration->update($this->connection);

        $values = $this->connection->fetchFirstColumn(
            'SELECT `configuration_value` FROM `system_config` WHERE `configuration_key` = :key',
            ['key' => self::CONFIG_KEY]
        );

        static::assertCount(1, $values);
        static::assertSame(['_value' => false], json_decode((string) $values[0], true));
    }

    private function dropTeaserColumnIfExists(): void
    {
        if (TableHelper::columnExists($this->connection, 'product_translation', 'description_teaser')) {
            $this->connection->executeStatement('ALTER TABLE `product_translation` DROP COLUMN `description_teaser`');
        }
    }
}
