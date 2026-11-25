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

#[Package('framework')]
class ProductCategoryDenormalizer
{
    /**
     * Longer id lists can turn the plan of a locking statement into a scan that locks other products' rows
     */
    private const BATCH_SIZE = 250;

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

        if ($ids === []) {
            return;
        }

        $versionId = Uuid::fromHexToBytes($context->getVersionId());

        $productIds = $this->fetchProductIds($ids, $versionId);
        if ($productIds === []) {
            return;
        }

        // Other connections keep seeing the old tree until the commit. Locking the products before reading makes
        // rewrites of one variant family wait for each other, so each one compares against the latest categories.
        RetryableTransaction::retryable($this->connection, function () use ($ids, $productIds, $versionId, $context): void {
            $this->lockProducts($productIds, $versionId);

            $categories = $this->fetchMapping($ids, $context);
            if ($categories === []) {
                return;
            }

            $this->writeChanges($categories, $this->fetchStoredTree(array_keys($categories), $versionId), $versionId);
        });
    }

    /**
     * @param array<string, array<string, string|null>> $categories
     * @param array<string, list<string>> $storedTree
     */
    private function writeChanges(array $categories, array $storedTree, string $versionId): void
    {
        $liveVersionId = Uuid::fromHexToBytes(Defaults::LIVE_VERSION);

        // products by their new tree, as all variants that inherit the categories get the same one
        $updates = [];
        $deletes = [];
        $inserts = [];
        foreach ($categories as $productId => $mapping) {
            $categoryIds = $this->mapCategories($mapping);
            $storedIds = $storedTree[$productId] ?? [];
            $productBytes = Uuid::fromHexToBytes($productId);

            if (!$this->isTreeUnchanged($mapping['category_tree'] ?? null, $categoryIds)) {
                $updates[$categoryIds === [] ? '' : json_encode($categoryIds, \JSON_THROW_ON_ERROR)][] = $productBytes;
            }

            $removed = array_values(array_diff($storedIds, $categoryIds));
            if ($removed !== []) {
                sort($removed);
                $key = implode('|', $removed);
                $deletes[$key] ??= ['categoryIds' => Uuid::fromHexToBytesList($removed), 'productIds' => []];
                $deletes[$key]['productIds'][] = $productBytes;
            }

            foreach (array_diff($categoryIds, $storedIds) as $categoryId) {
                $inserts[] = [
                    'product_id' => $productBytes,
                    'product_version_id' => $versionId,
                    'category_id' => Uuid::fromHexToBytes($categoryId),
                    'category_version_id' => $liveVersionId,
                ];
            }
        }

        foreach ($updates as $tree => $productIds) {
            foreach (array_chunk($productIds, self::BATCH_SIZE) as $chunk) {
                $this->connection->executeStatement(
                    'UPDATE product SET category_tree = :tree WHERE id IN (:ids) AND version_id = :version',
                    ['tree' => $tree === '' ? null : $tree, 'ids' => $chunk, 'version' => $versionId],
                    ['ids' => ArrayParameterType::BINARY]
                );
            }
        }

        foreach ($deletes as $delete) {
            foreach (array_chunk($delete['productIds'], self::BATCH_SIZE) as $chunk) {
                $this->connection->executeStatement(
                    'DELETE FROM product_category_tree WHERE product_id IN (:ids) AND product_version_id = :version AND category_id IN (:categoryIds)',
                    ['ids' => $chunk, 'version' => $versionId, 'categoryIds' => $delete['categoryIds']],
                    ['ids' => ArrayParameterType::BINARY, 'categoryIds' => ArrayParameterType::BINARY]
                );
            }
        }

        $this->insertTree($inserts);
    }

    /**
     * @param list<string> $categoryIds
     */
    private function isTreeUnchanged(?string $storedTree, array $categoryIds): bool
    {
        $stored = $storedTree === null ? [] : json_decode($storedTree, true);
        if (!\is_array($stored)) {
            return false;
        }

        sort($stored);
        sort($categoryIds);

        return $stored === $categoryIds;
    }

    /**
     * @param array<int, string> $ids
     *
     * @return list<string>
     */
    private function fetchProductIds(array $ids, string $versionId): array
    {
        /** @var list<string> $productIds */
        $productIds = $this->connection->fetchFirstColumn(
            'SELECT id FROM product WHERE (id IN (:ids) OR parent_id IN (:ids)) AND categories IN (:ids) AND version_id = :version',
            ['ids' => Uuid::fromHexToBytesList($ids), 'version' => $versionId],
            ['ids' => ArrayParameterType::BINARY]
        );

        return $productIds;
    }

    /**
     * Sorted like in ProductStreamUpdater, so the two never wait for each other in a circle.
     *
     * @param list<string> $productIds
     */
    private function lockProducts(array $productIds, string $versionId): void
    {
        sort($productIds);

        foreach (array_chunk($productIds, self::BATCH_SIZE) as $chunk) {
            $this->connection->fetchFirstColumn(
                'SELECT id FROM product WHERE id IN (:ids) AND version_id = :version ORDER BY id FOR UPDATE',
                ['ids' => $chunk, 'version' => $versionId],
                ['ids' => ArrayParameterType::BINARY]
            );
        }
    }

    /**
     * @param list<string> $productIds
     *
     * @return array<string, list<string>>
     */
    private function fetchStoredTree(array $productIds, string $versionId): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT LOWER(HEX(product_id)) AS product_id, LOWER(HEX(category_id)) AS category_id
             FROM product_category_tree
             WHERE product_id IN (:ids) AND product_version_id = :version',
            ['ids' => Uuid::fromHexToBytesList($productIds), 'version' => $versionId],
            ['ids' => ArrayParameterType::BINARY]
        );

        $tree = [];
        foreach ($rows as $row) {
            $tree[(string) $row['product_id']][] = (string) $row['category_id'];
        }

        return $tree;
    }

    /**
     * @param array<array<string, string>> $inserts
     */
    private function insertTree(array $inserts): void
    {
        if ($inserts === []) {
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
     * @return array<string, array<string, string|null>>
     */
    private function fetchMapping(array $ids, Context $context): array
    {
        $query = $this->connection->createQueryBuilder();
        $query->select(
            'LOWER(HEX(product.id)) as product_id',
            'GROUP_CONCAT(category.path SEPARATOR \'\') as paths',
            'GROUP_CONCAT(LOWER(HEX(category.id)) SEPARATOR \'|\') as ids',
            'product.category_tree',
        );
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

        // Performance optimization: Reduce the number of products to check by filtering for products via indexed columns
        $query->andWhere('product.id IN (:ids) OR product.parent_id IN (:ids)');

        // This where condition alone leads to a full table scan, which will be very slow on large datasets,
        // therefore we already filter for product.id and product.parent_id above (which is indexed and therefore much faster)
        // product.categories will only ever contain the product id (for non-variant products, or when categories are not inherited)
        // or the parent id (for variant products inheriting categories from their parent)
        $query->andWhere('product.categories IN (:ids)');

        $query->andWhere('product.version_id = :version');

        $query->setParameter('version', Uuid::fromHexToBytes($context->getVersionId()));
        $query->setParameter('live', Uuid::fromHexToBytes(Defaults::LIVE_VERSION));

        $bytes = array_map(static fn (string $id) => Uuid::fromHexToBytes($id), $ids);

        $query->setParameter('ids', $bytes, ArrayParameterType::BINARY);

        $rows = $query->executeQuery()->fetchAllAssociative();

        /** @var array<string, array<string, string|null>> $unique */
        $unique = FetchModeHelper::groupUnique($rows);

        return $unique;
    }

    /**
     * @param array<string, string|null> $mapping
     *
     * @return list<string>
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
