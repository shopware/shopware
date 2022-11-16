<?php declare(strict_types=1);

namespace Shopware\Core\Content\ProductStream\DataAbstractionLayer;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\ProductStream\Event\ProductStreamIndexerEvent;
use Shopware\Core\Content\ProductStream\ProductStreamDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\IteratorFactory;
use Shopware\Core\Framework\DataAbstractionLayer\Doctrine\FetchModeHelper;
use Shopware\Core\Framework\DataAbstractionLayer\Doctrine\RetryableQuery;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InvalidFilterQueryException;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\SearchRequestException;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexer;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexingMessage;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Parser\QueryStringParser;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[Package('business-ops')]
class ProductStreamIndexer extends EntityIndexer
{
    /**
     * @internal
     */
    public function __construct(
        private readonly Connection $connection,
        private readonly IteratorFactory $iteratorFactory,
        private readonly EntityRepository $repository,
        private readonly SerializerInterface $serializer,
        private readonly ProductDefinition $productDefinition,
        private readonly EventDispatcherInterface $eventDispatcher
    ) {
    }

    public function getName(): string
    {
        return 'product_stream.indexer';
    }

    /**
     * @param array<string, mixed>|null $offset
     */
    public function iterate(?array $offset): ?EntityIndexingMessage
    {
        $iterator = $this->iteratorFactory->createIterator($this->repository->getDefinition(), $offset);

        $ids = $iterator->fetch();

        if (empty($ids)) {
            return null;
        }

        return new ProductStreamIndexingMessage(array_values($ids), $iterator->getOffset());
    }

    public function update(EntityWrittenContainerEvent $event): ?EntityIndexingMessage
    {
        $updates = $event->getPrimaryKeys(ProductStreamDefinition::ENTITY_NAME);

        if (!$updates) {
            return null;
        }

        return new ProductStreamIndexingMessage(array_values($updates), null, $event->getContext());
    }

    public function handle(EntityIndexingMessage $message): void
    {
        $ids = $message->getData();

        $ids = array_unique(array_filter($ids));
        if (empty($ids)) {
            return;
        }

        $filters = $this->connection->fetchAllAssociative(
            'SELECT
                LOWER(HEX(product_stream_id)) as array_key,
                product_stream_filter.*
             FROM product_stream_filter
             WHERE product_stream_id IN (:ids)
             ORDER BY product_stream_id',
            ['ids' => Uuid::fromHexToBytesList($ids)],
            ['ids' => ArrayParameterType::STRING]
        );

        $filters = FetchModeHelper::group($filters);

        $update = new RetryableQuery(
            $this->connection,
            $this->connection->prepare('UPDATE product_stream SET api_filter = :serialized, invalid = :invalid WHERE id = :id')
        );

        foreach ($filters as $id => $filter) {
            $invalid = false;

            $serialized = null;

            try {
                $serialized = $this->buildPayload($filter);
            } catch (InvalidFilterQueryException | SearchRequestException) {
                $invalid = true;
            } finally {
                $update->execute([
                    'serialized' => $serialized,
                    'invalid' => (int) $invalid,
                    'id' => Uuid::fromHexToBytes($id),
                ]);
            }
        }

        $this->eventDispatcher->dispatch(new ProductStreamIndexerEvent($ids, $message->getContext(), $message->getSkip()));
    }

    public function getTotal(): int
    {
        return $this->iteratorFactory->createIterator($this->repository->getDefinition())->fetchCount();
    }

    public function getDecorated(): EntityIndexer
    {
        throw new DecorationPatternException(static::class);
    }

    /**
     * @param array<string, array<string, mixed>> $filter
     */
    private function buildPayload(array $filter): string
    {
        usort($filter, static fn (array $a, array $b) => $a['position'] <=> $b['position']);

        $nested = $this->buildNested($filter, null);

        $searchException = new SearchRequestException();
        $streamFilter = [];

        foreach ($nested as $value) {
            $parsed = QueryStringParser::fromArray($this->productDefinition, $value, $searchException);
            $streamFilter[] = QueryStringParser::toArray($parsed);
        }

        if ($searchException->getErrors()->current()) {
            throw $searchException;
        }

        return $this->serializer->serialize($streamFilter, 'json');
    }

    /**
     * @param array<string, array<string, mixed>> $entities
     *
     * @return array<int, array<string, mixed>>
     */
    private function buildNested(array $entities, ?string $parentId): array
    {
        $nested = [];
        foreach ($entities as $entity) {
            if ($entity['parent_id'] !== $parentId) {
                continue;
            }

            $parameters = $entity['parameters'];
            if ($parameters && \is_string($parameters)) {
                $decodedParameters = json_decode((string) $entity['parameters'], true);
                if (json_last_error() === \JSON_ERROR_NONE) {
                    $entity['parameters'] = $decodedParameters;
                }
            }

            if ($this->isMultiFilter($entity['type'])) {
                $entity['queries'] = $this->buildNested($entities, $entity['id']);
            }

            if ($this->isIdFilter($entity['field'])) {
                $entity = $this->wrapIdFilter($entity);
            }

            $nested[] = $entity;
        }

        return $nested;
    }

    private function isMultiFilter(string $type): bool
    {
        return \in_array($type, ['multi', 'not'], true);
    }

    private function isIdFilter(?string $field): bool
    {
        return $field === 'id' || $field === $this->productDefinition->getEntityName() . '.id';
    }

    /**
     * @param array<string, mixed> $originalQuery
     *
     * @return array<string, mixed>
     */
    private function wrapIdFilter(array $originalQuery): array
    {
        return [
            'type' => 'multi',
            'operator' => 'OR',
            'queries' => [$originalQuery, array_merge($originalQuery, ['field' => 'parentId'])],
        ];
    }
}
