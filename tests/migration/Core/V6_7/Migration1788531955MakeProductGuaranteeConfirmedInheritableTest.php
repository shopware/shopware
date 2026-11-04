<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\Doctrine\MultiInsertQueryQueue;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Util\Database\TableHelper;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Migration\V6_7\Migration1788531955MakeProductGuaranteeConfirmedInheritable;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(Migration1788531955MakeProductGuaranteeConfirmedInheritable::class)]
class Migration1788531955MakeProductGuaranteeConfirmedInheritableTest extends TestCase
{
    private Connection $connection;

    private string $versionId;

    private string $parentId;

    private string $untouchedVariantId;

    private string $confirmedVariantId;

    private string $singleProductId;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
        $this->versionId = Uuid::fromHexToBytes(Defaults::LIVE_VERSION);
        $this->parentId = Uuid::randomBytes();
        $this->untouchedVariantId = Uuid::randomBytes();
        $this->confirmedVariantId = Uuid::randomBytes();
        $this->singleProductId = Uuid::randomBytes();
    }

    protected function tearDown(): void
    {
        $this->connection->executeStatement(
            'DELETE FROM `product` WHERE `id` IN (:ids)',
            ['ids' => [$this->parentId, $this->untouchedVariantId, $this->confirmedVariantId, $this->singleProductId]],
            ['ids' => ArrayParameterType::BINARY]
        );

        // DDL is not rolled back, so leave the column as a fully migrated database has it.
        (new Migration1788531955MakeProductGuaranteeConfirmedInheritable())->update($this->connection);
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(
            1788531955,
            (new Migration1788531955MakeProductGuaranteeConfirmedInheritable())->getCreationTimestamp()
        );
    }

    public function testMigrationMakesGuaranteeConfirmedNullable(): void
    {
        $this->restoreColumnAsAddedByGaranLabelMigration();

        static::assertTrue(TableHelper::getColumnOfTable($this->connection, 'product', 'guarantee_confirmed')->isNotNull);

        $migration = new Migration1788531955MakeProductGuaranteeConfirmedInheritable();
        $migration->update($this->connection);
        $migration->update($this->connection);

        $column = TableHelper::getColumnOfTable($this->connection, 'product', 'guarantee_confirmed');

        static::assertFalse($column->isNotNull);
        static::assertNull($column->defaultValue);
    }

    public function testMigrationLetsVariantsWithoutOwnValueFallBackToTheParent(): void
    {
        $this->restoreColumnAsAddedByGaranLabelMigration();
        $this->createProducts();

        $migration = new Migration1788531955MakeProductGuaranteeConfirmedInheritable();
        $migration->update($this->connection);
        $migration->update($this->connection);

        $values = $this->connection->fetchAllKeyValue(
            'SELECT LOWER(HEX(`id`)), `guarantee_confirmed` FROM `product` WHERE `id` IN (:ids)',
            ['ids' => [$this->parentId, $this->untouchedVariantId, $this->confirmedVariantId, $this->singleProductId]],
            ['ids' => ArrayParameterType::BINARY]
        );

        static::assertNull($values[Uuid::fromBytesToHex($this->untouchedVariantId)]);
        static::assertSame('1', $values[Uuid::fromBytesToHex($this->confirmedVariantId)]);
        static::assertSame('1', $values[Uuid::fromBytesToHex($this->parentId)]);
        static::assertSame('0', $values[Uuid::fromBytesToHex($this->singleProductId)]);
    }

    /**
     * Migration1783944800AddGaranLabel added the column as NOT NULL DEFAULT 0, which is the state
     * this migration corrects. How far the shared test database has been migrated is unknown, so
     * recreate that state explicitly.
     */
    private function restoreColumnAsAddedByGaranLabelMigration(): void
    {
        if (TableHelper::columnExists($this->connection, 'product', 'guarantee_confirmed')) {
            $this->connection->executeStatement('ALTER TABLE `product` DROP COLUMN `guarantee_confirmed`');
        }

        $this->connection->executeStatement('ALTER TABLE `product` ADD COLUMN `guarantee_confirmed` TINYINT(1) NOT NULL DEFAULT \'0\'');
    }

    private function createProducts(): void
    {
        $this->connection->insert('product', [
            'id' => $this->parentId,
            'version_id' => $this->versionId,
            'stock' => 10,
            'guarantee_confirmed' => 1,
            'created_at' => (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);

        $queue = new MultiInsertQueryQueue($this->connection);

        $queue->addInsert('product', [
            'id' => $this->untouchedVariantId,
            'version_id' => $this->versionId,
            'parent_id' => $this->parentId,
            'parent_version_id' => $this->versionId,
            'stock' => 10,
            'guarantee_confirmed' => 0,
            'created_at' => (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);

        $queue->addInsert('product', [
            'id' => $this->confirmedVariantId,
            'version_id' => $this->versionId,
            'parent_id' => $this->parentId,
            'parent_version_id' => $this->versionId,
            'stock' => 10,
            'guarantee_confirmed' => 1,
            'created_at' => (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);

        $queue->addInsert('product', [
            'id' => $this->singleProductId,
            'version_id' => $this->versionId,
            'stock' => 10,
            'guarantee_confirmed' => 0,
            'created_at' => (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);

        $queue->execute();
    }
}
