<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Admin\Indexer;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Shopware\Core\Content\Media\Aggregate\MediaTag\MediaTagDefinition;
use Shopware\Core\Content\Media\Aggregate\MediaTranslation\MediaTranslationDefinition;
use Shopware\Core\Content\Media\MediaCollection;
use Shopware\Core\Content\Media\MediaDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\IterableQuery;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\IteratorFactory;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Elasticsearch\Framework\AbstractElasticsearchDefinition;
use Shopware\Elasticsearch\Framework\ElasticsearchFieldBuilder;

#[Package('inventory')]
final class MediaAdminSearchIndexer extends AbstractAdminIndexer
{
    /**
     * @internal
     *
     * @param EntityRepository<MediaCollection> $repository
     */
    public function __construct(
        private readonly Connection $connection,
        private readonly IteratorFactory $factory,
        private readonly EntityRepository $repository,
        private readonly ElasticsearchFieldBuilder $fieldBuilder,
        private readonly int $indexingBatchSize
    ) {
    }

    public function getDecorated(): AbstractAdminIndexer
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
        return $this->factory->createIterator($this->getEntity(), null, $this->indexingBatchSize);
    }

    public function getUpdatedIds(EntityWrittenContainerEvent $event): array
    {
        $mediaIds = $event->getPrimaryKeysWithPropertyChange($this->getEntity(), [
            'fileName',
            'fileExtension',
            'path',
            'mediaFolderId',
        ]);

        /** @var EntityWrittenContainerEvent<array<string, string>> $multiplePrimaryKeyWrittenEvent Mapping and translation definitions have multiple primary keys */
        $multiplePrimaryKeyWrittenEvent = $event;
        $tags = $multiplePrimaryKeyWrittenEvent->getPrimaryKeysWithPropertyChange(MediaTagDefinition::ENTITY_NAME, [
            'tagId',
        ]);

        $translations = $multiplePrimaryKeyWrittenEvent->getPrimaryKeysWithPropertyChange(MediaTranslationDefinition::ENTITY_NAME, [
            'title',
            'alt',
        ]);

        foreach (array_merge($tags, $translations) as $pks) {
            if (isset($pks['mediaId'])) {
                $mediaIds[] = $pks['mediaId'];
            }
        }

        return \array_values(\array_unique($mediaIds));
    }

    public function mapping(array $mapping): array
    {
        $languageFields = $this->fieldBuilder->translated(AbstractElasticsearchDefinition::KEYWORD_FIELD);

        $override = [
            'fileName' => AbstractElasticsearchDefinition::KEYWORD_FIELD,
            'fileExtension' => AbstractElasticsearchDefinition::KEYWORD_FIELD,
            'mediaFolderId' => AbstractElasticsearchDefinition::KEYWORD_FIELD,
            'title' => $languageFields,
            'alt' => $languageFields,
            'createdAt' => ElasticsearchFieldBuilder::datetime(),
            'tags' => ElasticsearchFieldBuilder::nested(),
        ];

        $mapping['properties'] ??= [];
        $mapping['properties'] = array_merge($mapping['properties'], $override);

        return $mapping;
    }

    public function globalData(array $result, Context $context): array
    {
        $ids = array_column($result['hits'], 'id');

        return [
            'total' => (int) $result['total'],
            'data' => $this->repository->search(new Criteria($ids), $context)->getEntities(),
        ];
    }

    /**
     * @return array<string, array{id:string, text:string}>
     */
    public function fetch(array $ids): array
    {
        $data = $this->connection->fetchAllAssociative(
            <<<'SQL'
            SELECT LOWER(HEX(media.id)) as id,
                   GROUP_CONCAT(DISTINCT tag.name SEPARATOR " ") as tags,
                   GROUP_CONCAT(LOWER(HEX(tag.id)) SEPARATOR " ") as tagIds,
                   GROUP_CONCAT(DISTINCT media_translation.alt SEPARATOR " ") as alt,
                   GROUP_CONCAT(DISTINCT media_translation.title SEPARATOR " ") as title,
                   JSON_ARRAYAGG(JSON_OBJECT(
                       'languageId', LOWER(HEX(media_translation.language_id)),
                       'title', media_translation.title,
                       'alt', media_translation.alt
                   )) as translatedFields,
                   media_folder.name as folderName,
                   media.file_name,
                   media.file_extension,
                   media.path,
                   LOWER(HEX(media.media_folder_id)) AS mediaFolderId,
                   media.created_at as createdAt
            FROM media
                LEFT JOIN media_translation
                    ON media.id = media_translation.media_id
                LEFT JOIN media_folder
                    ON media.media_folder_id = media_folder.id
                LEFT JOIN media_tag
                    ON media.id = media_tag.media_id
                LEFT JOIN tag
                    ON media_tag.tag_id = tag.id
            WHERE media.id IN (:ids)
            GROUP BY media.id
SQL,
            [
                'ids' => Uuid::fromHexToBytesList($ids),
            ],
            [
                'ids' => ArrayParameterType::BINARY,
            ]
        );

        $mapped = [];
        foreach ($data as $row) {
            $id = (string) $row['id'];
            $text = \implode(' ', array_filter([
                $row['file_name'] ?? '',
                $row['file_extension'] ?? '',
                $row['path'] ?? '',
                $row['alt'] ?? '',
                $row['title'] ?? '',
                $row['folderName'] ?? '',
                $row['tags'] ?? '',
                $id,
            ]));

            $translatedTitles = $this->decodeTranslatedValues((string) $row['translatedFields'], 'title');
            $translatedAlts = $this->decodeTranslatedValues((string) $row['translatedFields'], 'alt');

            $mapped[$id] = [
                'id' => $id,
                'text' => \strtolower($text),
                'fileName' => $row['file_name'] ?? null,
                'fileExtension' => $row['file_extension'] ?? null,
                'mediaFolderId' => $row['mediaFolderId'] ?? null,
                'title' => $translatedTitles,
                'alt' => $translatedAlts,
                'tags' => $this->parseTagIds($row),
                'createdAt' => $this->formatDateTime($row, 'createdAt'),
            ];
        }

        return $mapped;
    }
}
