<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Admin\Indexer;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Shopware\Core\Content\Category\Aggregate\CategoryTag\CategoryTagDefinition;
use Shopware\Core\Content\Category\Aggregate\CategoryTranslation\CategoryTranslationDefinition;
use Shopware\Core\Content\Category\CategoryCollection;
use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\IterableQuery;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\IteratorFactory;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Elasticsearch\Framework\AbstractElasticsearchDefinition;
use Shopware\Elasticsearch\Framework\ElasticsearchFieldBuilder;

#[Package('inventory')]
final class CategoryAdminSearchIndexer extends AbstractAdminIndexer
{
    /**
     * @internal
     *
     * @param EntityRepository<CategoryCollection> $repository
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
        return CategoryDefinition::ENTITY_NAME;
    }

    public function getName(): string
    {
        return 'category-listing';
    }

    public function getIterator(): IterableQuery
    {
        return $this->factory->createIterator($this->getEntity(), null, $this->indexingBatchSize);
    }

    public function getUpdatedIds(EntityWrittenContainerEvent $event): array
    {
        $categoryIds = $event->getPrimaryKeysWithPropertyChange($this->getEntity(), [
            'active',
            'parentId',
            'visible',
            'type',
        ]);

        $translations = $event->getPrimaryKeysWithPropertyChange(CategoryTranslationDefinition::ENTITY_NAME, [
            'name',
        ]);

        $tags = $event->getPrimaryKeysWithPropertyChange(CategoryTagDefinition::ENTITY_NAME, [
            'tagId',
        ]);

        foreach (array_merge($translations, $tags) as $pks) {
            if (isset($pks['categoryId'])) {
                $categoryIds[] = $pks['categoryId'];
            }
        }

        return array_values(array_unique(array_filter($categoryIds, '\is_string')));
    }

    public function mapping(array $mapping): array
    {
        if (!Feature::isActive('ENABLE_OPENSEARCH_FOR_ADMIN_API')) {
            return parent::mapping($mapping);
        }

        $languageFields = $this->fieldBuilder->translated(AbstractElasticsearchDefinition::KEYWORD_FIELD);

        $override = [
            'active' => AbstractElasticsearchDefinition::BOOLEAN_FIELD,
            'visible' => AbstractElasticsearchDefinition::BOOLEAN_FIELD,
            'type' => AbstractElasticsearchDefinition::KEYWORD_FIELD,
            'parentId' => AbstractElasticsearchDefinition::KEYWORD_FIELD,
            'name' => $languageFields,
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
            SELECT LOWER(HEX(category.id)) as id,
                   LOWER(HEX(category.parent_id)) as parentId,
                   GROUP_CONCAT(DISTINCT category_translation.name SEPARATOR " ") as name,
                   JSON_ARRAYAGG(JSON_OBJECT(
                       'languageId', LOWER(HEX(category_translation.language_id)),
                       'name', category_translation.name
                   )) as translatedNames,
                   GROUP_CONCAT(DISTINCT tag.name SEPARATOR " ") as tags,
                   GROUP_CONCAT(LOWER(HEX(tag.id)) SEPARATOR " ") as tagIds,
                   category.active AS active,
                   category.visible AS visible,
                   category.type AS type,
                   category.created_at as createdAt
            FROM category
                INNER JOIN category_translation
                    ON category_translation.category_id = category.id
                LEFT JOIN category_tag
                    ON category_tag.category_id = category.id
                LEFT JOIN tag
                    ON category_tag.tag_id = tag.id
            WHERE category.id IN (:ids)
            GROUP BY category.id
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
            $text = \implode(' ', array_filter([$row['name'] ?? '', $row['tags'] ?? '', $id]));

            if (!Feature::isActive('ENABLE_OPENSEARCH_FOR_ADMIN_API')) {
                $mapped[$id] = [
                    'id' => $id,
                    'text' => \strtolower($text),
                ];

                continue;
            }

            $translatedNames = $this->decodeTranslatedValues((string) $row['translatedNames']);

            $mapped[$id] = [
                'id' => $id,
                'parentId' => $row['parentId'] ?? null,
                'text' => \strtolower($text),
                'name' => $translatedNames,
                'active' => (bool) $row['active'],
                'visible' => (bool) $row['visible'],
                'type' => $row['type'] ?? null,
                'tags' => $this->parseTagIds($row),
                'createdAt' => $this->formatDateTime($row, 'createdAt'),
            ];
        }

        return $mapped;
    }
}
