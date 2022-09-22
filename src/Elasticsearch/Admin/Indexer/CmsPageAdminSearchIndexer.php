<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Admin\Indexer;

use Doctrine\DBAL\Connection;
use Shopware\Core\Content\Cms\CmsPageDefinition;
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
final class CmsPageAdminSearchIndexer extends AbstractAdminIndexer
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
        return CmsPageDefinition::ENTITY_NAME;
    }

    public function getName(): string
    {
        return 'cms-page-listing';
    }

    public function getIterator(): IterableQuery
    {
        return $this->factory->createIterator(CmsPageDefinition::ENTITY_NAME, null, 150);
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
            'LOWER(HEX(cms_page.id)) as id',
            'GROUP_CONCAT(cms_page_translation.name) as name',
        ]);

        $query->from('cms_page');
        $query->innerJoin('cms_page', 'cms_page_translation', 'cms_page_translation', 'cms_page.id = cms_page_translation.cms_page_id');
        $query->where('cms_page.id IN (:ids)');
        $query->setParameter('ids', Uuid::fromHexToBytesList($ids), Connection::PARAM_STR_ARRAY);
        $query->groupBy('cms_page.id');

        $data = $query->execute()->fetchAll();

        $mapped = [];
        foreach ($data as $row) {
            $id = $row['id'];
            $text = \implode(' ', array_filter($row));
            $mapped[$id] = ['id' => $id, 'text' => \strtolower($text)];
        }

        return $mapped;
    }
}
