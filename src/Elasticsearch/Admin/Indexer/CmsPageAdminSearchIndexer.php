<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Admin\Indexer;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Shopware\Core\Content\Cms\Aggregate\CmsPageTranslation\CmsPageTranslationDefinition;
use Shopware\Core\Content\Cms\CmsPageCollection;
use Shopware\Core\Content\Cms\CmsPageDefinition;
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
final class CmsPageAdminSearchIndexer extends AbstractAdminIndexer
{
    /**
     * @internal
     *
     * @param EntityRepository<CmsPageCollection> $repository
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
        return CmsPageDefinition::ENTITY_NAME;
    }

    public function getName(): string
    {
        return 'cms-page-listing';
    }

    public function getIterator(): IterableQuery
    {
        return $this->factory->createIterator(CmsPageDefinition::ENTITY_NAME, null, $this->indexingBatchSize);
    }

    public function getUpdatedIds(EntityWrittenContainerEvent $event): array
    {
        $ids = $event->getPrimaryKeysWithPropertyChange($this->getEntity(), [
            'type',
        ]);

        $translations = $event->getPrimaryKeysWithPropertyChange(CmsPageTranslationDefinition::ENTITY_NAME, [
            'name',
        ]);

        foreach ($translations as $pks) {
            if (isset($pks['cmsPageId'])) {
                $ids[] = $pks['cmsPageId'];
            }
        }

        return array_values(array_unique(array_filter($ids, '\is_string')));
    }

    public function mapping(array $mapping): array
    {
        if (!Feature::isActive('ENABLE_OPENSEARCH_FOR_ADMIN_API')) {
            return parent::mapping($mapping);
        }

        $languageFields = $this->fieldBuilder->translated(AbstractElasticsearchDefinition::KEYWORD_FIELD);

        $override = [
            'name' => $languageFields,
            'type' => AbstractElasticsearchDefinition::KEYWORD_FIELD,
            'createdAt' => ElasticsearchFieldBuilder::datetime(),
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

    public function fetch(array $ids): array
    {
        $data = $this->connection->fetchAllAssociative(
            <<<'SQL'
            SELECT LOWER(HEX(cms_page.id)) as id,
                   GROUP_CONCAT(DISTINCT cms_page_translation.name SEPARATOR " ") as name,
                   JSON_ARRAYAGG(JSON_OBJECT(
                       'languageId', LOWER(HEX(cms_page_translation.language_id)),
                       'name', cms_page_translation.name
                   )) as translatedNames,
                   cms_page.type AS type,
                   cms_page.created_at as createdAt
            FROM cms_page
                INNER JOIN cms_page_translation
                    ON cms_page_translation.cms_page_id = cms_page.id
            WHERE cms_page.id IN (:ids)
            GROUP BY cms_page.id
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
            $text = \implode(' ', array_filter([$row['name'] ?? '', $id]));

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
                'text' => \strtolower($text),
                'name' => $translatedNames,
                'type' => $row['type'] ?? null,
                'createdAt' => $this->formatDateTime($row, 'createdAt'),
            ];
        }

        return $mapped;
    }
}
