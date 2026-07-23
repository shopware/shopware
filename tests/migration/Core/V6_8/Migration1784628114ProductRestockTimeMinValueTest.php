<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_8;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\Doctrine\MultiInsertQueryQueue;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Migration\V6_8\Migration1784628114ProductRestockTimeMinValue;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(Migration1784628114ProductRestockTimeMinValue::class)]
class Migration1784628114ProductRestockTimeMinValueTest extends TestCase
{
    private Connection $connection;

    private string $versionId;

    private string $negativeRestockProductId;

    private string $zeroRestockProductId;

    private string $positiveRestockProductId;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
        $this->versionId = Uuid::fromHexToBytes(Defaults::LIVE_VERSION);
        $this->negativeRestockProductId = Uuid::randomBytes();
        $this->zeroRestockProductId = Uuid::randomBytes();
        $this->positiveRestockProductId = Uuid::randomBytes();
    }

    protected function tearDown(): void
    {
        $this->connection->executeStatement(
            'DELETE FROM `product` WHERE `id` IN (:ids)',
            ['ids' => [$this->negativeRestockProductId, $this->zeroRestockProductId, $this->positiveRestockProductId]],
            ['ids' => ArrayParameterType::BINARY]
        );
    }

    public function testCreationTimestamp(): void
    {
        $migration = new Migration1784628114ProductRestockTimeMinValue();

        static::assertSame(1784628114, $migration->getCreationTimestamp());
    }

    public function testMigration(): void
    {
        $queue = new MultiInsertQueryQueue($this->connection);

        $queue->addInsert('product', [
            'id' => $this->negativeRestockProductId,
            'version_id' => $this->versionId,
            'stock' => 10,
            'restock_time' => -5,
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);

        $queue->addInsert('product', [
            'id' => $this->zeroRestockProductId,
            'version_id' => $this->versionId,
            'stock' => 10,
            'restock_time' => 0,
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);

        $queue->addInsert('product', [
            'id' => $this->positiveRestockProductId,
            'version_id' => $this->versionId,
            'stock' => 10,
            'restock_time' => 3,
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);

        $queue->execute();

        $migration = new Migration1784628114ProductRestockTimeMinValue();

        // make sure the migration is idempotent
        $migration->update($this->connection);
        $migration->update($this->connection);

        $restockTimes = $this->connection->fetchAllKeyValue(
            'SELECT LOWER(HEX(`id`)), `restock_time` FROM `product` WHERE `id` IN (:ids)',
            ['ids' => [$this->negativeRestockProductId, $this->zeroRestockProductId, $this->positiveRestockProductId]],
            ['ids' => ArrayParameterType::BINARY]
        );

        static::assertNull($restockTimes[Uuid::fromBytesToHex($this->negativeRestockProductId)]);
        static::assertSame('0', $restockTimes[Uuid::fromBytesToHex($this->zeroRestockProductId)]);
        static::assertSame('3', $restockTimes[Uuid::fromBytesToHex($this->positiveRestockProductId)]);
    }
}
