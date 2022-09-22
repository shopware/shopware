<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Admin\Indexer;

use Doctrine\DBAL\Connection;
use Shopware\Core\Checkout\Customer\CustomerDefinition;
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
final class CustomerAdminSearchIndexer extends AbstractAdminIndexer
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
        return CustomerDefinition::ENTITY_NAME;
    }

    public function getName(): string
    {
        return 'customer-listing';
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
            'LOWER(HEX(customer.id)) as id',
            'GROUP_CONCAT(tag.name) as tags',
            'GROUP_CONCAT(country_translation.name) as country',
            'GROUP_CONCAT(customer_address.city) as city',
            'GROUP_CONCAT(customer_address.zipcode) as zipcode',
            'GROUP_CONCAT(customer_address.street) as street',
            'customer.first_name',
            'customer.last_name',
            'customer.email',
            'customer.company',
            'customer.customer_number',
        ]);

        $query->from('customer');
        $query->leftJoin('customer', 'customer_address', 'customer_address', 'customer.id = customer_address.customer_id');
        $query->leftJoin('customer_address', 'country', 'country', 'customer_address.country_id = country.id');
        $query->leftJoin('country', 'country_translation', 'country_translation', 'country.id = country_translation.country_id');
        $query->leftJoin('customer', 'customer_tag', 'customer_tag', 'customer.id = customer_tag.customer_id');
        $query->leftJoin('customer_tag', 'tag', 'tag', 'customer_tag.tag_id = tag.id');
        $query->groupBy('customer.id');

        $query->where('customer.id IN (:ids)');
        $query->setParameter('ids', Uuid::fromHexToBytesList($ids), Connection::PARAM_STR_ARRAY);

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
