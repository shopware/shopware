<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\DataAbstractionLayer;

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
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\Messenger\MessageBusInterface;

class ProductStreamUpdater extends EntityIndexer
{
    private Connection $connection;

    private ProductDefinition $productDefinition;

    private EntityRepository $repository;

    private MessageBusInterface $messageBus;

    private ManyToManyIdFieldUpdater $manyToManyIdFieldUpdater;

    /**
     * @internal
     */
    public function __construct(
        Connection $connection,
        ProductDefinition $productDefinition,
        EntityRepository $repository,
        MessageBusInterface $messageBus,
        ManyToManyIdFieldUpdater $manyToManyIdFieldUpdater
    ) {
        $this->connection = $connection;
        $this->productDefinition = $productDefinition;
        $this->repository = $repository;
        $this->messageBus = $messageBus;
        $this->manyToManyIdFieldUpdater = $manyToManyIdFieldUpdater;
    }

    public function getName(): string
    {
        return 'product_stream_mapping.indexer';
    }

    /**
     * @param array|null $offset
     *
     * @deprecated tag:v6.5.0 The parameter $offset will be native typed
     */
    public function iterate(/*?array */$offset): ?EntityIndexingMessage
    {
        if ($offset !== null && !\is_array($offset)) {
            Feature::triggerDeprecationOrThrow(
                'v6.5.0.0',
                'Parameter `$offset` of method "iterate()" in class "ProductStreamUpdater" will be natively typed to `?array` in v6.5.0.0.'
            );
        }

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

        $insert = new MultiInsertQueryQueue($this->connection, 250, false, true);

        $version = Uuid::fromHexToBytes(Defaults::LIVE_VERSION);

        $filter = json_decode((string) $filter, true);

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

        RetryableTransaction::retryable($this->connection, function () use ($binary): void {
            $this->connection->executeStatement(
                'DELETE FROM product_stream_mapping WHERE product_stream_id = :id',
                ['id' => $binary],
            );
        });

        $ids = [];
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

    public function updateProducts(array $ids, Context $context): void
    {
        $streams = $this->connection->fetchAllAssociative('SELECT id, api_filter FROM product_stream WHERE invalid = 0 AND api_filter IS NOT NULL');

        $insert = new MultiInsertQueryQueue($this->connection);

        $version = Uuid::fromHexToBytes(Defaults::LIVE_VERSION);

        foreach ($streams as $stream) {
            $filter = json_decode((string) $stream['api_filter'], true);
            if (empty($filter)) {
                continue;
            }

            $criteria = $this->getCriteria($filter, $ids);

            if ($criteria === null) {
                continue;
            }

            try {
                $matches = $this->repository->searchIds($criteria, $context);
            } catch (UnmappedFieldException $e) {
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

        RetryableTransaction::retryable($this->connection, function () use ($ids, $insert): void {
            $this->connection->executeStatement(
                'DELETE FROM product_stream_mapping WHERE product_id IN (:ids)',
                ['ids' => Uuid::fromHexToBytesList($ids)],
                ['ids' => Connection::PARAM_STR_ARRAY]
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

    private function getCriteria(array $filters, ?array $ids = null): ?Criteria
    {
        $exception = new SearchRequestException();

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
}
