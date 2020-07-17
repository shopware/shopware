<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\DataAbstractionLayer;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\DeadlockException;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Doctrine\FetchModeHelper;
use Shopware\Core\Framework\DataAbstractionLayer\Doctrine\MultiInsertQueryQueue;
use Shopware\Core\Framework\DataAbstractionLayer\Doctrine\RetryableQuery;
use Shopware\Core\Framework\Uuid\Uuid;

class ProductCategoryDenormalizer
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

        $ids = array_unique($ids);

        $categories = $this->fetchMapping($ids, $context);

        $versionId = Uuid::fromHexToBytes($context->getVersionId());
        $liveVersionId = Uuid::fromHexToBytes(Defaults::LIVE_VERSION);

        $update = new RetryableQuery(
            $this->connection->prepare('UPDATE product SET category_tree = :tree WHERE id = :id AND version_id = :version')
        );

        $delete = new RetryableQuery(
            $this->connection->prepare('DELETE FROM `product_category_tree` WHERE `product_id` = :id AND `product_version_id` = :version')
        );

        $inserts = [];
        foreach ($categories as $productId => $mapping) {
            $productId = Uuid::fromHexToBytes($productId);

            $categoryIds = $this->mapCategories($mapping);

            $json = null;
            if (!empty($categoryIds)) {
                $json = json_encode($categoryIds);
            }

            $params = ['id' => $productId, 'tree' => $json, 'version' => $versionId];

            $update->execute($params);

            $delete->execute(['id' => $productId, 'version' => $versionId]);

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

        $this->insertTree($inserts);
    }

    private function insertTree(array $inserts): void
    {
        if (empty($inserts)) {
            return;
        }

        try {
            $queue = new MultiInsertQueryQueue($this->connection, 250);
            foreach ($inserts as $insert) {
                $queue->addInsert('product_category_tree', $insert);
            }
            $queue->execute();
        } catch (DeadlockException $e) {
            $query = new RetryableQuery(
                $this->connection->prepare('
                    INSERT IGNORE INTO product_category_tree
                        (`product_id`, `product_version_id`, `category_id`, `category_version_id`)
                    VALUES
                        (:product_id, :product_version_id, :category_id, :category_version_id)
                ')
            );

            foreach ($inserts as $insert) {
                $query->execute($insert);
            }
        }
    }

    private function fetchMapping(array $ids, Context $context): array
    {
        $query = $this->connection->createQueryBuilder();
        $query->select([
            'LOWER(HEX(product.id)) as product_id',
            "GROUP_CONCAT(category.path SEPARATOR '') as paths",
            "GROUP_CONCAT(LOWER(HEX(category.id)) SEPARATOR '|') as ids",
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

        $query->andWhere('product.id IN (:ids) OR product.parent_id IN (:ids)');
        $query->andWhere('product.version_id = :version');

        $query->setParameter('version', Uuid::fromHexToBytes($context->getVersionId()));
        $query->setParameter('live', Uuid::fromHexToBytes(Defaults::LIVE_VERSION));

        $bytes = array_map(function (string $id) {
            return Uuid::fromHexToBytes($id);
        }, $ids);

        $query->setParameter('ids', $bytes, Connection::PARAM_STR_ARRAY);

        $rows = $query->execute()->fetchAll();

        return FetchModeHelper::groupUnique($rows);
    }

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
