<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_5;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('checkout')]
class Migration1689856589AddVersioningForOrderTransactionCaptures extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1689856589;
    }

    public function update(Connection $connection): void
    {
        $this->addColumn($connection, 'order_transaction_capture', 'version_id', 'BINARY(16)', true, '0x0fa91ce3e96a4bc2be4bd9ce752c3425');
        $this->addColumn($connection, 'order_transaction_capture_refund', 'version_id', 'BINARY(16)', true, '0x0fa91ce3e96a4bc2be4bd9ce752c3425');
        $this->addColumn($connection, 'order_transaction_capture_refund', 'capture_version_id', 'BINARY(16)', true, '0x0fa91ce3e96a4bc2be4bd9ce752c3425');
        $this->addColumn($connection, 'order_transaction_capture_refund_position', 'version_id', 'BINARY(16)', true, '0x0fa91ce3e96a4bc2be4bd9ce752c3425');
        $this->addColumn($connection, 'order_transaction_capture_refund_position', 'refund_version_id', 'BINARY(16)', true, '0x0fa91ce3e96a4bc2be4bd9ce752c3425');

        $this->dropForeignKeyIfExists($connection, 'order_transaction_capture_refund', 'fk.order_transaction_capture_refund.capture_id');
        $this->dropIndexIfExists($connection, 'order_transaction_capture_refund', 'fk.order_transaction_capture_refund.capture_id');
        $this->dropForeignKeyIfExists($connection, 'order_transaction_capture_refund_position', 'fk.order_transaction_capture_refund_position.refund_id');
        $this->dropIndexIfExists($connection, 'order_transaction_capture_refund_position', 'fk.order_transaction_capture_refund_position.refund_id');

        $this->reAddVersionedPrimaryKey($connection, 'order_transaction_capture');
        $this->reAddVersionedPrimaryKey($connection, 'order_transaction_capture_refund');
        $this->reAddVersionedPrimaryKey($connection, 'order_transaction_capture_refund_position');

        $connection->executeStatement('
            ALTER TABLE `order_transaction_capture_refund`
                ADD CONSTRAINT `fk.order_transaction_capture_refund.capture_id` FOREIGN KEY (`capture_id`, `capture_version_id`)
                    REFERENCES `order_transaction_capture` (`id`, `version_id`) ON DELETE CASCADE ON UPDATE CASCADE
        ');

        $connection->executeStatement('
            ALTER TABLE `order_transaction_capture_refund_position`
                ADD CONSTRAINT `fk.order_transaction_capture_refund_position.refund_id` FOREIGN KEY (`refund_id`, `refund_version_id`)
                    REFERENCES `order_transaction_capture_refund` (`id`, `version_id`) ON DELETE CASCADE ON UPDATE CASCADE
        ');
    }

    private function reAddVersionedPrimaryKey(Connection $connection, string $tableName): void
    {
        $sqlStatement = \sprintf(
            'ALTER TABLE `%s`
             DROP PRIMARY KEY,
             ADD PRIMARY KEY (`id`, `version_id`);',
            $tableName
        );
        $connection->executeStatement($sqlStatement);
    }
}
