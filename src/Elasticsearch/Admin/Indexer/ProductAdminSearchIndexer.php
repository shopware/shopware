<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Admin\Indexer;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use OpenSearchDSL\Query\Compound\BoolQuery;
use OpenSearchDSL\Query\FullText\MatchQuery;
use OpenSearchDSL\Query\FullText\SimpleQueryStringQuery;
use OpenSearchDSL\Search;
use Shopware\Core\Content\Product\Aggregate\ProductCategory\ProductCategoryDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductTag\ProductTagDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductTranslation\ProductTranslationDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\IterableQuery;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\IteratorFactory;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\SqlHelper;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Elasticsearch\Framework\AbstractElasticsearchDefinition;
use Shopware\Elasticsearch\Framework\ElasticsearchFieldBuilder;
use Shopware\Elasticsearch\Framework\ElasticsearchIndexingUtils;

#[Package('inventory')]
final class ProductAdminSearchIndexer extends AbstractAdminIndexer
{
    /**
     * @internal
     *
     * @param EntityRepository<ProductCollection> $repository
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
        return ProductDefinition::ENTITY_NAME;
    }

    public function getUpdatedIds(EntityWrittenContainerEvent $event): array
    {
        $productIds = $event->getPrimaryKeysWithPropertyChange($this->getEntity(), [
            'productNumber',
            'ean',
            'manufacturerNumber',
            'active',
            'manufacturerId',
            'stock',
            'releaseDate',
        ]);

        $multiplePrimaryKeyWrittenEvent = $event;
        $translations = $multiplePrimaryKeyWrittenEvent->getPrimaryKeysWithPropertyChange(ProductTranslationDefinition::ENTITY_NAME, [
            'name',
            'customSearchKeywords',
        ]);

        $categories = Feature::isActive('ENABLE_OPENSEARCH_FOR_ADMIN_API') ? $multiplePrimaryKeyWrittenEvent->getPrimaryKeysWithPropertyChange(ProductCategoryDefinition::ENTITY_NAME, [
            'categoryId',
        ]) : [];

        $visibilities = Feature::isActive('ENABLE_OPENSEARCH_FOR_ADMIN_API') ? $multiplePrimaryKeyWrittenEvent->getPrimaryKeysWithPropertyChange(ProductVisibilityDefinition::ENTITY_NAME, [
            'salesChannelId',
        ]) : [];

        $tags = $multiplePrimaryKeyWrittenEvent->getPrimaryKeysWithPropertyChange(ProductTagDefinition::ENTITY_NAME, [
            'tagId',
        ]);

        foreach (array_merge($translations, $tags, $visibilities, $categories) as $pks) {
            if (isset($pks['productId'])) {
                $productIds[] = $pks['productId'];
            }
        }

        return array_values(array_unique(array_filter($productIds, '\is_string')));
    }

    public function getName(): string
    {
        return 'product-listing';
    }

    public function getIterator(): IterableQuery
    {
        return $this->factory->createIterator($this->getEntity(), null, $this->indexingBatchSize);
    }

    public function globalData(array $result, Context $context): array
    {
        $ids = array_column($result['hits'], 'id');
        $criteria = new Criteria($ids);
        $criteria->addAssociations(['options.group']);

        return [
            'total' => (int) $result['total'],
            'data' => $this->repository->search($criteria, $context)->getEntities(),
        ];
    }

    public function globalCriteria(string $term, Search $criteria): Search
    {
        $splitTerms = explode(' ', $term);
        $lastPart = end($splitTerms);

        $ngramQuery = new MatchQuery('textBoosted.ngram', $term, [
            'boost' => 10,
        ]);
        $criteria->addQuery($ngramQuery, BoolQuery::SHOULD);

        // If the end of the search term is not a symbol, apply the prefix search query
        if (preg_match('/^[\p{L}0-9]+$/u', $lastPart)) {
            $term .= '*';
        }

        $query = new SimpleQueryStringQuery($term, [
            'fields' => ['textBoosted'],
            'boost' => 10,
            'lenient' => true,
        ]);
        $criteria->addQuery($query, BoolQuery::SHOULD);

        return $criteria;
    }

    public function mapping(array $mapping): array
    {
        if (!Feature::isActive('ENABLE_OPENSEARCH_FOR_ADMIN_API')) {
            return parent::mapping($mapping);
        }

        $languageFields = $this->fieldBuilder->translated(AbstractElasticsearchDefinition::KEYWORD_FIELD);

        $override = [
            'parentId' => AbstractElasticsearchDefinition::KEYWORD_FIELD,
            'available' => AbstractElasticsearchDefinition::BOOLEAN_FIELD,
            'name' => $languageFields,
            'active' => AbstractElasticsearchDefinition::BOOLEAN_FIELD,
            'sales' => AbstractElasticsearchDefinition::INT_FIELD,
            'states' => AbstractElasticsearchDefinition::KEYWORD_FIELD,
            'productNumber' => AbstractElasticsearchDefinition::KEYWORD_FIELD,
            'ean' => AbstractElasticsearchDefinition::KEYWORD_FIELD,
            'manufacturerNumber' => AbstractElasticsearchDefinition::KEYWORD_FIELD,
            'manufacturerId' => AbstractElasticsearchDefinition::KEYWORD_FIELD,
            'stock' => AbstractElasticsearchDefinition::INT_FIELD,
            'releaseDate' => ElasticsearchFieldBuilder::datetime(),
            'createdAt' => ElasticsearchFieldBuilder::datetime(),
            'updatedAt' => ElasticsearchFieldBuilder::datetime(),
            'categories' => ElasticsearchFieldBuilder::nested(),
            'tags' => ElasticsearchFieldBuilder::nested(),
            'manufacturer' => ElasticsearchFieldBuilder::nested([
                'name' => $languageFields,
            ]),
            'visibilities' => ElasticsearchFieldBuilder::nested([
                'salesChannelId' => AbstractElasticsearchDefinition::KEYWORD_FIELD,
            ]),
        ];

        $mapping['properties'] ??= [];
        $mapping['properties'] = array_merge($mapping['properties'], $override);

        return $mapping;
    }

    /**
     * @param array<string> $ids
     *
     * @return array<string, array<string, mixed>>
     */
    public function fetch(array $ids): array
    {
        if (Feature::isActive('ENABLE_OPENSEARCH_FOR_ADMIN_API')) {
            return $this->advancedFetch($ids);
        }

        $data = $this->connection->fetchAllAssociative(
            '
            SELECT LOWER(HEX(product.id)) as id,
                   GROUP_CONCAT(DISTINCT translation.name SEPARATOR " ") as name,
                   CONCAT("[", GROUP_CONCAT(translation.custom_search_keywords), "]") as custom_search_keywords,
                   GROUP_CONCAT(DISTINCT tag.name SEPARATOR " ") as tags,
                   product.product_number,
                   product.ean,
                   product.manufacturer_number
            FROM product
                INNER JOIN product_translation AS translation
                    ON product.id = translation.product_id AND product.version_id = translation.product_version_id
                LEFT JOIN product_tag
                    ON product.id = product_tag.product_id AND product.version_id = product_tag.product_version_id
                LEFT JOIN tag
                    ON product_tag.tag_id = tag.id
            WHERE product.id IN (:ids)
            AND product.version_id = :versionId
            GROUP BY product.id
        ',
            [
                'ids' => Uuid::fromHexToBytesList($ids),
                'versionId' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
            ],
            [
                'ids' => ArrayParameterType::BINARY,
            ]
        );

        $mapped = [];
        foreach ($data as $row) {
            $textBoosted = $row['name'] . ' ' . $row['product_number'];

            if ($row['custom_search_keywords']) {
                $row['custom_search_keywords'] = json_decode((string) $row['custom_search_keywords'], true, 512, \JSON_THROW_ON_ERROR);
                $textBoosted = $textBoosted . ' ' . implode(' ', array_unique(array_merge(...$row['custom_search_keywords'])));
            }

            $id = (string) $row['id'];
            unset($row['name'],  $row['product_number'], $row['custom_search_keywords']);
            $text = \implode(' ', array_filter(array_unique(array_values($row))));
            $mapped[$id] = ['id' => $id, 'textBoosted' => \strtolower($textBoosted), 'text' => \strtolower($text)];
        }

        return $mapped;
    }

