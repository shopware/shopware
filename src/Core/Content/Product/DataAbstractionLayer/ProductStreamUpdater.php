<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\DataAbstractionLayer;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Product\ProductException;
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
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexer;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexingMessage;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\ManyToManyIdFieldUpdater;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotEqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Parser\QueryStringParser;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
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

    private const ELASTICSEARCH_PAGE_SIZE = 1000;

    private const PAGE_SIZE = 20000;

    /**
     * @see \Shopware\Elasticsearch\Framework\DataAbstractionLayer\ElasticsearchEntitySearcher::RESULT_STATE
     */
    private const ELASTICSEARCH_RESULT_STATE = 'loaded-by-elastic';

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
        $criteria = null;
        if ((int) $stream['invalid'] === 0 && $stream['api_filter'] !== null) {
            $filter = json_decode((string) $stream['api_filter'], true, 512, \JSON_THROW_ON_ERROR);

            if (\is_array($filter)) {
                $criteria = $this->getCriteria($filter);
            }
        }

        $criteria?->addState(Criteria::STATE_ELASTICSEARCH_AWARE);

        $binaryStreamId = Uuid::fromHexToBytes($streamId);
        $context = $message->getContext();

        try {
            $this->syncMappings($binaryStreamId, $criteria, $criteria === null ? [] : $this->getLanguageContexts($context), $context);
        } catch (UnmappedFieldException|DeprecatedUnmappedFieldException) {
            // @deprecated tag:v6.8.0 - drop DeprecatedUnmappedFieldException, unmappedField() only returns UnmappedFieldException then
            // invalid filter, remove all mappings
            $this->syncMappings($binaryStreamId, null, [], $context);
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

            $criteria = $this->getCriteria($filter, $ids);

            if ($criteria === null) {
                continue;
            }

            try {
                $matchedIds = $this->collectMatchingIdsInLanguageContexts($languageContexts, $criteria);
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
     * @param list<Context> $languageContexts
     */
    private function syncMappings(string $binaryStreamId, ?Criteria $criteria, array $languageContexts, Context $context): void
    {
        $pageSize = $criteria === null ? self::PAGE_SIZE : self::ELASTICSEARCH_PAGE_SIZE;
        $after = 0;

        $matchPages = array_fill(0, \count($languageContexts), []);
        $hasMoreMatches = array_fill(0, \count($languageContexts), true);
        /** @var array<int, string> $mappingPage */
        $mappingPage = [];
        $hasMoreMappings = true;

        do {
            foreach ($languageContexts as $index => $languageContext) {
                if ($criteria === null || $matchPages[$index] !== [] || !$hasMoreMatches[$index]) {
                    continue;
                }

                $limit = $pageSize;
                $result = $this->searchMatchPage($criteria, $languageContext, $after, $limit);
                if (!$result->hasState(self::ELASTICSEARCH_RESULT_STATE)) {
                    $pageSize = self::PAGE_SIZE;
                }

                $matchPages[$index] = $this->indexByAutoIncrement($result);
                $hasMoreMatches[$index] = \count($matchPages[$index]) === $limit;
            }

            if ($mappingPage === [] && $hasMoreMappings) {
                [$mappingPage, $hasMoreMappings] = $this->fetchMappingPage($binaryStreamId, $after, $pageSize);
            }

            $until = \PHP_INT_MAX;
            foreach ($matchPages as $index => $page) {
                if ($hasMoreMatches[$index]) {
                    $until = min($until, (int) array_key_last($page));
                }
            }
            if ($hasMoreMappings) {
                $until = min($until, (int) array_key_last($mappingPage));
            }

            $matched = [];
            foreach ($matchPages as $index => $page) {
                [$window, $matchPages[$index]] = $this->splitPage($page, $until);
                $matched += array_fill_keys($window, true);
            }

            [$window, $mappingPage] = $this->splitPage($mappingPage, $until);
            $mapped = array_fill_keys($window, true);

            $this->writeWindow(
                $binaryStreamId,
                array_keys(array_diff_key($matched, $mapped)),
                array_keys(array_diff_key($mapped, $matched)),
                $context,
            );

            $after = $until;
        } while ($until !== \PHP_INT_MAX);
    }

    private function searchMatchPage(Criteria $criteria, Context $languageContext, int $after, int $limit): IdSearchResult
    {
        $pageCriteria = (clone $criteria)
            ->setLimit($limit)
            ->addSorting(new FieldSorting('autoIncrement'))
            ->addFilter(new RangeFilter('autoIncrement', [RangeFilter::GT => $after]));

        return $languageContext->enableInheritance(
            fn (Context $context): IdSearchResult => $this->repository->searchIds($pageCriteria, $context)
        );
    }

    /**
     * @return array<int, string>
     */
    private function indexByAutoIncrement(IdSearchResult $result): array
    {
        $page = [];
        foreach ($result->getData() as $id => $row) {
            $autoIncrement = $row['autoIncrement'] ?? null;
            if (!\is_int($autoIncrement) && !\is_float($autoIncrement)) {
                throw ProductException::streamMatchWithoutAutoIncrement($id);
            }

            $page[(int) $autoIncrement] = $id;
        }

        return $page;
    }

    /**
     * @return array{array<int, string>, bool}
     */
    private function fetchMappingPage(string $binaryStreamId, int $after, int $limit): array
    {
        /** @var list<array{int|string, string}> $rows */
        $rows = $this->connection->fetchAllNumeric(
            'SELECT product.auto_increment, LOWER(HEX(product.id))
             FROM product_stream_mapping
             INNER JOIN product ON product.id = product_stream_mapping.product_id AND product.version_id = :version
             WHERE product_stream_mapping.product_stream_id = :streamId AND product.auto_increment > :after
             ORDER BY product.auto_increment
             LIMIT :limit',
            [
                'streamId' => $binaryStreamId,
                'version' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
                'after' => $after,
                'limit' => $limit,
            ],
            ['after' => ParameterType::INTEGER, 'limit' => ParameterType::INTEGER],
        );

        $page = [];
        foreach ($rows as [$autoIncrement, $id]) {
            $page[(int) $autoIncrement] = $id;
        }

        return [$page, \count($rows) === $limit];
    }

    /**
     * @param array<int, string> $page
     *
     * @return array{list<string>, array<int, string>}
     */
    private function splitPage(array $page, int $until): array
    {
        $split = 0;
        foreach (array_keys($page) as $autoIncrement) {
            if ($autoIncrement > $until) {
                break;
            }
            ++$split;
        }

        return [\array_slice($page, 0, $split), \array_slice($page, $split, null, true)];
    }

    /**
     * @param list<string> $toBeAdded
     * @param list<string> $toBeDeleted
     */
    private function writeWindow(string $binaryStreamId, array $toBeAdded, array $toBeDeleted, Context $context): void
    {
        if ($toBeAdded === [] && $toBeDeleted === []) {
            return;
        }

        RetryableTransaction::retryable($this->connection, function () use ($toBeAdded, $toBeDeleted, $binaryStreamId): void {
            foreach (array_chunk($toBeDeleted, 250) as $chunk) {
                $this->connection->executeStatement(
                    'DELETE FROM product_stream_mapping WHERE product_id IN (:ids) AND product_stream_id = :streamId',
                    [
                        'ids' => Uuid::fromHexToBytesList($chunk),
                        'streamId' => $binaryStreamId,
                    ],
                    ['ids' => ArrayParameterType::BINARY],
                );
            }

            $this->insertMappings($toBeAdded, $binaryStreamId);
        });

        foreach (array_chunk([...$toBeAdded, ...$toBeDeleted], 250) as $chunk) {
            $this->manyToManyIdFieldUpdater->update(ProductDefinition::ENTITY_NAME, $chunk, $context, 'streamIds');
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
     *
     * @return list<string>
     */
    private function collectMatchingIdsInLanguageContexts(array $languageContexts, Criteria $criteria): array
    {
        /** @var array<string, true> $matches */
        $matches = [];

        foreach ($languageContexts as $languageContext) {
            $languageMatches = $languageContext->enableInheritance(
                fn (Context $context): array => $this->repository->searchIds($criteria, $context)->getIds()
            );

            foreach ($languageMatches as $id) {
                $matches[$id] = true;
            }
        }

        return array_keys($matches);
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

        if ($filters === []) {
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
