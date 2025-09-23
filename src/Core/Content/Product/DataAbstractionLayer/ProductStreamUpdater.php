<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\DataAbstractionLayer;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Shopware\Core\Content\Product\Aggregate\ProductCategory\ProductCategoryDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductManufacturer\ProductManufacturerDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductOption\ProductOptionDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductPrice\ProductPriceDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductProperty\ProductPropertyDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductTag\ProductTagDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductTranslation\ProductTranslationDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\ProductStream\ProductStreamDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Exception\UnmappedFieldException;
use Shopware\Core\Framework\DataAbstractionLayer\Doctrine\FetchModeHelper;
use Shopware\Core\Framework\DataAbstractionLayer\Doctrine\MultiInsertQueryQueue;
use Shopware\Core\Framework\DataAbstractionLayer\Doctrine\RetryableTransaction;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\SearchRequestException;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexer;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexingMessage;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\ManyToManyIdFieldUpdater;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Parser\QueryStringParser;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\Messenger\MessageBusInterface;

#[Package('framework')]
class ProductStreamUpdater extends AbstractProductStreamUpdater
{
    private const PRODUCT_FILTER_FIELD_WHITELIST = [
        ProductDefinition::ENTITY_NAME => [
            'active',
            'ratingAverage',
            'cheapestPrice',
            'productNumber',
            'stock',
            'availableStock',
            'releaseDate',
            'tags',
            'weight',
            'height',
            'width',
            'length',
            'ean',
            'sales',
            'manufacturer',
            'manufacturerNumber',
            'categoriesRo',
            'shippingFree',
            'visibilities',
            'properties',
            'options',
            'isCloseout',
            'deliveryTime',
            'purchasePrices',
            'createdAt',
            'coverId',
            'markAsTopseller',
            'price',
            'states',
        ],
        ProductTranslationDefinition::ENTITY_NAME => [
            'name',
            'description',
        ],
        ProductVisibilityDefinition::ENTITY_NAME => [],
        ProductPropertyDefinition::ENTITY_NAME => [],
        ProductOptionDefinition::ENTITY_NAME => [],
        ProductTagDefinition::ENTITY_NAME => [],
        ProductManufacturerDefinition::ENTITY_NAME => [],
        ProductCategoryDefinition::ENTITY_NAME => [],
        ProductPriceDefinition::ENTITY_NAME => [],
    ];

    private const PRODUCT_STREAM_FILTER_FIELD_MAP = [
        ProductVisibilityDefinition::ENTITY_NAME => ['visibilities.salesChannel.id', 'visibilities.product.id'],
        ProductOptionDefinition::ENTITY_NAME => ['options.id', 'options.group.id'],
        ProductPropertyDefinition::ENTITY_NAME => ['properties.id', 'properties.group.id', 'options.id', 'options.group.id'],
        ProductTagDefinition::ENTITY_NAME => ['tags.id'],
        ProductManufacturerDefinition::ENTITY_NAME => ['manufacturer.id'],
        ProductCategoryDefinition::ENTITY_NAME => ['categoriesRo.id'],
        ProductPriceDefinition::ENTITY_NAME => ['cheapestPrice', 'cheapestPrice.percentage'],
        'price' => ['cheapestPrice', 'cheapestPrice.percentage'],
    ];

    /**
     * @internal
     *
     * @param EntityRepository<ProductCollection> $repository
     */
    public function __construct(
        private readonly Connection $connection,
        private readonly ProductDefinition $productDefinition,
        private readonly EntityRepository $repository,
        private readonly MessageBusInterface $messageBus,
        private readonly ManyToManyIdFieldUpdater $manyToManyIdFieldUpdater,
        private readonly bool $indexingEnabled,
    ) {
    }

    public function getName(): string
    {
        return 'product_stream_mapping.indexer';
    }

    public function iterate(?array $offset): ?EntityIndexingMessage
    {
        // in full index, the product indexer will call the `updateProducts` method
        return null;
    }

    /**
     * @param array<string, array<string>> $fieldsChangeSet
     *
     * @return array<string>
     */
    public function getAffectedFilterFields(array $fieldsChangeSet): array
    {
        $affectedFields = [];
        foreach ($fieldsChangeSet as $entityName => $payload) {
            if (!\array_key_exists($entityName, self::PRODUCT_FILTER_FIELD_WHITELIST)) {
                continue;
            }

            if (isset(self::PRODUCT_STREAM_FILTER_FIELD_MAP[$entityName])) {
                $affectedFields = array_merge($affectedFields, self::PRODUCT_STREAM_FILTER_FIELD_MAP[$entityName]);
            } else {
                $fields = array_intersect($payload, self::PRODUCT_FILTER_FIELD_WHITELIST[$entityName]);
                $affectedFields = array_merge($affectedFields, $fields);
            }
        }

        return array_values(array_unique($affectedFields));
    }

