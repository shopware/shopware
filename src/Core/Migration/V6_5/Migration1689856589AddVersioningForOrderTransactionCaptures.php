<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_5;

use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Uuid\Uuid;

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
        // adjusting "order_transaction_capture" table
        $this->addBinaryColumnToTable($connection, 'version_id', 'order_transaction_capture');
        $this->setLiveVersionValueOnNewVersionColumn($connection, 'order_transaction_capture', 'version_id');
        $this->reAddVersionedPrimaryKey($connection, 'order_transaction_capture');

        // adjusting "order_transaction_capture_refund" table
        $this->addBinaryColumnToTable($connection, 'version_id', 'order_transaction_capture_refund');
        $this->setLiveVersionValueOnNewVersionColumn($connection, 'order_transaction_capture_refund', 'version_id');
        $this->reAddVersionedPrimaryKey($connection, 'order_transaction_capture_refund');

        $this->addBinaryColumnToTable($connection, 'capture_version_id', 'order_transaction_capture_refund');
        $this->setLiveVersionValueOnNewVersionColumn($connection, 'order_transaction_capture_refund', 'capture_version_id');
        $this->reAddVersionedForeignKey($connection, 'order_transaction_capture_refund', 'order_transaction_capture', 'capture');

        // adjusting "order_transaction_capture_refund_position" table
        $this->addBinaryColumnToTable($connection, 'version_id', 'order_transaction_capture_refund_position');
        $this->setLiveVersionValueOnNewVersionColumn($connection, 'order_transaction_capture_refund_position', 'version_id');
        $this->reAddVersionedPrimaryKey($connection, 'order_transaction_capture_refund_position');

        $this->addBinaryColumnToTable($connection, 'refund_version_id', 'order_transaction_capture_refund_position');
        $this->setLiveVersionValueOnNewVersionColumn($connection, 'order_transaction_capture_refund_position', 'refund_version_id');
        $this->reAddVersionedForeignKey($connection, 'order_transaction_capture_refund_position', 'order_transaction_capture_refund', 'refund');
    }

    private function reAddVersionedPrimaryKey(Connection $connection, string $tableName): void
    {
        $sqlStatement = sprintf('
            ALTER TABLE `%s`
            DROP PRIMARY KEY,
            ADD PRIMARY KEY (`id`, `version_id`);
        ', $tableName);
        $connection->executeStatement($sqlStatement);
    }

    private function addBinaryColumnToTable(Connection $connection, string $newColumnName, string $tableName): void
    {
        $this->addColumn(
            connection: $connection,
            table: $tableName,
            column: $newColumnName,
            type: 'BINARY(16)'
        );
    }

    private function reAddVersionedForeignKey(Connection $connection, string $tableName, string $referencedTableName, string $foreignKeyColumnSuffix): void
    {
        $foreignKeyName = sprintf('fk.%s.%s_id', $tableName, $foreignKeyColumnSuffix);

        $connection->executeStatement(
            sprintf('ALTER TABLE `%s` DROP FOREIGN KEY `%s`;', $tableName, $foreignKeyName)
        );

        $connection->executeStatement(
            sprintf('ALTER TABLE `%s` DROP INDEX `%s`;', $tableName, $foreignKeyName)
        );

        $addForeignKeySqlStatement = sprintf('
            ALTER TABLE `%s`
            ADD CONSTRAINT `%s` FOREIGN KEY (`%s_id`, `%s_version_id`)
                REFERENCES `%s` (`id`, `version_id`) ON DELETE CASCADE ON UPDATE CASCADE
        ', $tableName, $foreignKeyName, $foreignKeyColumnSuffix, $foreignKeyColumnSuffix, $referencedTableName);

        $connection->executeStatement($addForeignKeySqlStatement);
    }

    private function setLiveVersionValueOnNewVersionColumn(Connection $connection, string $tableName, string $versionColumn): void
    {
        $liveVersionUpdateSqlStatement = $connection->prepare(
            sprintf('UPDATE `%s` SET %s = :liveVersionId WHERE %s = :emptyVersionId', $tableName, $versionColumn, $versionColumn)
        );

        $liveVersionUpdateSqlStatement->executeStatement([
            'liveVersionId' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
            'emptyVersionId' => Uuid::fromHexToBytes('00000000000000000000000000000000'),
        ]);
    }
}
