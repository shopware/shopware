<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_8;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Types\Types;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Migration\V6_7\Migration1773392535BackfillDownloadAccessForPaidDigitalOrders;

/**
 * @internal
 */
#[CoversClass(Migration1773392535BackfillDownloadAccessForPaidDigitalOrders::class)]
class Migration1773392535BackfillDownloadAccessForPaidDigitalOrdersTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        parent::setUp();

        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(
            1773392535,
            (new Migration1773392535BackfillDownloadAccessForPaidDigitalOrders())->getCreationTimestamp()
        );
    }

    public function testMigrationBackfillsOnlyPaidDigitalDownloads(): void
    {
        $paidStateId = $this->fetchOrderTransactionStateId('paid');
        $openStateId = $this->fetchOrderTransactionStateId('open');
        $paymentMethodId = $this->fetchPaymentMethodId();
        $versionId = Uuid::fromHexToBytes(Defaults::LIVE_VERSION);

        $paidOrderId = Uuid::randomBytes();
        $unpaidOrderId = Uuid::randomBytes();
        $physicalOrderId = Uuid::randomBytes();

        $paidDigitalLineItemId = Uuid::randomBytes();
        $unpaidDigitalLineItemId = Uuid::randomBytes();
        $paidPhysicalLineItemId = Uuid::randomBytes();

        $paidDigitalDownloadId = Uuid::randomBytes();
        $unpaidDigitalDownloadId = Uuid::randomBytes();
        $paidPhysicalDownloadId = Uuid::randomBytes();

        $this->connection->executeStatement('SET FOREIGN_KEY_CHECKS=0');
        try {
            $this->insertOrderLineItem(
                $paidDigitalLineItemId,
                $paidOrderId,
                $versionId,
                ['productType' => 'digital']
            );
            $this->insertOrderLineItem(
                $unpaidDigitalLineItemId,
                $unpaidOrderId,
                $versionId,
                ['productType' => 'digital']
            );
            $this->insertOrderLineItem(
                $paidPhysicalLineItemId,
                $physicalOrderId,
                $versionId,
                ['productType' => 'physical']
            );

            $this->insertOrderTransaction($paidOrderId, $versionId, $paymentMethodId, $paidStateId);
            $this->insertOrderTransaction($unpaidOrderId, $versionId, $paymentMethodId, $openStateId);
            $this->insertOrderTransaction($physicalOrderId, $versionId, $paymentMethodId, $paidStateId);

            $this->insertOrderLineItemDownload($paidDigitalDownloadId, $paidDigitalLineItemId, $versionId, 0);
            $this->insertOrderLineItemDownload($unpaidDigitalDownloadId, $unpaidDigitalLineItemId, $versionId, 0);
            $this->insertOrderLineItemDownload($paidPhysicalDownloadId, $paidPhysicalLineItemId, $versionId, 0);
        } finally {
            $this->connection->executeStatement('SET FOREIGN_KEY_CHECKS=1');
        }

        $migration = new Migration1773392535BackfillDownloadAccessForPaidDigitalOrders();
        $migration->update($this->connection);
        $migration->update($this->connection);

        static::assertSame(1, $this->fetchAccessGranted($paidDigitalDownloadId));
        static::assertSame(0, $this->fetchAccessGranted($unpaidDigitalDownloadId));
        static::assertSame(0, $this->fetchAccessGranted($paidPhysicalDownloadId));

        $this->connection->delete('order_line_item_download', ['id' => $paidDigitalDownloadId], ['id' => Types::BINARY]);
        $this->connection->delete('order_line_item_download', ['id' => $unpaidDigitalDownloadId], ['id' => Types::BINARY]);
        $this->connection->delete('order_line_item_download', ['id' => $paidPhysicalDownloadId], ['id' => Types::BINARY]);

        $this->connection->delete('order_transaction', ['order_id' => $paidOrderId], ['order_id' => Types::BINARY]);
        $this->connection->delete('order_transaction', ['order_id' => $unpaidOrderId], ['order_id' => Types::BINARY]);
        $this->connection->delete('order_transaction', ['order_id' => $physicalOrderId], ['order_id' => Types::BINARY]);

        $this->connection->delete('order_line_item', ['id' => $paidDigitalLineItemId, 'version_id' => $versionId], ['id' => Types::BINARY, 'version_id' => Types::BINARY]);
        $this->connection->delete('order_line_item', ['id' => $unpaidDigitalLineItemId, 'version_id' => $versionId], ['id' => Types::BINARY, 'version_id' => Types::BINARY]);
        $this->connection->delete('order_line_item', ['id' => $paidPhysicalLineItemId, 'version_id' => $versionId], ['id' => Types::BINARY, 'version_id' => Types::BINARY]);
    }

    /**
     * @param array<string, string> $payload
     */
    private function insertOrderLineItem(string $lineItemId, string $orderId, string $versionId, array $payload): void
    {
        $this->connection->insert('order_line_item', [
            'id' => $lineItemId,
            'version_id' => $versionId,
            'order_id' => $orderId,
            'order_version_id' => $versionId,
            'identifier' => Uuid::randomHex(),
            'quantity' => 1,
            'type' => 'product',
            'label' => 'migration-test',
            'payload' => json_encode($payload, \JSON_THROW_ON_ERROR),
            'price' => json_encode([
                'netPrice' => 10.0,
                'taxStatus' => 'gross',
                'totalPrice' => 10.0,
                'positionPrice' => 10.0,
                'calculatedTaxes' => [],
                'taxRules' => [],
            ], \JSON_THROW_ON_ERROR),
            'created_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s.v'),
        ]);
    }

    private function insertOrderTransaction(string $orderId, string $versionId, string $paymentMethodId, string $stateId): void
    {
        $this->connection->insert('order_transaction', [
            'id' => Uuid::randomBytes(),
            'version_id' => $versionId,
            'order_id' => $orderId,
            'order_version_id' => $versionId,
            'state_id' => $stateId,
            'payment_method_id' => $paymentMethodId,
            'amount' => 10.0,
            'created_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s.v'),
        ]);
    }

    private function insertOrderLineItemDownload(string $downloadId, string $lineItemId, string $versionId, int $accessGranted): void
    {
        $this->connection->insert('order_line_item_download', [
            'id' => $downloadId,
            'version_id' => $versionId,
            'position' => 1,
            'access_granted' => $accessGranted,
            'order_line_item_id' => $lineItemId,
            'order_line_item_version_id' => $versionId,
            'media_id' => Uuid::randomBytes(),
            'created_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s.v'),
        ]);
    }

    private function fetchAccessGranted(string $downloadId): int
    {
        return (int) $this->connection->fetchOne(
            'SELECT access_granted FROM order_line_item_download WHERE id = :id',
            ['id' => $downloadId],
            ['id' => Types::BINARY]
        );
    }

    private function fetchOrderTransactionStateId(string $stateTechnicalName): string
    {
        $machineId = (string) $this->connection->fetchOne(
            'SELECT id FROM state_machine WHERE technical_name = :technicalName',
            ['technicalName' => 'order_transaction.state']
        );

        return (string) $this->connection->fetchOne(
            'SELECT id FROM state_machine_state WHERE technical_name = :state AND state_machine_id = :machineId',
            ['state' => $stateTechnicalName, 'machineId' => $machineId]
        );
    }

    private function fetchPaymentMethodId(): string
    {
        return (string) $this->connection->fetchOne('SELECT id FROM payment_method WHERE active = 1 ORDER BY position LIMIT 1');
    }
}
