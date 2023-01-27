<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Event;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Shopware\Core\Framework\Event\NestedEvent;
use Shopware\Core\Framework\Event\NestedEventCollection;
use Shopware\Core\Framework\Log\Package;

#[Package('core')]
class EntityWrittenContainerEvent extends NestedEvent
{
    /**
     * @var Context
     */
    protected $context;

    protected bool $cloned = false;

    public function __construct(
        Context $context,
        private readonly NestedEventCollection $events,
        private readonly array $errors
    ) {
        $this->context = $context;
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    public function getEvents(): ?NestedEventCollection
    {
        return $this->events;
    }

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

    public static function createWithWrittenEvents(array $identifiers, Context $context, array $errors, bool $cloned = false): self
    {
        $event = self::createEvents($identifiers, $context, $errors, EntityWrittenEvent::class);

        $event->setCloned($cloned);

        return $event;
    }

    public static function createWithDeletedEvents(array $identifiers, Context $context, array $errors): self
    {
        return self::createEvents($identifiers, $context, $errors, EntityDeletedEvent::class);
    }

    /**
     * @internal used for debugging purposes only
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

    public function addEvent(NestedEvent ...$events): void
    {
        foreach ($events as $event) {
            $this->events->add($event);
        }
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    public function getPrimaryKeys(string $entity): array
    {
        return $this->findPrimaryKeys($entity);
    }

    public function getDeletedPrimaryKeys(string $entity): array
    {
        return $this->findPrimaryKeys($entity, fn (EntityWriteResult $result) => $result->getOperation() === EntityWriteResult::OPERATION_DELETE);
    }

    public function getPrimaryKeysWithPayload(string $entity): array
    {
        return $this->findPrimaryKeys($entity, function (EntityWriteResult $result) {
            if ($result->getOperation() === EntityWriteResult::OPERATION_DELETE) {
                return true;
            }

            return !empty($result->getPayload());
        });
    }

    public function getPrimaryKeysWithPayloadIgnoringFields(string $entity, array $ignoredFields): array
    {
        return $this->findPrimaryKeys($entity, function (EntityWriteResult $result) use ($ignoredFields) {
            if ($result->getOperation() === EntityWriteResult::OPERATION_DELETE) {
                return true;
            }

            return !empty(array_diff(array_keys($result->getPayload()), $ignoredFields));
        });
    }

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

    private static function createEvents(array $identifiers, Context $context, array $errors, string $event): self
    {
        $events = new NestedEventCollection();

        /** @var EntityWriteResult[] $data */
        foreach ($identifiers as $data) {
            if (\count($data) === 0) {
                continue;
            }

            $first = current($data);

            /** @var NestedEvent $instance */
            $instance = new $event($first->getEntityName(), $data, $context, $errors);

            $events->add($instance);
        }

        return new self($context, $events, $errors);
    }

    private function findPrimaryKeys(string $entity, ?\Closure $closure = null): array
    {
        $ids = [];

        /** @var EntityWrittenEvent $event */
        foreach ($this->events as $event) {
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
