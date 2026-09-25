<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\DataAbstractionLayer;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\ProductStream\Aggregate\ProductStreamFilter\ProductStreamFilterDefinition;
use Shopware\Core\Content\ProductStream\DataAbstractionLayer\ProductStreamWriteResultHelper;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Exception\UnmappedFieldException as DeprecatedUnmappedFieldException;
use Shopware\Core\Framework\DataAbstractionLayer\Doctrine\RetryableTransaction;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\SearchRequestException;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\UnmappedFieldException;
use Shopware\Core\Framework\DataAbstractionLayer\Field\AssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexer;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexingMessage;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\ManyToManyIdFieldUpdater;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\AndFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\Filter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotEqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Parser\QueryStringParser;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Language\LanguageCollection;
use Shopware\Core\System\Language\LanguageEntity;
use Symfony\Component\Messenger\MessageBusInterface;

#[Package('framework')]
class ProductStreamUpdater extends AbstractProductStreamUpdater
{
    public const INDEXER_NAME = 'product_stream_mapping.indexer';

    private const CONDITION_CHUNK_SIZE = 10;

    private const ID_CHUNK_SIZE = 500;

    /**
     * @internal
     *
     * @param EntityRepository<ProductCollection> $repository
     * @param EntityRepository<LanguageCollection> $languageRepository
     */
    public function __construct(
        private readonly Connection $connection,
        private readonly ProductDefinition $productDefinition,
        private readonly EntityRepository $repository,
        private readonly MessageBusInterface $messageBus,
        private readonly ManyToManyIdFieldUpdater $manyToManyIdFieldUpdater,
        private readonly EntityRepository $languageRepository,
        private readonly bool $indexingEnabled,
    ) {
    }

    public function getName(): string
    {
        return self::INDEXER_NAME;
    }

