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
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\JoinGroupBuilder;
use Shopware\Core\Framework\DataAbstractionLayer\Doctrine\MultiInsertQueryQueue;
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
    /**
     * Conditions per `searchIds` call. MariaDB/MySQL joins at most 61 tables per
     * query (see issue #10770) and the joins per condition are not bounded, so
     * {@see searchMatchingProductIds} halves this and retries on error 1116.
     */
    private const CONDITION_CHUNK_SIZE = 20;

    /**
     * Candidate ids per query. Keeps intermediate results below the Elasticsearch
     * result window and the maximum number of SQL placeholders.
     */
    private const ID_CHUNK_SIZE = 500;

    /**
     * MariaDB/MySQL "Too many tables" error.
     */
    private const TOO_MANY_TABLES_ERROR_CODE = 1116;

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
        return 'product_stream_mapping.indexer';
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

        $parsedFilters = $this->parseFilters($filter);
        if ($parsedFilters === null) {
            return;
        }

        $binaryStreamId = Uuid::fromHexToBytes($streamId);

        /** @var list<string> $oldMatches */
        $oldMatches = $this->connection->fetchFirstColumn(
            'SELECT LOWER(HEX(product_id)) FROM product_stream_mapping WHERE product_stream_id = :id',
            ['id' => $binaryStreamId],
        );

        try {
            $newMatches = $this->collectMatchingIdsInLanguageContexts($this->getLanguageContexts($message->getContext()), $parsedFilters, null, true);
        } catch (UnmappedFieldException|DeprecatedUnmappedFieldException) {
            // @deprecated tag:v6.8.0 - drop DeprecatedUnmappedFieldException, unmappedField() only returns UnmappedFieldException then
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
     * @param list<string> $ids
     */
    public function updateProducts(array $ids, Context $context): void
    {
        if (!$this->indexingEnabled) {
            return;
        }

        $streams = $this->connection->fetchAllAssociative('SELECT id, api_filter FROM product_stream WHERE invalid = 0 AND api_filter IS NOT NULL');

        $insert = new MultiInsertQueryQueue($this->connection);

        $version = Uuid::fromHexToBytes(Defaults::LIVE_VERSION);

        $languageContexts = $this->getLanguageContexts($context);

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
                // `updateProducts` runs inside the product indexer, directly after the
                // products have been written. The Elasticsearch documents of those
                // products are only written afterwards, so this path has to stay on the
                // SQL implementation to see the current data.
                $matchedIds = $this->collectMatchingIdsInLanguageContexts($languageContexts, $parsedFilters, $ids, false);
            } catch (UnmappedFieldException|DeprecatedUnmappedFieldException) {
                // @deprecated tag:v6.8.0 - drop DeprecatedUnmappedFieldException, unmappedField() only returns UnmappedFieldException then
                // skip if filter field is not found
                continue;
            }

            foreach ($matchedIds as $id) {
                $insert->addInsert('product_stream_mapping', [
                    'product_id' => Uuid::fromHexToBytes($id),
                    'product_version_id' => $version,
                    'product_stream_id' => $stream['id'],
                ]);
            }
        }

        RetryableTransaction::retryable($this->connection, function () use ($ids, $insert): void {
            $this->connection->executeStatement(
                'DELETE FROM product_stream_mapping WHERE product_id IN (:ids)',
                ['ids' => Uuid::fromHexToBytesList($ids)],
                ['ids' => ArrayParameterType::BINARY]
            );
            $insert->execute();
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
     * Collects the products matching `$parsedFilters` across every relevant
     * language context and returns the union of their ids. The chunked search
     * (see {@see searchMatchingProductIds}) is executed once per language
     * context so translated fields are matched in each language while still
     * keeping every individual SQL query below the MariaDB/MySQL 61-table
     * join limit.
     *
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

        foreach ($languageContexts as $languageContext) {
            $languageMatches = $languageContext->enableInheritance(
                fn (Context $context): array => $this->searchMatchingProductIds($parsedFilters, $restrictToIds, $context, $elasticsearchAware)
            );

            foreach ($languageMatches as $id) {
                $matches[$id] = true;
            }
        }

        return array_keys($matches);
    }

    /**
     * Parses the raw api_filter payload of a product stream into the Filter
     * DAL representation. The returned list is the conjunction of all contained
     * filters, exactly as it would be passed to `Criteria::addFilter`.
     *
     * @param array<int, array<string, mixed>> $filters
     *
     * @return list<Filter>|null null when the filter is empty and nothing needs to be indexed
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
     * Executes the conjunction of `$parsedFilters`, splitting it over multiple
     * `searchIds` calls when it would otherwise generate a single query that
     * joins more than 61 tables (MariaDB/MySQL hard limit, see issue #10770).
     *
     * The number of joins a condition needs cannot be determined upfront, so
     * the batch size is reduced and the search is repeated whenever the
     * database still reports the limit.
     *
     * @param list<Filter> $parsedFilters
     * @param list<string>|null $restrictToIds
     *
     * @return list<string>
     */
    private function searchMatchingProductIds(array $parsedFilters, ?array $restrictToIds, Context $context, bool $elasticsearchAware): array
    {
        $chunkSize = self::CONDITION_CHUNK_SIZE;

        while (true) {
            try {
                return $this->searchChunked($this->chunkFilters($parsedFilters, $chunkSize), $restrictToIds, $context, $elasticsearchAware);
            } catch (\Throwable $e) {
                if ($chunkSize <= 1 || !$this->isTooManyTablesError($e)) {
                    throw $e;
                }

                $chunkSize = intdiv($chunkSize, 2);
            }
        }
    }

    /**
     * @param list<list<Filter>> $chunks
     * @param list<string>|null $restrictToIds
     *
     * @return list<string>
     */
    private function searchChunked(array $chunks, ?array $restrictToIds, Context $context, bool $elasticsearchAware): array
    {
        if (\count($chunks) <= 1) {
            // The whole conjunction fits into one query. No intermediate result
            // set is created, so this behaves exactly like an unsplit criteria.
            return $this->searchIds($chunks[0] ?? [], $restrictToIds, $context, $elasticsearchAware);
        }

        // More than one query is needed. Every one of them is restricted to a
        // bounded page of candidate ids, otherwise an intermediate result could
        // exceed the Elasticsearch result window (silently dropping matches) or
        // the maximum number of SQL placeholders.
        $matches = [];
        foreach ($this->iterateCandidateIds($restrictToIds) as $candidateIds) {
            foreach ($chunks as $chunk) {
                $candidateIds = $this->searchIds($chunk, $candidateIds, $context, $elasticsearchAware);

                if ($candidateIds === []) {
                    break;
                }
            }

            $matches = [...$matches, ...$candidateIds];
        }

        return $matches;
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

        /** @var list<string> $ids */
        $ids = $this->repository->searchIds($criteria, $context)->getIds();

        return $ids;
    }

    /**
     * Yields the candidate ids a chunked search has to be run against, in pages
     * of at most `self::ID_CHUNK_SIZE`. Without a restriction the whole product
     * table is iterated, because the first chunk of conditions on its own can
     * match an arbitrary number of products.
     *
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
            /** @var list<string> $binaryIds */
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
     * Splits the conjunction into batches of at most `$chunkSize` conditions.
     *
     * Filters are only ever regrouped in a way that keeps the generated SQL
     * equivalent: a multi filter wrapping a single query is unwrapped (the
     * Administration always wraps a stream into such an `OR` container), and a
     * top-level `AND` filter is split into groups of conditions that address the
     * same to-many association, so {@see JoinGroupBuilder} keeps building the
     * join groups it built for the unsplit criteria.
     *
     * A disjunction cannot be split, so a stream that combines several `OR`
     * groups still ends up in a single query and can exceed the join limit.
     *
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
     * Splits a single filter into an equivalent list of filters that may be
     * distributed over multiple queries. Returns the filter unchanged whenever
     * splitting it would change the result set.
     *
     * @return list<Filter>
     */
    private function splitFilter(Filter $filter): array
    {
        if (!$filter instanceof MultiFilter || $filter instanceof NotFilter) {
            return [$filter];
        }

        $queries = array_values($filter->getQueries());

        // A container holding a single query is equivalent to that query, no
        // matter which operator it uses.
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

            // Conditions sharing a to-many association have to stay inside a
            // single filter, otherwise the DAL joins that association once per
            // condition instead of once per group.
            $splitted[] = new AndFilter($group);
        }

        return $splitted;
    }

    /**
     * Groups filters so that all filters referencing the same to-many
     * association end up in the same group. Filters that reference no to-many
     * association can be evaluated in separate queries without changing the
     * result and therefore form a group of their own.
     *
     * @param list<Filter> $filters
     *
     * @return list<list<Filter>>
     */
    private function groupByToManyAssociation(array $filters): array
    {
        /** @var array<string, int> $groupByPath */
        $groupByPath = [];
        /** @var array<int, list<Filter>> $groups */
        $groups = [];
        // never reuse an index: merging removes keys, so `count($groups)` would collide
        $nextGroup = 0;

        foreach ($filters as $filter) {
            $paths = $this->getToManyPaths($filter);

            $targets = [];
            foreach ($paths as $path) {
                if (isset($groupByPath[$path])) {
                    $targets[$groupByPath[$path]] = true;
                }
            }
            $targets = array_keys($targets);

            $group = $targets[0] ?? $nextGroup++;
            $groups[$group] = [...$groups[$group] ?? [], $filter];

            // The filter bridges groups that were separate until now, merge them.
            foreach (\array_slice($targets, 1) as $merged) {
                $groups[$group] = [...$groups[$group], ...$groups[$merged]];
                unset($groups[$merged]);
            }

            foreach ($groupByPath as $path => $index) {
                if (\in_array($index, $targets, true)) {
                    $groupByPath[$path] = $group;
                }
            }
            foreach ($paths as $path) {
                $groupByPath[$path] = $group;
            }
        }

        return array_values($groups);
    }

    /**
     * @return list<string> the paths to the first to-many association of every field of the filter
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

    /**
     * Mirrors `JoinGroupBuilder::findToManyPath`: only to-many associations are
     * joined more than once and can therefore multiply rows.
     */
    private function getToManyPath(string $accessor): ?string
    {
        $definition = $this->productDefinition;

        $parts = explode('.', str_replace('extensions.', '', $accessor));
        if (($parts[0] ?? null) === $definition->getEntityName()) {
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

    private function isTooManyTablesError(\Throwable $e): bool
    {
        for ($current = $e; $current !== null; $current = $current->getPrevious()) {
            if ((int) $current->getCode() === self::TOO_MANY_TABLES_ERROR_CODE) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<int, array<string, mixed>> $filters
     *
     * @return array<int, array<string, mixed>>
     */
    private function replaceCheapestPriceFilters(array $filters): array
    {
        foreach ($filters as $key => $filter) {
            if (\is_array($filter['queries'] ?? null) && $filter['queries'] !== []) {
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