    /**
     * @description to keep the writing fast we do a more complex fetch here only if the feature flag ENABLE_OPENSEARCH_FOR_ADMIN_API is enabled to reduce the number of joins in the sql query
     *
     * @param array<string> $ids
     *
     * @return array<string, array<string, mixed>>
     */
    private function advancedFetch(array $ids): array
    {
        $baseMapping = [
            '#visibilities#' => SqlHelper::objectArray([
                'salesChannelId' => 'LOWER(HEX(product_visibility.sales_channel_id))',
            ], 'visibilities'),
        ];

        $baseSql = <<<'SQL'
            SELECT LOWER(HEX(product.id)) as id,
                   GROUP_CONCAT(DISTINCT translation.name SEPARATOR " ") as name,
                   JSON_ARRAYAGG(JSON_OBJECT(
                       'languageId', LOWER(HEX(translation.language_id)),
                       'name', translation.name
                   )) as translatedNames,
                   JSON_ARRAYAGG(JSON_OBJECT(
                       'languageId', LOWER(HEX(manufacturer_translation.language_id)),
                       'name', manufacturer_translation.name
                   )) as translatedManufacturerNames,
                   CONCAT("[", GROUP_CONCAT(translation.custom_search_keywords), "]") as customSearchKeywords,
                   GROUP_CONCAT(DISTINCT tag.name SEPARATOR " ") as tags,
                   GROUP_CONCAT(LOWER(HEX(tag.id)) SEPARATOR " ") as tagIds,
                   IFNULL(product.active, parent.active) AS active,
                   product.available AS available,
                   LOWER(HEX(product.parent_id)) as parentId,
                   product.product_number as productNumber,
                   product.ean as ean,
                   product.manufacturer_number as manufacturerNumber,
                   product.sales as sales,
                   product.states as states,
                   LOWER(HEX(product.manufacturer)) AS manufacturerId,
                   IFNULL(product.category_ids, parent.category_ids) AS categoryIds,
                   product.stock as stock,
                   IFNULL(product.release_date, parent.release_date) AS releaseDate,
                   product.created_at as createdAt,
                   product.updated_at as updatedAt,
                   #visibilities#
            FROM product
                LEFT JOIN product parent ON (product.parent_id = parent.id AND parent.version_id = :versionId)
                LEFT JOIN product_visibility ON (product_visibility.product_id = product.visibilities AND product_visibility.product_version_id = product.version_id)
                INNER JOIN product_translation AS translation
                    ON product.id = translation.product_id AND product.version_id = translation.product_version_id
                LEFT JOIN product_manufacturer_translation AS manufacturer_translation
                    ON manufacturer_translation.product_manufacturer_id = product.manufacturer
                    AND manufacturer_translation.language_id = translation.language_id
                    AND manufacturer_translation.product_manufacturer_version_id = product.version_id
                LEFT JOIN product_tag
                    ON product.id = product_tag.product_id AND product.version_id = product_tag.product_version_id
                LEFT JOIN tag
                    ON product_tag.tag_id = tag.id
            WHERE product.id IN (:ids)
            AND product.version_id = :versionId
            GROUP BY product.id
SQL;

        $data = $this->connection->fetchAllAssociative(
            str_replace(array_keys($baseMapping), array_values($baseMapping), $baseSql),
            [
                'ids' => Uuid::fromHexToBytesList($ids),
                'versionId' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
            ],
            [
                'ids' => ArrayParameterType::BINARY,
            ]
        );

        $mapped = [];
        foreach ($data as $row) {
            $textBoosted = $row['name'] . ' ' . $row['productNumber'];

            if ($row['customSearchKeywords']) {
                $row['customSearchKeywords'] = json_decode((string) $row['customSearchKeywords'], true, 512, \JSON_THROW_ON_ERROR);
                $textBoosted = $textBoosted . ' ' . implode(' ', array_unique(array_merge(...$row['customSearchKeywords'])));
            }

            $id = (string) $row['id'];
            $translatedNames = $this->decodeTranslatedValues((string) $row['translatedNames']);
            $translatedManufacturerNames = $this->decodeTranslatedValues((string) $row['translatedManufacturerNames']);

            $textRow = [
                'tags' => $row['tags'] ?? '',
                'id' => $id,
            ];

            $text = \implode(' ', array_filter(array_unique(array_values($textRow))));

            $mapped[$id] = [
                'id' => $id,
                'textBoosted' => \strtolower($textBoosted),
                'text' => \strtolower($text),
                'name' => $translatedNames,
                'parentId' => $row['parentId'] ?? null,
                'productNumber' => $row['productNumber'] ?? null,
                'ean' => $row['ean'] ?? null,
                'manufacturerNumber' => $row['manufacturerNumber'] ?? null,
                'manufacturerId' => $row['manufacturerId'] ?? null,
                'sales' => (int) $row['sales'],
                'active' => (bool) $row['active'],
                'available' => (bool) $row['available'],
                'stock' => (int) $row['stock'],
                'states' => ElasticsearchIndexingUtils::parseJson($row, 'states'),
                'manufacturer' => [
                    'id' => $row['manufacturerId'] ?? null,
                    'name' => $translatedManufacturerNames,
                ],
                'categories' => array_map(function (string $categoryId) {
                    return [
                        'id' => $categoryId,
                        'versionId' => Defaults::LIVE_VERSION,
                        '_count' => 1,
                    ];
                }, ElasticsearchIndexingUtils::parseJson($row, 'categoryIds')),
                'visibilities' => array_map(function (array $visibility) {
                    return array_merge([
                        '_count' => 1,
                    ], $visibility);
                }, ElasticsearchIndexingUtils::parseJson($row, 'visibilities')),
                'tags' => $this->parseTagIds($row),
                'createdAt' => $this->formatDateTime($row, 'createdAt'),
                'updatedAt' => $this->formatDateTime($row, 'updatedAt'),
                'releaseDate' => $this->formatDateTime($row, 'releaseDate'),
            ];
        }

        return $mapped;
    }
}
