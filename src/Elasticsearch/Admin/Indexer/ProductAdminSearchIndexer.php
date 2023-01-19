<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Admin\Indexer;

use Doctrine\DBAL\Connection;
use ONGR\ElasticsearchDSL\Query\Compound\BoolQuery;
use ONGR\ElasticsearchDSL\Query\FullText\SimpleQueryStringQuery;
use ONGR\ElasticsearchDSL\Search;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\IterableQuery;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\IteratorFactory;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('system-settings')]
final class ProductAdminSearchIndexer extends AbstractAdminIndexer
{
    private Connection $connection;

    private IteratorFactory $factory;

    private EntityRepository $repository;

    private int $indexingBatchSize;

    public function __construct(
        Connection $connection,
        IteratorFactory $factory,
        EntityRepository $repository,
        int $indexingBatchSize
    ) {
        $this->connection = $connection;
        $this->factory = $factory;
        $this->repository = $repository;
        $this->indexingBatchSize = $indexingBatchSize;
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

    /**
     * @param array<string, mixed> $result
     *
     * @return array{total:int, data:EntityCollection<Entity>}
     */
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
        if (preg_match('/^[a-zA-Z0-9]+$/', $lastPart)) {
            $term = $term . '*';
        }

        $query = new SimpleQueryStringQuery($term, [
            'fields' => ['textBoosted'],
            'boost' => 10,
        ]);

        $criteria->addQuery($query, BoolQuery::SHOULD);

        return $criteria;
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
        $data = $this->connection->fetchAllAssociative(
            '
            SELECT LOWER(HEX(product.id)) as id,
                   GROUP_CONCAT(DISTINCT translation.name) as name,
                   CONCAT("[", GROUP_CONCAT(translation.custom_search_keywords), "]") as custom_search_keywords,
                   GROUP_CONCAT(DISTINCT tag.name) as tags,
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
                'ids' => Connection::PARAM_STR_ARRAY,
            ]
        );

        $mapped = [];
        foreach ($data as $row) {
            $textBoosted = $row['name'] . ' ' . $row['product_number'];

            if ($row['custom_search_keywords']) {
                $row['custom_search_keywords'] = json_decode($row['custom_search_keywords'], true);
                $textBoosted = $textBoosted . ' ' . implode(' ', array_unique(array_merge(...$row['custom_search_keywords'])));
            }

            $id = $row['id'];
            unset($row['name'],  $row['product_number'], $row['custom_search_keywords']);
            $text = \implode(' ', array_filter(array_unique(array_values($row))));
            $mapped[$id] = ['id' => $id, 'textBoosted' => \strtolower($textBoosted), 'text' => \strtolower($text)];
        }

        return $mapped;
    }
}
