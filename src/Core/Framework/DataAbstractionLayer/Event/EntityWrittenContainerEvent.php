<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Event;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Shopware\Core\Framework\Event\NestedEvent;
use Shopware\Core\Framework\Event\NestedEventCollection;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;

/**
 * @template IDStructure of string|array<string, string> = string
 */
#[Package('framework')]
class EntityWrittenContainerEvent extends NestedEvent
{
    protected bool $cloned = false;

    /**
     * @param NestedEventCollection<EntityWrittenEvent<IDStructure>> $events
     * @param array<mixed> $errors
     */
    public function __construct(
        protected Context $context,
        private readonly NestedEventCollection $events,
        private readonly array $errors
    ) {
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    /**
     * @deprecated tag:v6.8.0 - reason:return-type-change - Will only return NestedEventCollection
     *
     * @return NestedEventCollection<EntityWrittenEvent<IDStructure>>|null
     */
    public function getEvents(): ?NestedEventCollection
    {
        return $this->events;
    }

    /**
     * @return EntityWrittenEvent<IDStructure>|null
     */
    public function getEventByEntityName(string $entityName): ?EntityWrittenEvent
    {
        foreach ($this->events as $event) {
            if (!$event instanceof EntityWrittenEvent) {
                continue;
            }

            if ($event->getEntityName() === $entityName) {
                return $event;
            }
        }

        return null;
    }

    /**
     * @param array<string, list<EntityWriteResult>> $identifiers
     * @param array<mixed> $errors
     */
    public static function createWithWrittenEvents(array $identifiers, Context $context, array $errors, bool $cloned = false): self
    {
        $event = self::createEvents($identifiers, $context, $errors, EntityWrittenEvent::class);

        $event->setCloned($cloned);

        return $event;
    }

    /**
     * @param array<string, list<EntityWriteResult>> $identifiers
     * @param array<mixed> $errors
     */
    public static function createWithDeletedEvents(array $identifiers, Context $context, array $errors): self
    {
        return self::createEvents($identifiers, $context, $errors, EntityDeletedEvent::class);
    }

    /**
     * @internal used for debugging purposes only
     *
     * @return array<string, list<IDStructure>>
     */
    public function getList(): array
    {
        $list = [];

        foreach ($this->events as $event) {
            if ($event instanceof EntityWrittenEvent) {
                $list[$event->getName()] = $event->getIds();
            }
        }

        return $list;
    }

    /**
     * @param EntityWrittenEvent<IDStructure> ...$events
     */
    public function addEvent(NestedEvent ...$events): void
    {
        foreach ($events as $event) {
            $this->events->add($event);
        }
    }

    /**
     * @return array<mixed>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * @return list<IDStructure>
     */
    public function getPrimaryKeys(string $entity): array
    {
        return $this->findPrimaryKeys($entity);
    }

    /**
     * @return list<IDStructure>
     */
    public function getDeletedPrimaryKeys(string $entity): array
    {
        return $this->findPrimaryKeys($entity, fn (EntityWriteResult $result) => $result->getOperation() === EntityWriteResult::OPERATION_DELETE);
    }

    /**
     * @deprecated tag:v6.8.0 - Will be removed with the next major as it is unused
     *
     * @return list<IDStructure>
     */
    public function getPrimaryKeysWithPayload(string $entity): array
    {
        Feature::triggerDeprecationOrThrow(
            'v6.8.0.0',
            Feature::deprecatedMethodMessage(__CLASS__, __METHOD__, 'v6.8.0.0'),
        );

        return $this->findPrimaryKeys($entity, function (EntityWriteResult $result) {
            if ($result->getOperation() === EntityWriteResult::OPERATION_DELETE) {
                return true;
            }

            return !empty($result->getPayload());
        });
    }

    /**
     * @param list<string> $ignoredFields
     *
     * @return list<IDStructure>
     */
    public function getPrimaryKeysWithPayloadIgnoringFields(string $entity, array $ignoredFields): array
    {
        return $this->findPrimaryKeys($entity, function (EntityWriteResult $result) use ($ignoredFields) {
            if ($result->getOperation() === EntityWriteResult::OPERATION_DELETE) {
                return true;
            }

            return !empty(array_diff(array_keys($result->getPayload()), $ignoredFields));
        });
    }

    /**
     * @param list<string> $properties
     *
     * @return list<IDStructure>
     */
    public function getPrimaryKeysWithPropertyChange(string $entity, array $properties): array
    {
        return $this->findPrimaryKeys($entity, function (EntityWriteResult $result) use ($properties) {
            $payload = $result->getPayload();

            foreach ($properties as $property) {
                if (\array_key_exists($property, $payload)) {
                    return true;
                }
            }

            return false;
        });
    }

    public function isCloned(): bool
    {
        return $this->cloned;
    }

    public function setCloned(bool $cloned): void
    {
        $this->cloned = $cloned;
    }

    /**
     * @param array<string, list<EntityWriteResult>> $identifiers
     * @param array<mixed> $errors
     */
    private static function createEvents(array $identifiers, Context $context, array $errors, string $event): self
    {
        $events = new NestedEventCollection();

        foreach ($identifiers as $data) {
            if (\count($data) === 0) {
                continue;
            }

            $first = current($data);

            $instance = new $event($first->getEntityName(), $data, $context, $errors);

            $events->add($instance);
        }

        return new self($context, $events, $errors);
    }

    /**
     * @return list<IDStructure>
     */
    private function findPrimaryKeys(string $entity, ?\Closure $closure = null): array
    {
        $ids = [];

        foreach ($this->events as $event) {
            if (!$event instanceof EntityWrittenEvent) {
                continue;
            }

            if ($event->getEntityName() !== $entity) {
                continue;
            }

            if (!$closure) {
                $ids = array_merge($ids, $event->getIds());

                continue;
            }

            foreach ($event->getWriteResults() as $result) {
                if ($closure($result)) {
                    $ids[] = $result->getPrimaryKey();
                }
            }
        }

        return $ids;
    }
}
