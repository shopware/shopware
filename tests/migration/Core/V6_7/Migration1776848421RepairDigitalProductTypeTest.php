<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Migration\IndexerQueuer;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Util\Database\TableHelper;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Migration\V6_7\Migration1776848421RepairDigitalProductType;
use Shopware\Core\Test\Stub\Framework\IdsCollection;

/**
 * @internal
 */
#[CoversClass(Migration1776848421RepairDigitalProductType::class)]
class Migration1776848421RepairDigitalProductTypeTest extends TestCase
{
    private Connection $connection;

    private IdsCollection $ids;

    protected function setUp(): void
    {
        parent::setUp();

        $this->connection = KernelLifecycleManager::getConnection();
        $this->ids = new IdsCollection();

        try {
            $this->connection->executeStatement('DELETE FROM `product`');
        } catch (\Throwable) {
        }

        $this->connection->executeStatement(
            'DELETE FROM `system_config` WHERE `configuration_key` = :key',
            ['key' => IndexerQueuer::INDEXER_KEY]
        );

        $this->ensureStatesColumnExists();
        $this->ensureTypeColumnExists();
    }

    protected function tearDown(): void
    {
        try {
            $this->connection->executeStatement('DELETE FROM `product`');
        } catch (\Throwable) {
        }

        $this->connection->executeStatement(
            'DELETE FROM `system_config` WHERE `configuration_key` = :key',
            ['key' => IndexerQueuer::INDEXER_KEY]
        );
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1776848421, (new Migration1776848421RepairDigitalProductType())->getCreationTimestamp());
    }

    public function testCreationTimestamp(): void
    {
        static::assertSame(1776848421, (new Migration1776848421RepairDigitalProductType())->getCreationTimestamp());
    }

    public function testRepairsProductsLeftAsPhysicalByFaultyPreviousMigration(): void
    {
        $this->insertProduct('faulty-download', 'physical', ['is-download']);
        $this->insertProduct('already-digital', 'digital', ['is-download']);
        $this->insertProduct('physical-without-states', 'physical', null);
        $this->insertProduct('physical-with-other-state', 'physical', ['some-other-state']);

        $migration = new Migration1776848421RepairDigitalProductType();
        $migration->update($this->connection);
        $migration->update($this->connection);

        static::assertSame('digital', $this->fetchType('faulty-download'));
        static::assertSame('digital', $this->fetchType('already-digital'));
        static::assertSame('physical', $this->fetchType('physical-without-states'));
        static::assertSame('physical', $this->fetchType('physical-with-other-state'));
    }

    public function testRegistersProductIndexerWhenProductsWereRepaired(): void
    {
        $this->insertProduct('faulty-download', 'physical', ['is-download']);

        $migration = new Migration1776848421RepairDigitalProductType();
        $migration->update($this->connection);

        $indexers = (new IndexerQueuer($this->connection))->getIndexers();

        static::assertArrayHasKey('product.indexer', $indexers);
        static::assertContains('product.states', $indexers['product.indexer']);
    }

    public function testDoesNotRegisterProductIndexerWhenNothingWasRepaired(): void
    {
        $this->insertProduct('already-digital', 'digital', ['is-download']);
        $this->insertProduct('physical-without-states', 'physical', null);
        $this->insertProduct('physical-with-other-state', 'physical', ['some-other-state']);

        $migration = new Migration1776848421RepairDigitalProductType();
        $migration->update($this->connection);

        $indexers = (new IndexerQueuer($this->connection))->getIndexers();

        static::assertArrayNotHasKey('product.indexer', $indexers);
    }

    public function testSkipsWhenTypeColumnMissing(): void
    {
        $this->dropTypeColumnIfExists();

        $migration = new Migration1776848421RepairDigitalProductType();
        $migration->update($this->connection);

        static::assertFalse(TableHelper::columnExists($this->connection, 'product', 'type'));

        $this->ensureTypeColumnExists();
    }

    private function fetchType(string $key): string
    {
        $type = $this->connection->fetchOne(
            'SELECT `type` FROM `product` WHERE `id` = :id',
            ['id' => $this->ids->getBytes($key)]
        );

        static::assertIsString($type);

        return $type;
    }

    /**
     * @param list<string>|null $states
     */
    private function insertProduct(string $key, string $type, ?array $states): void
    {
        $this->connection->insert('product', [
            'id' => $this->ids->getBytes($key),
            'version_id' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
            'product_number' => $key,
            'stock' => 1,
            'type' => $type,
            'states' => $states === null ? null : json_encode($states, \JSON_THROW_ON_ERROR),
            'created_at' => (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);
    }

    private function ensureStatesColumnExists(): void
    {
        if (TableHelper::columnExists($this->connection, 'product', 'states')) {
            return;
        }

        $this->connection->executeStatement('ALTER TABLE `product` ADD COLUMN `states` JSON NULL');
    }

    private function ensureTypeColumnExists(): void
    {
        if (TableHelper::columnExists($this->connection, 'product', 'type')) {
            return;
        }

        $this->connection->executeStatement(
            'ALTER TABLE `product` ADD COLUMN `type` VARCHAR(32) NOT NULL DEFAULT \'physical\''
        );
    }

    private function dropTypeColumnIfExists(): void
    {
        if (TableHelper::indexExists($this->connection, 'product', 'idx.product.type')) {
            $this->connection->executeStatement('DROP INDEX `idx.product.type` ON `product`');
        }

        if (TableHelper::columnExists($this->connection, 'product', 'type')) {
            $this->connection->executeStatement('ALTER TABLE `product` DROP COLUMN `type`');
        }
    }
}
