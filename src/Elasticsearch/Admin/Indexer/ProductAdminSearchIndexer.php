<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Admin\Indexer;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use OpenSearchDSL\Query\Compound\BoolQuery;
use OpenSearchDSL\Query\FullText\SimpleQueryStringQuery;
use OpenSearchDSL\Search;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\IterableQuery;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\IteratorFactory;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\SqlHelper;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
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

        // If the end of the search term is not a symbol, apply the prefix search query
        if (preg_match('/^[\p{L}0-9]+$/u', $lastPart)) {
            $term .= '*';
        }

        $query = new SimpleQueryStringQuery($term, [
            'fields' => ['textBoosted'],
            'boost' => 10,
        ]);

        $criteria->addQuery($query, BoolQuery::SHOULD);

        return $criteria;
    }

    public function mapping(array $mapping): array
    {
        $override = [
            'parentId' => AbstractElasticsearchDefinition::KEYWORD_FIELD,
            'available' => AbstractElasticsearchDefinition::BOOLEAN_FIELD,
            'active' => AbstractElasticsearchDefinition::BOOLEAN_FIELD,
            'sales' => AbstractElasticsearchDefinition::INT_FIELD,
            'states' => AbstractElasticsearchDefinition::KEYWORD_FIELD,
            'productNumber' => AbstractElasticsearchDefinition::KEYWORD_FIELD,
            'manufacturerId' => AbstractElasticsearchDefinition::KEYWORD_FIELD,
            'stock' => AbstractElasticsearchDefinition::INT_FIELD,
            'releaseDate' => ElasticsearchFieldBuilder::datetime(),
            'createdAt' => ElasticsearchFieldBuilder::datetime(),
            'updatedAt' => ElasticsearchFieldBuilder::datetime(),
            'categories' => ElasticsearchFieldBuilder::nested([
                'versionId' => AbstractElasticsearchDefinition::KEYWORD_FIELD,
            ]),
            'tags' => ElasticsearchFieldBuilder::nested(),
            'manufacturer' => ElasticsearchFieldBuilder::nested(),
            'visibilities' => ElasticsearchFieldBuilder::nested([
                'id' => AbstractElasticsearchDefinition::KEYWORD_FIELD,
                'salesChannelId' => AbstractElasticsearchDefinition::KEYWORD_FIELD,
                'visibility' => AbstractElasticsearchDefinition::INT_FIELD,
            ]),
        ];

        $mapping['properties'] ??= [];
        $mapping['properties'] = array_merge($mapping['properties'], $override);

        return $mapping;
    }

    /**
     * @return array<string, array{id:string, textBoosted:string, text:string}>
     */
    public function fetch(array $ids): array
    {
        $baseMapping = [
            '#visibilities#' => SqlHelper::objectArray([
                'visibility' => 'product_visibility.visibility',
                'salesChannelId' => 'LOWER(HEX(product_visibility.sales_channel_id))',
            ], 'visibilities'),
        ];

        $baseSql = <<<'SQL'
            SELECT LOWER(HEX(product.id)) as id,
                   GROUP_CONCAT(DISTINCT translation.name SEPARATOR " ") as name,
                   CONCAT("[", GROUP_CONCAT(translation.custom_search_keywords), "]") as customSearchKeywords,
                   GROUP_CONCAT(DISTINCT tag.name SEPARATOR " ") as tags,
                   GROUP_CONCAT(LOWER(HEX(tag.id)) SEPARATOR " ") as tagIds,
                   IFNULL(product.active, parent.active) AS active,
                   product.available AS available,
                   product.parent_id as parentId,
                   product.product_number as productNumber,
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
                LEFT JOIN product_media ON (product_media.product_id = product.media AND product_media.product_version_id = product.version_id)

                INNER JOIN product_translation AS translation
                    ON product.id = translation.product_id AND product.version_id = translation.product_version_id
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

            $textRow = [
                'tags' => $row['tags'] ?? '',
                'id' => $id,
            ];

            $text = \implode(' ', array_filter(array_unique(array_values($textRow))));
            $mapped[$id] = [
                'id' => $id,
                'textBoosted' => \strtolower($textBoosted),
                'text' => \strtolower($text),
                'parentId' => $row['parentId'] ?? null,
                'productNumber' => $row['productNumber'] ?? null,
                'manufacturerId' => $row['manufacturerId'] ?? null,
                'sales' => (int) $row['sales'],
                'active' => (bool) $row['active'],
                'available' => (bool) $row['available'],
                'stock' => (int) $row['stock'],
                'states' => ElasticsearchIndexingUtils::parseJson($row, 'states'),
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
                'tags' => array_map(function (string $tagId) {
                    return [
                        'id' => $tagId,
                        '_count' => 1,
                    ];
                }, explode(' ', $row['tagIds'] ?? '')),
                'createdAt' => isset($row['createdAt']) ? (new \DateTime($row['createdAt']))->format(Defaults::STORAGE_DATE_TIME_FORMAT) : null,
                'updatedAt' => isset($row['updatedAt']) ? (new \DateTime($row['updatedAt']))->format(Defaults::STORAGE_DATE_TIME_FORMAT) : null,
                'releaseDate' => isset($row['releaseDate']) ? (new \DateTime($row['releaseDate']))->format(Defaults::STORAGE_DATE_TIME_FORMAT) : null,
            ];
        }

        return $mapped;
    }
}