    public function iterate(?array $offset): ?EntityIndexingMessage
    {
        // in full index, the product indexer will call the `updateProducts` method
        return null;
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

        /** @var array{invalid: int|string, api_filter: string|null}|false $stream */
        $stream = $this->connection->fetchAssociative(
            'SELECT invalid, api_filter FROM product_stream WHERE id = :id',
            ['id' => Uuid::fromHexToBytes($streamId)]
        );
        // the stream is gone, its mappings went with it through the foreign key
        if ($stream === false) {
            return;
        }

        // an invalid stream, or one left without filters, has nothing to match against
        $parsedFilters = null;
        if ((int) $stream['invalid'] === 0 && $stream['api_filter'] !== null) {
            $filter = json_decode((string) $stream['api_filter'], true, 512, \JSON_THROW_ON_ERROR);

            if (\is_array($filter)) {
                $parsedFilters = $this->parseFilters($filter);
            }
        }

        $binaryStreamId = Uuid::fromHexToBytes($streamId);

        /** @var list<string> $oldMatches */
        $oldMatches = $this->connection->fetchFirstColumn(
            'SELECT LOWER(HEX(product_id)) FROM product_stream_mapping WHERE product_stream_id = :id',
            ['id' => $binaryStreamId],
        );

        if ($parsedFilters === null) {
            // nothing left to match against, so every mapping the stream still holds has to go
            $newMatches = [];
        } else {
            try {
                $newMatches = $this->collectMatchingIdsInLanguageContexts($this->getLanguageContexts($message->getContext()), $parsedFilters, null, true);
            } catch (UnmappedFieldException|DeprecatedUnmappedFieldException) {
                // @deprecated tag:v6.8.0 - drop DeprecatedUnmappedFieldException, unmappedField() only returns UnmappedFieldException then
                // invalid filter, remove all mappings
                $newMatches = [];
            }
        }

        $toBeAdded = array_values(array_diff($newMatches, $oldMatches));
        $toBeDeleted = array_values(array_diff($oldMatches, $newMatches));

        if ($toBeAdded !== []) {
            RetryableTransaction::retryable($this->connection, function () use ($toBeAdded, $binaryStreamId): void {
                $this->insertMappings($toBeAdded, $binaryStreamId);
            });
        }

        if ($toBeDeleted !== []) {
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

        if ($event->getEventByEntityName(ProductStreamFilterDefinition::ENTITY_NAME) === null) {
            return null;
        }

        $ids = ProductStreamWriteResultHelper::getAffectedStreamIds($event);

        if ($ids === []) {
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
     */
    public function updateProducts(array $ids, Context $context): void
    {
        if (!$this->indexingEnabled) {
            return;
        }

        $streams = $this->connection->fetchAllAssociative('SELECT id, api_filter FROM product_stream WHERE invalid = 0 AND api_filter IS NOT NULL');

        $languageContexts = $this->getLanguageContexts($context);

        /** @var list<array{streamId: string, productIds: list<string>}> $matches */
        $matches = [];

        foreach ($streams as $stream) {
            $filter = json_decode((string) $stream['api_filter'], true, 512, \JSON_THROW_ON_ERROR);
            if (!\is_array($filter) || $filter === []) {
                continue;
            }

            $parsedFilters = $this->parseFilters($filter);

            if ($parsedFilters === null) {
                continue;
            }

            try {
                // runs inside the product indexer, before the Elasticsearch documents
                // of the written products are updated
                $matchedIds = $this->collectMatchingIdsInLanguageContexts($languageContexts, $parsedFilters, array_values($ids), false);
            } catch (UnmappedFieldException|DeprecatedUnmappedFieldException) {
                // @deprecated tag:v6.8.0 - drop DeprecatedUnmappedFieldException, unmappedField() only returns UnmappedFieldException then
                // skip if filter field is not found
                continue;
            }

            if ($matchedIds === []) {
                continue;
            }

            $matches[] = ['streamId' => (string) $stream['id'], 'productIds' => $matchedIds];
        }

        RetryableTransaction::retryable($this->connection, function () use ($ids, $matches): void {
            if ($matches !== []) {
                $this->lockProducts($ids);
            }

            $this->connection->executeStatement(
                'DELETE FROM product_stream_mapping WHERE product_id IN (:ids)',
                ['ids' => Uuid::fromHexToBytesList($ids)],
                ['ids' => ArrayParameterType::BINARY]
            );

            foreach ($matches as $match) {
                $this->insertMappings($match['productIds'], $match['streamId']);
            }
        });
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
     * Locks the products before the mapping rows are touched, as a concurrent product delete takes the same
     * locks in that order through its cascade. Sorted ids keep parallel runs in one order.
     *
     * @param string[] $productIds
     */
    private function lockProducts(array $productIds): void
    {
        $ids = Uuid::fromHexToBytesList($productIds);
        sort($ids);

        foreach (array_chunk($ids, 250) as $chunk) {
            $this->connection->executeStatement(
                'SELECT id FROM product WHERE id IN (:ids) AND version_id = :version ORDER BY id FOR UPDATE',
                [
                    'ids' => $chunk,
                    'version' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
                ],
                ['ids' => ArrayParameterType::BINARY],
            );
        }
    }

    /**
     * Selecting from `product` keeps the existence check in the insert, as the ids come from a search that
     * ran before the write, so a product may be deleted by now.
     *
     * @param list<string> $productIds
     */
    private function insertMappings(array $productIds, string $binaryStreamId): void
    {
        foreach (array_chunk($productIds, 250) as $chunk) {
            $this->connection->executeStatement(
                'INSERT IGNORE INTO product_stream_mapping (product_id, product_version_id, product_stream_id)
                 SELECT product.id, product.version_id, :streamId
                 FROM product
                 WHERE product.id IN (:ids) AND product.version_id = :version',
                [
                    'streamId' => $binaryStreamId,
                    'ids' => Uuid::fromHexToBytesList($chunk),
                    'version' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
                ],
                ['ids' => ArrayParameterType::BINARY],
            );
        }
    }

    /**
     * @return list<Context>
     */
    private function getLanguageContexts(Context $context): array
    {
        $criteria = new Criteria();
        $criteria->addFilter(new NotEqualsFilter('salesChannels.id', null));
        $languages = $this->languageRepository->search($criteria, Context::createDefaultContext())->getEntities();

        return array_values($languages->map(
            fn (LanguageEntity $language): Context => $this->createLanguageContext($context, $language)
        ));
    }

    private function createLanguageContext(Context $context, LanguageEntity $language): Context
    {
        $languageContext = clone $context;
        $languageContext->assign([
            'languageIdChain' => array_values(array_unique(array_filter([$language->getId(), $language->getParentId(), Defaults::LANGUAGE_SYSTEM]))),
        ]);

        return $languageContext;
    }

    /**
     * @param list<Context> $languageContexts
     * @param list<Filter> $parsedFilters
     * @param list<string>|null $restrictToIds
     *
     * @return list<string>
     */
    private function collectMatchingIdsInLanguageContexts(array $languageContexts, array $parsedFilters, ?array $restrictToIds, bool $elasticsearchAware): array
    {
        /** @var array<string, true> $matches */
        $matches = [];

        $chunks = $this->chunkFilters($parsedFilters, self::CONDITION_CHUNK_SIZE);

        if (\count($chunks) <= 1) {
            foreach ($languageContexts as $languageContext) {
                $ids = $languageContext->enableInheritance(
                    fn (Context $context): array => $this->searchIds($chunks[0] ?? [], $restrictToIds, $context, $elasticsearchAware)
                );

                foreach ($ids as $id) {
                    $matches[$id] = true;
                }
            }

            return array_keys($matches);
        }

        foreach ($this->iterateCandidateIds($restrictToIds) as $candidateIds) {
            foreach ($languageContexts as $languageContext) {
                $ids = $languageContext->enableInheritance(
                    fn (Context $context): array => $this->searchPage($chunks, $candidateIds, $context, $elasticsearchAware)
                );

                foreach ($ids as $id) {
                    $matches[$id] = true;
                }
            }
        }

        return array_keys($matches);
    }

    /**
     * @param array<int, array<string, mixed>> $filters
     *
     * @return list<Filter>|null
     */
    private function parseFilters(array $filters): ?array
    {
        if ($filters === []) {
            return null;
        }

        $exception = new SearchRequestException();

        $filters = $this->replaceCheapestPriceFilters($filters);

        $parsed = [];
        foreach ($filters as $filter) {
            $parsed[] = QueryStringParser::fromArray($this->productDefinition, $filter, $exception, '');
        }

        return $parsed;
    }

    /**
     * @param list<list<Filter>> $chunks
     * @param list<string> $candidateIds
     *
     * @return list<string>
     */
    private function searchPage(array $chunks, array $candidateIds, Context $context, bool $elasticsearchAware): array
    {
        foreach ($chunks as $chunk) {
            $candidateIds = $this->searchIds($chunk, $candidateIds, $context, $elasticsearchAware);

            if ($candidateIds === []) {
                return [];
            }
        }

        return $candidateIds;
    }

    /**
     * @param list<Filter> $filters
     * @param list<string>|null $restrictToIds
     *
     * @return list<string>
     */
    private function searchIds(array $filters, ?array $restrictToIds, Context $context, bool $elasticsearchAware): array
    {
        $criteria = new Criteria();
        $criteria->addFilter(...$filters);

        if ($elasticsearchAware) {
            $criteria->addState(Criteria::STATE_ELASTICSEARCH_AWARE);
        }

        if ($restrictToIds !== null) {
            $criteria->addFilter(new EqualsAnyFilter('id', $restrictToIds));
        }

        $ids = $this->repository->searchIds($criteria, $context)->getIds();

        return $ids;
    }

    /**
     * @param list<string>|null $restrictToIds
     *
     * @return \Generator<int, list<string>>
     */
    private function iterateCandidateIds(?array $restrictToIds): \Generator
    {
        if ($restrictToIds !== null) {
            foreach (array_chunk($restrictToIds, self::ID_CHUNK_SIZE) as $chunk) {
                yield $chunk;
            }

            return;
        }

        $lastId = '';
        while (true) {
            $binaryIds = $this->connection->fetchFirstColumn(
                'SELECT id FROM product WHERE version_id = :version AND id > :lastId ORDER BY id LIMIT ' . self::ID_CHUNK_SIZE,
                ['version' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION), 'lastId' => $lastId],
            );

            if ($binaryIds === []) {
                return;
            }

            $lastId = end($binaryIds);

            yield array_map(static fn (string $id): string => bin2hex($id), $binaryIds);
        }
    }

    /**
     * @param list<Filter> $parsedFilters
     *
     * @return list<list<Filter>>
     */
    private function chunkFilters(array $parsedFilters, int $chunkSize): array
    {
        $splitted = [];
        foreach ($parsedFilters as $filter) {
            $splitted = [...$splitted, ...$this->splitFilter($filter)];
        }

        $chunks = [];
        $chunk = [];
        $size = 0;

        foreach ($splitted as $filter) {
            $conditions = max(1, \count($filter->getFields()));

            if ($chunk !== [] && $size + $conditions > $chunkSize) {
                $chunks[] = $chunk;
                $chunk = [];
                $size = 0;
            }

            $chunk[] = $filter;
            $size += $conditions;
        }

        if ($chunk !== []) {
            $chunks[] = $chunk;
        }

        return $chunks;
    }

    /**
     * @return list<Filter>
     */
    private function splitFilter(Filter $filter): array
    {
        if (!$filter instanceof MultiFilter || $filter instanceof NotFilter) {
            return [$filter];
        }

        $queries = array_values($filter->getQueries());

        if (\count($queries) === 1) {
            return $this->splitFilter($queries[0]);
        }

        if ($filter->getOperator() !== MultiFilter::CONNECTION_AND) {
            return [$filter];
        }

        $groups = $this->groupByToManyAssociation($queries);
        if (\count($groups) <= 1) {
            return [$filter];
        }

        $splitted = [];
        foreach ($groups as $group) {
            if (\count($group) === 1) {
                $splitted = [...$splitted, ...$this->splitFilter($group[0])];

                continue;
            }

            $splitted[] = new AndFilter($group);
        }

        return $splitted;
    }

    /**
     * @param list<Filter> $filters
     *
     * @return list<list<Filter>>
     */
    private function groupByToManyAssociation(array $filters): array
    {
        /** @var array<string, int> $groupOfPath */
        $groupOfPath = [];
        /** @var array<int, int> $groupOfFilter */
        $groupOfFilter = [];
        $nextGroup = 0;

        foreach ($filters as $index => $filter) {
            $paths = $this->getToManyPaths($filter);

            $targets = [];
            foreach ($paths as $path) {
                if (isset($groupOfPath[$path])) {
                    $targets[$groupOfPath[$path]] = true;
                }
            }
            $targets = array_keys($targets);

            $group = $targets[0] ?? $nextGroup++;

            foreach ($targets as $target) {
                if ($target === $group) {
                    continue;
                }

                foreach ($groupOfPath as $path => $current) {
                    if ($current === $target) {
                        $groupOfPath[$path] = $group;
                    }
                }

                foreach ($groupOfFilter as $key => $current) {
                    if ($current === $target) {
                        $groupOfFilter[$key] = $group;
                    }
                }
            }

            foreach ($paths as $path) {
                $groupOfPath[$path] = $group;
            }

            $groupOfFilter[$index] = $group;
        }

        /** @var array<int, list<Filter>> $byGroup */
        $byGroup = [];
        foreach ($filters as $index => $filter) {
            $byGroup[$groupOfFilter[$index]][] = $filter;
        }

        $grouped = [];
        foreach ($byGroup as $group) {
            $grouped[] = $group;
        }

        return $grouped;
    }

    /**
     * @return list<string>
     */
    private function getToManyPaths(Filter $filter): array
    {
        $paths = [];
        foreach ($filter->getFields() as $field) {
            $path = $this->getToManyPath($field);

            if ($path !== null) {
                $paths[$path] = true;
            }
        }

        return array_keys($paths);
    }

    private function getToManyPath(string $accessor): ?string
    {
        $definition = $this->productDefinition;

        $parts = explode('.', str_replace('extensions.', '', $accessor));
        if ($parts[0] === $definition->getEntityName()) {
            array_shift($parts);
        }

        $path = [$definition->getEntityName()];

        foreach ($parts as $part) {
            $field = $definition->getFields()->get($part);

            if (!$field instanceof AssociationField) {
                return null;
            }

            $path[] = $field->getPropertyName();

            if ($field instanceof ManyToManyAssociationField || $field instanceof OneToManyAssociationField) {
                return implode('.', $path);
            }

            $definition = $field->getReferenceDefinition();
        }

        return null;
    }

    /**
     * @param array<int, array<string, mixed>> $filters
     *
     * @return array<int, array<string, mixed>>
     */
    private function replaceCheapestPriceFilters(array $filters): array
    {
        foreach ($filters as $key => $filter) {
            $queries = $filter['queries'] ?? null;
            if (\is_array($queries) && $queries !== []) {
                /** @var non-empty-array<int, array<string, mixed>> $queries */
                $filters[$key]['queries'] = $this->replaceCheapestPriceFilters($queries);
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
