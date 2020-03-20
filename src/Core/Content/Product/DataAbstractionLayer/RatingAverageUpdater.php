<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\DataAbstractionLayer;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Doctrine\RetryableQuery;
use Shopware\Core\Framework\Uuid\Uuid;

class RatingAverageUpdater
{
    /**
     * @var Connection
     */
    private $connection;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    public function update(array $ids, Context $context): void
    {
        if (empty($ids)) {
            return;
        }

        $versionId = Uuid::fromHexToBytes($context->getVersionId());

        RetryableQuery::retryable(function () use ($ids, $versionId): void {
            $this->connection->executeUpdate(
                'UPDATE product SET rating_average = NULL WHERE (parent_id IN (:ids) OR id IN (:ids)) AND version_id = :version',
                ['ids' => Uuid::fromHexToBytesList($ids), 'version' => $versionId],
                ['ids' => Connection::PARAM_STR_ARRAY]
            );
        });

        $query = $this->connection->createQueryBuilder();
        $query->select([
            'IFNULL(product.parent_id, product.id) as id',
            'AVG(product_review.points) as average',
        ]);
        $query->from('product_review');
        $query->leftJoin('product_review', 'product', 'product', 'product.id = product_review.product_id OR product.parent_id = product_review.product_id');
        $query->andWhere('product_review.status = 1');
        $query->andWhere('product.id IN (:ids) OR product.parent_id IN (:ids)');
        $query->andWhere('product.version_id = :version');
        $query->setParameter('version', $versionId);
        $query->setParameter('ids', Uuid::fromHexToBytesList($ids), Connection::PARAM_STR_ARRAY);
        $query->addGroupBy('IFNULL(product.parent_id, product.id)');

        $averages = $query->execute()->fetchAll();

        $query = new RetryableQuery(
            $this->connection->prepare('UPDATE product SET rating_average = :average WHERE id = :id AND version_id = :version')
        );

        foreach ($averages as $average) {
            $query->execute([
                'average' => $average['average'],
                'id' => $average['id'],
                'version' => $versionId,
            ]);
        }
    }
}
