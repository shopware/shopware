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
        $connection->executeStatement('
            ALTER TABLE `order_transaction_capture`
            ADD COLUMN `version_id` BINARY(16) NOT NULL AFTER `id`,
            DROP PRIMARY KEY,
            ADD PRIMARY KEY (`id`, `version_id`);
        ');

        $liveVersionUuid = Uuid::fromHexToBytes(Defaults::LIVE_VERSION);

        // for all existing order_transaction_capture - set live version ID
        $liveVersionSetOnCaptureStatement = $connection->prepare('UPDATE `order_transaction_capture` SET version_id = :versionId');
        $liveVersionSetOnCaptureStatement->executeStatement([
            'versionId' => $liveVersionUuid,
        ]);

        $connection->executeStatement('
            ALTER TABLE `order_transaction_capture_refund`
            ADD COLUMN `version_id` BINARY(16) NOT NULL AFTER `id`,
            ADD COLUMN `capture_version_id` BINARY(16) NOT NULL AFTER `capture_id`,
            DROP PRIMARY KEY,
            ADD PRIMARY KEY (`id`, `version_id`),
            DROP FOREIGN KEY `fk.order_transaction_capture_refund.capture_id`;
        ');

        // for all existing order_transaction_capture_refund - set live version ID
        $liveVersionSetOnCaptureRefundStatement = $connection->prepare(
            'UPDATE `order_transaction_capture_refund` SET version_id = :versionId, capture_version_id = :captureVersionId'
        );
        $liveVersionSetOnCaptureRefundStatement->executeStatement([
            'versionId' => $liveVersionUuid,
            'captureVersionId' => $liveVersionUuid,
        ]);

        // re-add foreign key once relation can be set using version_id
        $connection->executeStatement('
            ALTER TABLE `order_transaction_capture_refund`
            ADD CONSTRAINT `fk.order_transaction_capture_refund.capture_id` FOREIGN KEY (`capture_id`, `capture_version_id`)
                REFERENCES `order_transaction_capture` (`id`, `version_id`) ON DELETE CASCADE ON UPDATE CASCADE
        ');

        $connection->executeStatement('
            ALTER TABLE `order_transaction_capture_refund_position`
            ADD COLUMN `version_id` BINARY(16) NOT NULL AFTER `id`,
            ADD COLUMN `refund_version_id` BINARY(16) NOT NULL AFTER `refund_id`,
            DROP PRIMARY KEY,
            ADD PRIMARY KEY (`id`, `version_id`),
            DROP FOREIGN KEY `fk.order_transaction_capture_refund_position.refund_id`;
        ');

        // for all existing order_transaction_capture_refund_position - set live version ID
        $liveVersionSetOnCaptureRefundPositionStatement = $connection->prepare('
            UPDATE `order_transaction_capture_refund_position`
                SET version_id = :versionId, refund_version_id = :refundVersionId
        ');
        $liveVersionSetOnCaptureRefundPositionStatement->executeStatement([
            'versionId' => $liveVersionUuid,
            'refundVersionId' => $liveVersionUuid,
        ]);

        // re-add foreign key once relation can be set using version_id
        $connection->executeStatement('
            ALTER TABLE `order_transaction_capture_refund_position`
            ADD CONSTRAINT `fk.order_transaction_capture_refund_position.refund_id` FOREIGN KEY (`refund_id`, `refund_version_id`)
                REFERENCES `order_transaction_capture_refund` (`id`, `version_id`) ON DELETE CASCADE ON UPDATE CASCADE
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
