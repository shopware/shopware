<?php declare(strict_types=1);

namespace Shopware\Core\Test\Stub\DataAbstractionLayer;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityDeletedEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\AggregationResultCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;
use Shopware\Core\Framework\Event\NestedEventCollection;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\Validator\Validation;

/**
 * @final
 *
 * @template TEntityCollection of EntityCollection
 *
 * @extends EntityRepository<TEntityCollection>
 *
 * @phpstan-type ResultTypes EntitySearchResult<TEntityCollection>|AggregationResultCollection|mixed|TEntityCollection|IdSearchResult|array
 */
class StaticEntityRepository extends EntityRepository
{
    /**
     * @var array<array<mixed>>
     */
    public array $upserts = [];

    /**
     * @var array<array<mixed>>
     */
    public array $updates = [];

    /**
     * @var array<array<mixed>>
     */
    public array $creates = [];

    /**
     * @var array<array<string, mixed|null>>
     */
    public array $deletes = [];

    /**
     * @param array<callable(Criteria, Context): (ResultTypes)|ResultTypes> $searches
     */
    public function __construct(
        private array $searches,
        private readonly ?EntityDefinition $definition = null
    ) {
        if (!$definition) {
            return;
        }

        try {
            $definition->getFields();
        } catch (\Throwable $exception) {
            $registry = new StaticDefinitionInstanceRegistry(
                [$definition],
                Validation::createValidator(),
                new StaticEntityWriterGateway()
            );
            $definition->compile($registry);
        }
    }

    /**
     * @return EntitySearchResult<TEntityCollection>
     */
    public function search(Criteria $criteria, Context $context): EntitySearchResult
    {
        $result = \array_shift($this->searches);
        $callable = $result;

        if (\is_callable($callable)) {
            /** @var callable(Criteria, Context, StaticEntityRepository<TEntityCollection>): ResultTypes $callable */
            $result = $callable($criteria, $context, $this);
        }

        if ($result instanceof EntitySearchResult) {
            return $result;
        }

        if ($result instanceof EntityCollection) {
            /** @var TEntityCollection $result */
            return new EntitySearchResult($this->getDummyEntityName(), $result->count(), $result, null, $criteria, $context);
        }

        if ($result instanceof AggregationResultCollection) {
            /** @var TEntityCollection $collection */
            $collection = new EntityCollection();

            return new EntitySearchResult($this->getDummyEntityName(), 0, $collection, $result, $criteria, $context);
        }

        throw new \RuntimeException('Invalid mock repository configuration');
    }

    public function searchIds(Criteria $criteria, Context $context): IdSearchResult
    {
        $result = \array_shift($this->searches);
        $callable = $result;

        if (\is_callable($callable)) {
            /** @var callable(Criteria, Context): ResultTypes $callable */
            $result = $callable($criteria, $context);
        }

        if ($result instanceof IdSearchResult) {
            return $result;
        }

        if (!\is_array($result)) {
            throw new \RuntimeException('Invalid mock repository configuration');
        }

        // flat array of ids
        if (\array_key_exists(0, $result) && \is_string($result[0])) {
            $result = \array_map(fn (string $id) => ['primaryKey' => $id, 'data' => []], $result);
        }

        return new IdSearchResult(\count($result), $result, $criteria, $context);
    }

    public function create(array $data, Context $context): EntityWrittenContainerEvent
    {
        $writeResults = $this->getDummyWriteResults($data, EntityWriteResult::OPERATION_INSERT, $context);
        /** @var EntityWrittenEvent $entityWrittenEvent */
        $entityWrittenEvent = $writeResults->first();

        $this->creates[] = $entityWrittenEvent->getPayloads();

        return new EntityWrittenContainerEvent($context, $writeResults, []);
    }

    public function update(array $data, Context $context): EntityWrittenContainerEvent
    {
        $this->updates[] = $data;

        return new EntityWrittenContainerEvent(
            $context,
            $this->getDummyWriteResults($data, EntityWriteResult::OPERATION_UPDATE, $context),
            []
        );
    }

    public function upsert(array $data, Context $context): EntityWrittenContainerEvent
    {
        $writeResults = $this->getDummyWriteResults($data, EntityWriteResult::OPERATION_INSERT, $context);
        /** @var EntityWrittenEvent $entityWrittenEvent */
        $entityWrittenEvent = $writeResults->first();

        $this->upserts[] = $entityWrittenEvent->getPayloads();

        return new EntityWrittenContainerEvent($context, $writeResults, []);
    }

    public function delete(array $ids, Context $context): EntityWrittenContainerEvent
    {
        $this->deletes[] = $ids;

        return new EntityWrittenContainerEvent(
            $context,
            $this->getDummyWriteResults($ids, EntityWriteResult::OPERATION_DELETE, $context),
            []
        );
    }

    public function getDefinition(): EntityDefinition
    {
        if ($this->definition === null) {
            throw new \RuntimeException('No definition set');
        }

        return $this->definition;
    }

    /**
     * @param mixed[][] $data
     */
    private function getDummyWriteResults(array $data, string $operation, Context $context): NestedEventCollection
    {
        $writeResults = [];

        foreach ($data as $payload) {
            $primaryKeys = $this->getDummyPrimaryKeys($payload);
            $payload = array_merge($primaryKeys, $payload);
            $primaryKey = \count($primaryKeys) === 1 ? current($primaryKeys) : $primaryKeys;

            $writeResults[] = new EntityWriteResult(
                empty($primaryKey) ? Uuid::randomHex() : $primaryKey,
                $payload,
                $this->getDummyEntityName(),
                $operation
            );
        }

        if ($operation === EntityWriteResult::OPERATION_DELETE) {
            $event = new EntityDeletedEvent($this->getDummyEntityName(), $writeResults, $context);
        } else {
            $event = new EntityWrittenEvent($this->getDummyEntityName(), $writeResults, $context);
        }

        return new NestedEventCollection([$event]);
    }

    /**
     * @param mixed[] $payload
     *
     * @return mixed[]
     */
    private function getDummyPrimaryKeys(array $payload): array
    {
        if ($this->definition === null) {
            return [];
        }

        $primaryKeys = [];

        /** @var Field $field */
        foreach ($this->definition->getPrimaryKeys() as $field) {
            $primaryKeys[$field->getPropertyName()] = $payload[$field->getPropertyName()] ?? Uuid::randomHex();
        }

        return $primaryKeys;
    }

    private function getDummyEntityName(): string
    {
        if (!$this->definition) {
            return 'mock';
        }

        return $this->definition->getEntityName();
    }
}
