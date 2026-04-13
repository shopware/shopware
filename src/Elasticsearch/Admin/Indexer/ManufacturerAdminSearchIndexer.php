<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Admin\Indexer;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Shopware\Core\Content\Product\Aggregate\ProductManufacturer\ProductManufacturerCollection;
use Shopware\Core\Content\Product\Aggregate\ProductManufacturer\ProductManufacturerDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductManufacturerTranslation\ProductManufacturerTranslationDefinition;
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
final class ManufacturerAdminSearchIndexer extends AbstractAdminIndexer
{
    /**
     * @internal
     *
     * @param EntityRepository<ProductManufacturerCollection> $repository
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
        return ProductManufacturerDefinition::ENTITY_NAME;
    }

    public function getName(): string
    {
        return 'manufacturer-listing';
    }

    public function getIterator(): IterableQuery
    {
        return $this->factory->createIterator($this->getEntity(), null, $this->indexingBatchSize);
    }

    public function getUpdatedIds(EntityWrittenContainerEvent $event): array
    {
        $productManufacturerIds = $event->getPrimaryKeys($this->getEntity());

        $translations = $event->getPrimaryKeysWithPropertyChange(ProductManufacturerTranslationDefinition::ENTITY_NAME, [
            'name',
        ]);

        foreach ($translations as $pks) {
            if (isset($pks['productManufacturerId'])) {
                $productManufacturerIds[] = $pks['productManufacturerId'];
            }
        }

        return array_values(array_unique(array_filter($productManufacturerIds, '\is_string')));
    }

    public function mapping(array $mapping): array
    {
        if (!Feature::isActive('ENABLE_OPENSEARCH_FOR_ADMIN_API')) {
            return parent::mapping($mapping);
        }

        $languageFields = $this->fieldBuilder->translated(AbstractElasticsearchDefinition::KEYWORD_FIELD);

        $override = [
            'name' => $languageFields,
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

    /**
     * @return array<string, array{id:string, text:string}>
     */
    public function fetch(array $ids): array
    {
        $data = $this->connection->fetchAllAssociative(
            <<<'SQL'
            SELECT LOWER(HEX(product_manufacturer.id)) as id,
                   GROUP_CONCAT(DISTINCT product_manufacturer_translation.name SEPARATOR " ") as name,
                   JSON_ARRAYAGG(JSON_OBJECT(
                       'languageId', LOWER(HEX(product_manufacturer_translation.language_id)),
                       'name', product_manufacturer_translation.name
                   )) as translatedNames,
                   product_manufacturer.created_at as createdAt
            FROM product_manufacturer
                INNER JOIN product_manufacturer_translation
                    ON product_manufacturer.id = product_manufacturer_translation.product_manufacturer_id
            WHERE product_manufacturer.id IN (:ids)
            GROUP BY product_manufacturer.id
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
                'createdAt' => $this->formatDateTime($row, 'createdAt'),
            ];
        }

        return $mapped;
    }
}
