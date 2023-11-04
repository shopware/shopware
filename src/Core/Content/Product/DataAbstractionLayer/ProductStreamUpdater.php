<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\DataAbstractionLayer;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\ProductStream\ProductStreamDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\RepositoryIterator;
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
use Shopware\Core\Framework\DataAbstractionLayer\Search\Parser\QueryStringParser;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\Messenger\MessageBusInterface;

#[Package('core')]
class ProductStreamUpdater extends AbstractProductStreamUpdater
{
    /**
     * @internal
     */
    public function __construct(
        private readonly Connection $connection,
        private readonly ProductDefinition $productDefinition,
        private readonly EntityRepository $repository,
        private readonly MessageBusInterface $messageBus,
        private readonly ManyToManyIdFieldUpdater $manyToManyIdFieldUpdater
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

        $id = $message->getData();
        if (!\is_string($id)) {
            return;
        }

        $filter = $this->connection->fetchOne(
            'SELECT api_filter FROM product_stream WHERE invalid = 0 AND api_filter IS NOT NULL AND id = :id',
            ['id' => Uuid::fromHexToBytes($id)]
        );
        // if the filter is invalid
        if ($filter === false) {
            return;
        }

        $insert = new MultiInsertQueryQueue($this->connection, 250, false, true);

        $version = Uuid::fromHexToBytes(Defaults::LIVE_VERSION);

        $filter = json_decode((string) $filter, true, 512, \JSON_THROW_ON_ERROR);

        $criteria = $this->getCriteria($filter);

        if ($criteria === null) {
            return;
        }

        $criteria->setLimit(150);
        $iterator = new RepositoryIterator(
            $this->repository,
            $message->getContext(),
            $criteria
        );

        $binary = Uuid::fromHexToBytes($id);

        $ids = $this->connection->fetchFirstColumn(
            'SELECT LOWER(HEX(product_id)) FROM product_stream_mapping WHERE product_stream_id = :id',
            ['id' => $binary],
        );

        RetryableTransaction::retryable($this->connection, function () use ($binary): void {
            $this->connection->executeStatement(
                'DELETE FROM product_stream_mapping WHERE product_stream_id = :id',
                ['id' => $binary],
            );
        });

        while ($matches = $iterator->fetchIds()) {
            foreach ($matches as $id) {
                if (!\is_string($id)) {
                    continue;
                }
                $ids[] = $id;
                $insert->addInsert('product_stream_mapping', [
                    'product_id' => Uuid::fromHexToBytes($id),
                    'product_version_id' => $version,
                    'product_stream_id' => $binary,
                ]);
            }

            $insert->execute();
        }

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
     */
    public function updateProducts(array $ids, Context $context): void
    {
        $streams = $this->connection->fetchAllAssociative('SELECT id, api_filter FROM product_stream WHERE invalid = 0 AND api_filter IS NOT NULL');

        $insert = new MultiInsertQueryQueue($this->connection);

        $version = Uuid::fromHexToBytes(Defaults::LIVE_VERSION);

        $considerInheritance = $context->considerInheritance();
        $context->setConsiderInheritance(true);
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
                $matches = $this->repository->searchIds($criteria, $context);
            } catch (UnmappedFieldException) {
                // skip if filter field is not found
                continue;
            }

            foreach ($matches->getIds() as $id) {
                if (!\is_string($id)) {
                    continue;
                }
                $insert->addInsert('product_stream_mapping', [
                    'product_id' => Uuid::fromHexToBytes($id),
                    'product_version_id' => $version,
                    'product_stream_id' => $stream['id'],
                ]);
            }
        }
        $context->setConsiderInheritance($considerInheritance);

        RetryableTransaction::retryable($this->connection, function () use ($ids, $insert): void {
            $this->connection->executeStatement(
                'DELETE FROM product_stream_mapping WHERE product_id IN (:ids)',
                ['ids' => Uuid::fromHexToBytesList($ids)],
                ['ids' => ArrayParameterType::STRING]
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
