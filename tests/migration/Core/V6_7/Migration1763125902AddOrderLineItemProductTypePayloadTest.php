<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Types\Types;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Util\Database\TableHelper;
use Shopware\Core\Framework\Util\Json;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Migration\V6_7\Migration1763125902AddOrderLineItemProductTypePayload;

/**
 * @internal
 */
#[CoversClass(Migration1763125902AddOrderLineItemProductTypePayload::class)]
class Migration1763125902AddOrderLineItemProductTypePayloadTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        parent::setUp();

        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1763125902, (new Migration1763125902AddOrderLineItemProductTypePayload())->getCreationTimestamp());
    }

    public function testUpdateSetsDigitalTypeWhenStatesIsDownloadExists(): void
    {
        $this->ensureStatesColumnExists();

        $versionId = Uuid::fromHexToBytes(Defaults::LIVE_VERSION);
        $lineItemId = Uuid::randomBytes();
        $orderId = Uuid::randomBytes();
        $now = new \DateTimeImmutable();

        $this->disableForeignKeyChecks();
        try {
            $this->connection->insert('order_line_item', [
                'id' => $lineItemId,
                'version_id' => $versionId,
                'order_id' => $orderId,
                'order_version_id' => $versionId,
                'identifier' => Uuid::randomHex(),
                'label' => 'downloadable',
                'quantity' => 1,
                'type' => 'product',
                'payload' => Json::encode(new \stdClass()),
                'states' => json_encode(['is-download']),
                'price' => json_encode($this->createPricePayload(), \JSON_THROW_ON_ERROR),
                'created_at' => $now,
            ], [
                'created_at' => Types::DATETIME_IMMUTABLE,
            ]);
        } finally {
            $this->enableForeignKeyChecks();
        }

        $migration = new Migration1763125902AddOrderLineItemProductTypePayload();
        $migration->update($this->connection);
        $migration->update($this->connection);

        $productType = $this->connection->fetchOne(
            'SELECT JSON_UNQUOTE(JSON_EXTRACT(payload, "$.productType")) FROM `order_line_item` WHERE `id` = :id AND `version_id` = :version',
            ['id' => $lineItemId, 'version' => $versionId],
            ['id' => Types::BINARY, 'version' => Types::BINARY],
        );

        static::assertSame('digital', $productType);

        $this->connection->delete('order_line_item', [
            'id' => $lineItemId,
            'version_id' => $versionId,
        ], [
            'id' => Types::BINARY,
            'version_id' => Types::BINARY,
        ]);
    }

    public function testUpdateSetsPhysicalTypeFromStates(): void
    {
        $this->ensureStatesColumnExists();

        $versionId = Uuid::fromHexToBytes(Defaults::LIVE_VERSION);
        $lineItemId = Uuid::randomBytes();
        $orderId = Uuid::randomBytes();
        $now = new \DateTimeImmutable();

        $this->disableForeignKeyChecks();
        try {
            $this->connection->insert('order_line_item', [
                'id' => $lineItemId,
                'version_id' => $versionId,
                'order_id' => $orderId,
                'order_version_id' => $versionId,
                'identifier' => Uuid::randomHex(),
                'label' => 'physical',
                'quantity' => 1,
                'type' => 'product',
                'payload' => Json::encode(new \stdClass()),
                'price' => json_encode($this->createPricePayload(), \JSON_THROW_ON_ERROR),
                'states' => json_encode(['is-physical']),
                'created_at' => $now,
            ], [
                'created_at' => Types::DATETIME_IMMUTABLE,
            ]);
        } finally {
            $this->enableForeignKeyChecks();
        }

        $migration = new Migration1763125902AddOrderLineItemProductTypePayload();
        $migration->update($this->connection);
        $migration->update($this->connection);

        $productType = $this->connection->fetchOne(
            'SELECT JSON_UNQUOTE(JSON_EXTRACT(payload, "$.productType")) FROM `order_line_item` WHERE `id` = :id AND `version_id` = :version',
            ['id' => $lineItemId, 'version' => $versionId],
            ['id' => Types::BINARY, 'version' => Types::BINARY],
        );

        static::assertSame('physical', $productType);

        $this->connection->delete('order_line_item', [
            'id' => $lineItemId,
            'version_id' => $versionId,
        ], [
            'id' => Types::BINARY,
            'version_id' => Types::BINARY,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function createPricePayload(): array
    {
        return [
            'unitPrice' => 10.0,
            'totalPrice' => 10.0,
            'quantity' => 1,
            'calculatedTaxes' => [],
            'taxRules' => [],
        ];
    }

    private function ensureStatesColumnExists(): void
    {
        if (TableHelper::columnExists($this->connection, 'order_line_item', 'states')) {
            return;
        }

        $this->connection->executeStatement('ALTER TABLE `order_line_item` ADD COLUMN `states` JSON NULL');
        $this->connection->executeStatement('ALTER TABLE `order_line_item` ADD CONSTRAINT `json.order_line_item.states` CHECK (JSON_VALID(`states`))');
    }

    private function disableForeignKeyChecks(): void
    {
        $this->connection->executeStatement('SET FOREIGN_KEY_CHECKS=0');
    }

    private function enableForeignKeyChecks(): void
    {
        $this->connection->executeStatement('SET FOREIGN_KEY_CHECKS=1');
    }
}