    public function handle(EntityIndexingMessage $message): void
    {
        if (!$message instanceof ProductStreamMappingIndexingMessage) {
            return;
        }

        $streamId = $message->getData();
        if (!\is_string($streamId)) {
            return;
        }

        $filter = $this->connection->fetchOne(
            'SELECT api_filter FROM product_stream WHERE invalid = 0 AND api_filter IS NOT NULL AND id = :id',
            ['id' => Uuid::fromHexToBytes($streamId)]
        );
        // if the filter is invalid
        if ($filter === false) {
            return;
        }

        $version = Uuid::fromHexToBytes(Defaults::LIVE_VERSION);

        $filter = json_decode((string) $filter, true, 512, \JSON_THROW_ON_ERROR);

        $criteria = $this->getCriteria($filter);
        $criteria->addState(Criteria::STATE_ELASTICSEARCH_AWARE);

        if ($criteria === null) {
            return;
        }

        $considerInheritance = $message->getContext()->considerInheritance();
        $message->getContext()->setConsiderInheritance(true);

        $binaryStreamId = Uuid::fromHexToBytes($streamId);

        /** @var list<string> $oldMatches */
        $oldMatches = $this->connection->fetchFirstColumn(
            'SELECT LOWER(HEX(product_id)) FROM product_stream_mapping WHERE product_stream_id = :id',
            ['id' => $binaryStreamId],
        );

        try {
            /** @var list<string> $newMatches */
            $newMatches = $this->repository->searchIds($criteria, $message->getContext())->getIds();
        } catch (UnmappedFieldException) {
            // invalid filter, remove all mappings
            $newMatches = [];
        }

        $toBeAdded = array_values(array_diff($newMatches, $oldMatches));
        $toBeDeleted = array_values(array_diff($oldMatches, $newMatches));

        $insert = new MultiInsertQueryQueue($this->connection, 250, false, false);

        foreach ($toBeAdded as $id) {
            $insert->addInsert('product_stream_mapping', [
                'product_id' => Uuid::fromHexToBytes($id),
                'product_version_id' => $version,
                'product_stream_id' => $binaryStreamId,
            ]);
        }

        $insert->execute();

        if (!empty($toBeDeleted)) {
            RetryableTransaction::retryable($this->connection, function () use ($toBeDeleted, $binaryStreamId): void {
                $this->connection->executeStatement(
                    'DELETE FROM product_stream_mapping WHERE product_id IN (:ids) AND product_stream_id = :streamId',
                    [
                        'ids' => Uuid::fromHexToBytesList($toBeDeleted),
                        'streamId' => $binaryStreamId,
                    ],
                    ['ids' => ArrayParameterType::BINARY],
                );
            });
        }

        $message->getContext()->setConsiderInheritance($considerInheritance);

        $ids = array_unique([...$toBeAdded, ...$toBeDeleted]);

        foreach (array_chunk($ids, 250) as $chunkedIds) {
            $this->manyToManyIdFieldUpdater->update(
                ProductDefinition::ENTITY_NAME,
                $chunkedIds,
                $message->getContext(),
                'streamIds'
            );
        }
    }

    public function update(EntityWrittenContainerEvent $event): ?EntityIndexingMessage
    {
        if (!$this->indexingEnabled) {
            return null;
        }

        $ids = $event->getPrimaryKeys(ProductStreamDefinition::ENTITY_NAME);

        if (empty($ids)) {
            return null;
        }

        foreach ($ids as $id) {
            $message = new ProductStreamMappingIndexingMessage($id);
            $message->setIndexer($this->getName());
            $this->messageBus->dispatch($message);
        }

        return null;
    }

