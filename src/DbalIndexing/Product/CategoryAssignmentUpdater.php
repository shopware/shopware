<?php declare(strict_types=1);

namespace Shopware\DbalIndexing\Product;

use Doctrine\DBAL\Connection;
use Ramsey\Uuid\Uuid;
use Shopware\Api\Entity\Dbal\EntityDefinitionQueryHelper;
use Shopware\Context\Struct\ShopContext;
use Shopware\Defaults;

class CategoryAssignmentUpdater
{
    /**
     * @var Connection
     */
    private $connection;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    public function update(array $ids, ShopContext $context): void
    {
        if (empty($ids)) {
            return;
        }

        $categories = $this->fetchCategories($ids, $context);

        foreach ($categories as $productId => $mapping) {
            $categoryIds = array_filter(explode('||', (string) $mapping['ids']));
            $categoryIds = array_map(
                function (string $bytes) {
                    return Uuid::fromString($bytes)->toString();
                },
                $categoryIds
            );

            $categoryIds = array_merge(
                explode('|', (string) $mapping['paths']),
                $categoryIds
            );

            $categoryIds = array_keys(array_flip(array_filter($categoryIds)));

            $this->connection->executeUpdate(
                'UPDATE product SET category_tree = :tree WHERE id = :id AND version_id = :version',
                [
                    'id' => $productId,
                    'tree' => json_encode($categoryIds),
                    'version' => Uuid::fromString($context->getVersionId())->getBytes(),
                ]
            );
        }
    }

    private function fetchCategories(array $ids, ShopContext $context): array
    {
        $query = $this->connection->createQueryBuilder();
        $query->select([
            'product.id as product_id',
            "GROUP_CONCAT(category.path SEPARATOR '|') as paths",
            "GROUP_CONCAT(HEX(category.id) SEPARATOR '||') as ids",
        ]);
        $query->from('product');
        $query->leftJoin('product', 'product_category', 'mapping', 'mapping.product_id = product.category_join_id AND product.version_id = mapping.product_version_id');
        $query->leftJoin('mapping', 'category', 'category', 'category.id = mapping.category_id AND category.version_id = :live');
        $query->addGroupBy('product.id');

        $query->andWhere('product.id IN (:ids)');
        $query->andWhere('product.version_id = :version');

        $query->setParameter('version', Uuid::fromString($context->getVersionId())->getBytes());
        $query->setParameter('live', Uuid::fromString(Defaults::LIVE_VERSION)->getBytes());

        $bytes = EntityDefinitionQueryHelper::uuidStringsToBytes($ids);

        $query->setParameter('ids', $bytes, Connection::PARAM_STR_ARRAY);

        return $query->execute()->fetchAll(\PDO::FETCH_GROUP | \PDO::FETCH_UNIQUE);
    }
}
