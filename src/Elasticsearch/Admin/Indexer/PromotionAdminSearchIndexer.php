<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Admin\Indexer;

use Doctrine\DBAL\Connection;
use Shopware\Core\Checkout\Promotion\PromotionDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\IterableQuery;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\IteratorFactory;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Uuid\Uuid;

final class PromotionAdminSearchIndexer extends AdminSearchIndexer
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


    public function getDecorated(): AdminSearchIndexer
    {
        throw new DecorationPatternException(self::class);
    }

    public function getEntity(): string
    {
        return PromotionDefinition::ENTITY_NAME;
    }

    public function getName(): string
    {
        return 'promotion-listing';
    }

    public function getIterator(): IterableQuery
    {
        return $this->factory->createIterator($this->getEntity(), null, 150);
    }

    public function globalData(array $result, Context $context): array
    {
        $ids = array_column($result['hits'], 'id');

        return [
            'total' => $result['total'],
            'data' => $this->repository->search(new Criteria($ids), $context)->getEntities(),
        ];
    }

    public function fetch(array $ids): array
    {
        $query = $this->connection->createQueryBuilder();
        $query->select([
            'LOWER(HEX(promotion.id)) as id',
            'GROUP_CONCAT(promotion_translation.name) as name',
        ]);

        $query->from('promotion');
        $query->innerJoin('promotion', 'promotion_translation', 'promotion_translation', 'promotion.id = promotion_translation.promotion_id');
        $query->andWhere('promotion.id IN (:ids)');
        $query->setParameter('ids', Uuid::fromHexToBytesList($ids), Connection::PARAM_STR_ARRAY);
        $query->groupBy('promotion.id');

        $data = $query->execute()->fetchAll();

        $mapped = [];
        foreach ($data as $row) {
            $id = $row['id'];
            $mapped[$id] = ['id' => $id, 'text' => \implode(' ', $row)];
        }

        return $mapped;
    }
}
