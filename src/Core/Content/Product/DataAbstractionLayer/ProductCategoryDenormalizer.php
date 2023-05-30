<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\DataAbstractionLayer;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Doctrine\FetchModeHelper;
use Shopware\Core\Framework\DataAbstractionLayer\Doctrine\MultiInsertQueryQueue;
use Shopware\Core\Framework\DataAbstractionLayer\Doctrine\RetryableTransaction;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;

#[Package('core')]
class ProductCategoryDenormalizer
{
    /**
     * @internal
     */
    public function __construct(private readonly Connection $connection)
    {
    }

    /**
     * @param array<int, string> $ids
     */
    public function update(array $ids, Context $context): void
    {
        $ids = array_unique(\array_filter($ids));
        $allIds = [];

        if (empty($ids)) {
            return;
        }

        $categories = $this->fetchMapping($ids, $context);

        $versionId = Uuid::fromHexToBytes($context->getVersionId());
        $liveVersionId = Uuid::fromHexToBytes(Defaults::LIVE_VERSION);

        $inserts = [];
        $updates = [];
        foreach ($categories as $productId => $mapping) {
            $productId = Uuid::fromHexToBytes($productId);
            $allIds[] = $productId;
            $categoryIds = $this->mapCategories($mapping);

            $json = null;
            if (!empty($categoryIds)) {
                $json = json_encode($categoryIds, \JSON_THROW_ON_ERROR);
            }

            $updates[] = ['id' => $productId, 'tree' => $json, 'version' => $versionId];

            if (empty($categoryIds)) {
                continue;
            }

            foreach ($categoryIds as $id) {
                $inserts[] = [
                    'product_id' => $productId,
                    'product_version_id' => $versionId,
                    'category_id' => Uuid::fromHexToBytes($id),
                    'category_version_id' => $liveVersionId,
                ];
            }
        }

        RetryableTransaction::retryable($this->connection, function () use ($allIds, $versionId): void {
            $this->connection->executeStatement(
                'DELETE FROM product_category_tree WHERE `product_id` IN (:ids) AND `product_version_id` = :version',
                ['ids' => $allIds, 'version' => $versionId],
                ['ids' => ArrayParameterType::STRING]
            );
        });

        RetryableTransaction::retryable($this->connection, function () use ($updates): void {
            $query = $this->connection->prepare('UPDATE product SET category_tree = :tree WHERE id = :id AND version_id = :version');

            foreach ($updates as $update) {
                $query->executeStatement($update);
            }
        });

        $this->insertTree($inserts);
    }

    /**
     * @param array<array<string, string>> $inserts
     */
    private function insertTree(array $inserts): void
    {
        if (empty($inserts)) {
            return;
        }

        $queue = new MultiInsertQueryQueue($this->connection, 250, true);
        foreach ($inserts as $insert) {
            $queue->addInsert('product_category_tree', $insert);
        }
        $queue->execute();
    }

    /**
     * @param array<int, string> $ids
     *
     * @return array<string, array<string, string>>
     */
    private function fetchMapping(array $ids, Context $context): array
    {
        $query = $this->connection->createQueryBuilder();
        $query->select([
            'LOWER(HEX(product.id)) as product_id',
            'GROUP_CONCAT(category.path SEPARATOR \'\') as paths',
            'GROUP_CONCAT(LOWER(HEX(category.id)) SEPARATOR \'|\') as ids',
        ]);
        $query->from('product');
        $query->leftJoin(
            'product',
            'product_category',
            'mapping',
            'mapping.product_id = product.categories AND mapping.product_version_id = product.version_id'
        );
        $query->leftJoin(
            'mapping',
            'category',
            'category',
            'mapping.category_id = category.id AND mapping.category_version_id = category.version_id AND mapping.category_version_id = :live'
        );

        $query->addGroupBy('product.id');

        $query->andWhere('product.categories IN (:ids)');
        $query->andWhere('product.version_id = :version');

        $query->setParameter('version', Uuid::fromHexToBytes($context->getVersionId()));
        $query->setParameter('live', Uuid::fromHexToBytes(Defaults::LIVE_VERSION));

        $bytes = array_map(fn (string $id) => Uuid::fromHexToBytes($id), $ids);

        $query->setParameter('ids', $bytes, ArrayParameterType::STRING);

        $rows = $query->executeQuery()->fetchAllAssociative();

        return FetchModeHelper::groupUnique($rows);
    }

    /**
     * @param array<string, string|null> $mapping
     *
     * @return array<int, string>
     */
    private function mapCategories(array $mapping): array
    {
        $categoryIds = array_filter(explode('|', (string) $mapping['ids']));
        $categoryIds = array_merge(
            explode('|', (string) $mapping['paths']),
            $categoryIds
        );

        $categoryIds = array_map('strtolower', $categoryIds);

        return array_keys(array_flip(array_filter($categoryIds)));
    }
}
