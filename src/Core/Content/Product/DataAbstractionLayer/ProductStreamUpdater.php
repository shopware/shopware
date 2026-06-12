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
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Exception\UnmappedFieldException;
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
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotEqualsFilter;
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

        $criteria = $this->getCriteria($filter);
        $criteria?->addState(Criteria::STATE_ELASTICSEARCH_AWARE);

        if ($criteria === null) {
            return;
        }

        $binaryStreamId = Uuid::fromHexToBytes($streamId);

        /** @var list<string> $oldMatches */
        $oldMatches = $this->connection->fetchFirstColumn(
            'SELECT LOWER(HEX(product_id)) FROM product_stream_mapping WHERE product_stream_id = :id',
            ['id' => $binaryStreamId],
        );

        try {
            $newMatches = $this->collectMatchingIdsInLanguageContexts($this->getLanguageContexts($message->getContext()), $criteria);
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

        $insert = new MultiInsertQueryQueue($this->connection);

        $version = Uuid::fromHexToBytes(Defaults::LIVE_VERSION);

        $languageContexts = $this->getLanguageContexts($context);

        foreach ($streams as $stream) {
            $filter = json_decode((string) $stream['api_filter'], true, 512, \JSON_THROW_ON_ERROR);
            if (empty($filter)) {
                continue;
            }

            $criteria = $this->getCriteria($filter, $ids);

            if ($criteria === null) {
                continue;
            }

            try {
                $matchedIds = $this->collectMatchingIdsInLanguageContexts($languageContexts, $criteria);
            } catch (UnmappedFieldException) {
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