    /**
     * @param string[] $ids
     * @param string[] $affectedFields
     */
    public function updateProducts(array $ids, Context $context, array $affectedFields = []): void
    {
        if (!$this->indexingEnabled) {
            return;
        }

        if (!empty($affectedFields)) {
            $streams = $this->connection->fetchAllAssociative(
                'SELECT ps.id AS id,
               ps.api_filter
        FROM product_stream ps
        WHERE ps.invalid = 0
      AND ps.api_filter IS NOT NULL
      AND EXISTS (
          SELECT 1
          FROM product_stream_filter psf
          WHERE psf.product_stream_id = ps.id
            AND psf.field IN (:fields)
          LIMIT 1
      )',
                ['fields' => $affectedFields],
                ['fields' => ArrayParameterType::STRING]
            );
        } else {
            $streams = $this->connection->fetchAllAssociative('SELECT id, api_filter FROM product_stream WHERE invalid = 0 AND api_filter IS NOT NULL');
        }

        if (empty($streams)) {
            return;
        }

        $insert = new MultiInsertQueryQueue($this->connection);

        $version = Uuid::fromHexToBytes(Defaults::LIVE_VERSION);

        $considerInheritance = $context->considerInheritance();
        $context->setConsiderInheritance(true);

        /** @var list<array<string, string>> $result */
        $result = $this->connection->fetchAllAssociative(
            'SELECT product_stream_id, LOWER(HEX(product_id)) as product_id FROM product_stream_mapping WHERE product_stream_id IN (:ids) AND product_id IN (:productIds)',
            ['ids' => array_column($streams, 'id'), 'productIds' => Uuid::fromHexToBytesList($ids)],
            ['ids' => ArrayParameterType::BINARY, 'productIds' => ArrayParameterType::BINARY]
        );

        $oldMatches = FetchModeHelper::group($result);

        foreach ($streams as $stream) {
            $filter = json_decode((string) $stream['api_filter'], true, 512, \JSON_THROW_ON_ERROR);
            if (empty($filter)) {
                continue;
            }

            $oldMatchesOfStream = array_column($oldMatches[$stream['id']] ?? [], 'product_id');
            $criteria = $this->getCriteria($filter, $ids);

            if ($criteria === null) {
                continue;
            }

            try {
                $newMatches = $this->repository->searchIds($criteria, $context)->getIds();
            } catch (UnmappedFieldException) {
                // skip if filter field is not found
                continue;
            }

            $toBeDeleted = array_values(array_diff($oldMatchesOfStream, $newMatches));
            $toBeAdded = array_values(array_diff($newMatches, $oldMatchesOfStream));

            foreach ($toBeAdded as $id) {
                $insert->addInsert('product_stream_mapping', [
                    'product_id' => Uuid::fromHexToBytes($id),
                    'product_version_id' => $version,
                    'product_stream_id' => $stream['id'],
                ]);
            }

            if (empty($toBeDeleted)) {
                continue;
            }

            RetryableTransaction::retryable($this->connection, function () use ($toBeDeleted, $stream): void {
                $this->connection->executeStatement(
                    'DELETE FROM product_stream_mapping WHERE product_id IN (:ids) AND product_stream_id = :streamId',
                    [
                        'ids' => Uuid::fromHexToBytesList($toBeDeleted),
                        'streamId' => $stream['id'],
                    ],
                    ['ids' => ArrayParameterType::BINARY],
                );
            });
        }

        $context->setConsiderInheritance($considerInheritance);

        $insert->execute();
    }

    public function getTotal(): int
    {
        // full index will be done over product indexer
        return 0;
    }

    public function getDecorated(): EntityIndexer
    {
        throw new DecorationPatternException(static::class);
    }

    /**
     * @param array<int, array<string, mixed>> $filters
     * @param string[]|null $ids
     */
    private function getCriteria(array $filters, ?array $ids = null): ?Criteria
    {
        $exception = new SearchRequestException();

        $filters = $this->replaceCheapestPriceFilters($filters);
        $parsed = [];
        foreach ($filters as $filter) {
            $parsed[] = QueryStringParser::fromArray($this->productDefinition, $filter, $exception, '');
        }

        if (empty($filters)) {
            return null;
        }

        $criteria = new Criteria();
        $criteria->addFilter(...$parsed);

        if ($ids !== null) {
            $criteria->addFilter(new EqualsAnyFilter('id', $ids));
        }

        return $criteria;
    }

    /**
     * @param array<int, array<string, mixed>> $filters
     *
     * @return array<int, array<string, mixed>>
     */
    private function replaceCheapestPriceFilters(array $filters): array
    {
        foreach ($filters as $key => $filter) {
            if (!empty($filter['queries'])) {
                $filters[$key]['queries'] = $this->replaceCheapestPriceFilters($filter['queries']);
            }

            if (!$priceQueries = $this->getPriceQueries($filter)) {
                continue;
            }

            $filters[$key] = [
                'type' => 'multi',
                'operator' => 'OR',
                'queries' => $priceQueries,
            ];
        }

        return $filters;
    }

    /**
     * @param array<string, mixed> $filter
     *
     * @return array<int, array<string, mixed>>|null
     */
    private function getPriceQueries(array $filter): ?array
    {
        if (!\array_key_exists('field', $filter)) {
            return null;
        }

        $fieldName = $filter['field'];

        $prefix = $this->productDefinition->getEntityName() . '.';
        if (str_starts_with((string) $fieldName, $prefix)) {
            $fieldName = substr((string) $fieldName, \strlen($prefix));
        }

        $accessors = explode('.', (string) $fieldName);
        if (($accessors[0] ?? '') !== 'cheapestPrice') {
            return null;
        }

        $accessors[0] = '';
        $accessors = implode('.', $accessors);

        return [
            [...$filter, ...['field' => $prefix . 'price' . $accessors]],
            [...$filter, ...['field' => $prefix . 'prices.price' . $accessors]],
        ];
    }
}
