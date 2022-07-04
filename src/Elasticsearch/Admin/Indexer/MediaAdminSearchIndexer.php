<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Admin\Indexer;

use Doctrine\DBAL\Connection;
use Shopware\Core\Content\Media\MediaDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\IterableQuery;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\IteratorFactory;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Uuid\Uuid;

final class MediaAdminSearchIndexer extends AdminSearchIndexer
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
        return MediaDefinition::ENTITY_NAME;
    }

    public function getName(): string
    {
        return 'media-listing';
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
            'LOWER(HEX(media.id)) as id',
            'media.file_name',
            'GROUP_CONCAT(media_translation.alt) as alt',
            'GROUP_CONCAT(media_translation.title) as title',
            'media_folder.name',
            'GROUP_CONCAT(tag.name) as tags',
        ]);

        $query->from('media');
        $query->innerJoin('media', 'media_translation', 'media_translation', 'media.id = media_translation.media_id');
        $query->leftJoin('media', 'media_folder', 'media_folder', 'media.media_folder_id = media_folder.id');
        $query->leftJoin('media', 'media_tag', 'media_tag', 'media.id = media_tag.media_id');
        $query->leftJoin('media_tag', 'tag', 'tag', 'media_tag.tag_id = tag.id');
        $query->andWhere('media.id IN (:ids)');
        $query->setParameter('ids', Uuid::fromHexToBytesList($ids), Connection::PARAM_STR_ARRAY);
        $query->groupBy('media.id');

        $data = $query->execute()->fetchAll();

        $mapped = [];
        foreach ($data as $row) {
            $id = $row['id'];
            $mapped[$id] = ['id' => $id, 'text' => \implode(' ', $row)];
        }

        return $mapped;
    }
}
