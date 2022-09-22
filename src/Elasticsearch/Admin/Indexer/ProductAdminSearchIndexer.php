<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Admin\Indexer;

use Doctrine\DBAL\Connection;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\IterableQuery;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\IteratorFactory;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
final class ProductAdminSearchIndexer extends AbstractAdminIndexer
{
    private Connection $connection;

    private IteratorFactory $factory;

    private EntityRepositoryInterface $repository;

    public function __construct(Connection $connection, IteratorFactory $factory, EntityRepositoryInterface $repository)
    {
        $this->connection = $connection;
        $this->factory = $factory;
        $this->repository = $repository;
    }

    public function getDecorated(): AbstractAdminIndexer
    {
        throw new DecorationPatternException(self::class);
    }

    public function getEntity(): string
    {
        return ProductDefinition::ENTITY_NAME;
    }

    public function getName(): string
    {
        return 'product-listing';
    }

    public function getIterator(): IterableQuery
    {
        return $this->factory->createIterator($this->getEntity(), null, 150);
    }

    /**
     * @param array<string, mixed> $result
     *
     * @return array{total:int, data:EntityCollection<Entity>}
     */
    public function globalData(array $result, Context $context): array
    {
        $ids = array_column($result['hits'], 'id');

        return [
            'total' => (int) $result['total'],
            'data' => $this->repository->search(new Criteria($ids), $context)->getEntities(),
        ];
    }

    /**
     * @param array<string>|array<int, array<string>> $ids
     *
     * @throws \Doctrine\DBAL\Exception
     *
     * @return array<int|string, array<string, mixed>>
     */
    public function fetch(array $ids): array
    {
        $query = $this->connection->createQueryBuilder();
        $query->select([
            'LOWER(HEX(product.id)) as id',
            'GROUP_CONCAT(translation.name) as name',
            'GROUP_CONCAT(tag.name) as tags',
            'product.product_number',
            'product.ean',
            'product.manufacturer_number',
        ]);

        $query->from('product');
        $query->innerJoin('product', 'product_translation', 'translation', 'product.id = translation.product_id AND product.version_id = translation.product_version_id');
        $query->leftJoin('product', 'product_tag', 'product_tag', 'product.id = product_tag.product_id AND product.version_id = product_tag.product_version_id');
        $query->leftJoin('product_tag', 'tag', 'tag', 'product_tag.tag_id = tag.id');
        $query->where('product.id IN (:ids)');
        $query->andWhere('product.version_id = :versionId');
        $query->setParameter('ids', Uuid::fromHexToBytesList($ids), Connection::PARAM_STR_ARRAY);
        $query->setParameter('versionId', Uuid::fromHexToBytes(Defaults::LIVE_VERSION));
        $query->groupBy('product.id');

        $data = $query->execute()->fetchAll();

        $mapped = [];
        foreach ($data as $row) {
            $id = $row['id'];
            $text = \implode(' ', array_filter(array_unique(array_values($row))));
            $mapped[$id] = ['id' => $id, 'text' => \strtolower($text)];
        }

        return $mapped;
    }
}
