<?php declare(strict_types=1);

namespace Shopware\Docs\Command\App;

use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Shopware\Core\Framework\Event\BusinessEventDefinition;
use Shopware\Core\Framework\Event\EventData\EntityCollectionType;
use Shopware\Core\Framework\Event\EventData\EntityType;

class HookableEventDoc
{
    private const WRITE_EVENT_DESCRIPTION_TEMPLATE = 'Triggers when a %s is %s';

    private string $eventName;

    private ?string $description;

    private string $permissions;

    private ?string $payload;

    public function __construct(string $eventName, ?string $description, string $permissions, ?string $payload)
    {
        $this->eventName = $eventName;
        $this->description = $description;
        $this->permissions = $permissions;
        $this->payload = $payload;
    }

    public function getEventName(): string
    {
        return $this->eventName;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function getPermissions(): string
    {
        return $this->permissions;
    }

    public function getPayload(): ?string
    {
        return $this->payload;
    }

    public static function fromEntityWrittenEvent(string $event, array $permissions): self
    {
        $eventInfo = explode('.', $event);

        try {
            return new self(
                $event,
                sprintf(
                    self::WRITE_EVENT_DESCRIPTION_TEMPLATE,
                    $eventInfo[0],
                    $eventInfo[1]
                ),
                $permissions ? '`' . implode('` `', $permissions) . '`' : '-',
                json_encode(HookableEventDoc::parsingSimpleEntityWrittenEvent($eventInfo[0], $eventInfo[1]), \JSON_THROW_ON_ERROR)
            );
        } catch (\JsonException $e) {
            throw new \RuntimeException('Can not parsing payload for written event');
        }
    }

    public static function fromBusinessEvent(BusinessEventDefinition $event, array $permissions, string $description): self
    {
        try {
            return new self(
                $event->getName(),
                $description,
                $permissions ? '`' . implode('` `', $permissions) . '`' : '-',
                json_encode(HookableEventDoc::parsingSimpleBusinessEventPayload($event->getData()), \JSON_THROW_ON_ERROR)
            );
        } catch (\JsonException $e) {
            throw new \RuntimeException('Can not parsing payload for business event');
        }
    }

    private static function parsingSimpleBusinessEventPayload(array $dataTypes): array
    {
        $data = [];
        foreach ($dataTypes as $name => $dataType) {
            if ($dataType['type'] === EntityType::TYPE || $dataType['type'] === EntityCollectionType::TYPE) {
                /** @var EntityDefinition $definition */
                $definition = new $dataType['entityClass']();
                $data[EntityType::TYPE] = $definition->getEntityName();

                continue;
            }

            $data[$name] = $dataType['type'];
        }

        return $data;
    }

    private static function parsingSimpleEntityWrittenEvent(string $entity, string $operation): array
    {
        return [
            'entity' => $entity,
            'operation' => $operation === 'written' ?: EntityWriteResult::OPERATION_UPDATE . ' ' . EntityWriteResult::OPERATION_INSERT,
            'primaryKey' => 'array string',
            'payload' => 'array',
        ];
    }
}
