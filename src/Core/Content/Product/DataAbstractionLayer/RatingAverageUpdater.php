<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\DataAbstractionLayer;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Doctrine\MultiUpdateQueryQueue;
use Shopware\Core\Framework\DataAbstractionLayer\Doctrine\RetryableQuery;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;

#[Package('framework')]
class RatingAverageUpdater
{
    /**
     * @internal
     */
    public function __construct(private readonly Connection $connection)
    {
    }

    /**
     * @param array<string> $ids
     */
    public function update(array $ids, Context $context): void
    {
        if ($ids === []) {
            return;
        }

        $versionId = Uuid::fromHexToBytes($context->getVersionId());

        RetryableQuery::retryable($this->connection, function () use ($ids, $versionId): void {
            $this->connection->executeStatement(
                'UPDATE product SET rating_average = NULL WHERE (parent_id IN (:ids) OR id IN (:ids)) AND version_id = :version AND rating_average IS NOT NULL',
                ['ids' => Uuid::fromHexToBytesList($ids), 'version' => $versionId],
                ['ids' => ArrayParameterType::BINARY]
            );
        });

        $query = $this->connection->createQueryBuilder();
        $query->select(
            'IFNULL(product.parent_id, product.id) as id',
            'AVG(NULLIF(product_review.points, 0)) as average',
        );
        $query->from('product_review');
        $query->innerJoin('product_review', 'product', 'product', 'product.id = product_review.product_id');
        $query->andWhere('product_review.status = 1');
        $query->andWhere('product.id IN (:ids) OR product.parent_id IN (:ids)');
        $query->andWhere('product.version_id = :version');
        $query->setParameter('version', $versionId);
        $query->setParameter('ids', Uuid::fromHexToBytesList($ids), ArrayParameterType::BINARY);
        $query->addGroupBy('IFNULL(product.parent_id, product.id)');

        $updates = new MultiUpdateQueryQueue($this->connection, 'product', chunkSize: 500);
        foreach ($query->executeQuery()->iterateAssociative() as $average) {
            $updates->addUpdate($average['id'], ['rating_average' => $average['average']]);
        }

        $updates->execute(['version_id' => $versionId]);
    }
}
