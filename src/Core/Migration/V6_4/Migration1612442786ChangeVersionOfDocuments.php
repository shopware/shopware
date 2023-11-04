<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_4;

use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('core')]
class Migration1612442786ChangeVersionOfDocuments extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1612442786;
    }

    public function update(Connection $connection): void
    {
        /** Get all documents with live version */
        $wrongVersionedDocuments = $this->getWrongVersionedDocuments($connection);

        foreach ($wrongVersionedDocuments as $wrongVersionedDocument) {
            /** get the order version which was created nearest before the document creation */
            $orders = $this->getOrders(
                $connection,
                $wrongVersionedDocument['order_id'],
                $wrongVersionedDocument['created_at']
            );

            if (\is_array($orders) && \count($orders) === 1) {
                /* Update the document version with the version of the order */
                $this->updateDocument($connection, $orders[0]['version_id'], $wrongVersionedDocument['id']);
            } else {
                /** if no order prior to the document creation was found, get last version of order */
                $orders = $this->getOrders(
                    $connection,
                    $wrongVersionedDocument['order_id']
                );

                if (\is_array($orders) && \count($orders) === 1) {
                    /* Update the document version with the version of the order */
                    $this->updateDocument($connection, $orders[0]['version_id'], $wrongVersionedDocument['id']);
                }
            }
        }
    }

    public function updateDestructive(Connection $connection): void
    {
        // Nothing to do here
    }

    /**
     * @return mixed[]
     */
    private function getWrongVersionedDocuments(Connection $connection): array
    {
        return $connection->fetchAllAssociative(
            'SELECT * FROM `document`
            WHERE `document`.`order_version_id` = :liveVersion',
            ['liveVersion' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION)]
        );
    }

    /**
     * @return list<array{version_id: string}>
     */
    private function getOrders(Connection $connection, string $orderId, ?string $createdAt = null): array
    {
        $orderQuery = 'SELECT version_id FROM `order`
                WHERE `order`.`id` = :orderId AND
                    `order`.`version_id` != :liveVersion';
        $params = [
            'orderId' => $orderId,
            'liveVersion' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
        ];

        if ($createdAt) {
            $orderQuery .= ' AND
                    `order`.`created_at` <= :createdAtDoc';
            $params['createdAtDoc'] = $createdAt;
        }

        $orderQuery .= ' ORDER BY created_at DESC
                LIMIT 1';

        /** @var list<array{version_id: string}> $orders */
        $orders = $connection->fetchAllAssociative($orderQuery, $params);

        return $orders;
    }

    private function updateDocument(Connection $connection, string $versionId, string $wrongVersionedDocumentId): void
    {
        $connection->executeStatement(
            'UPDATE document SET `order_version_id` = :orderVersionId WHERE `id` = :documentId',
            [
                'orderVersionId' => $versionId,
                'documentId' => $wrongVersionedDocumentId,
            ]
        );
    }
}
