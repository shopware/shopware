<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\Docs\App;

use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Shopware\Core\Framework\Event\BusinessEventDefinition;
use Shopware\Core\Framework\Event\EventData\EntityCollectionType;
use Shopware\Core\Framework\Event\EventData\EntityType;
use Shopware\Core\Framework\Log\Package;

#[Package('core')]
class HookableEventDoc
{
    private const WRITE_EVENT_DESCRIPTION_TEMPLATE = 'Triggers when a %s is %s';

    public function __construct(
        private readonly string $eventName,
        private readonly ?string $description,
        private readonly string $permissions,
        private readonly ?string $payload
    ) {
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

    /**
     * @param list<string> $permissions
     */
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
        } catch (\JsonException) {
            throw new \RuntimeException('Can not parsing payload for written event');
        }
    }

    /**
     * @param list<string> $permissions
     */
    public static function fromBusinessEvent(BusinessEventDefinition $event, array $permissions, string $description): self
    {
        try {
            return new self(
                $event->getName(),
                $description,
                $permissions ? '`' . implode('` `', $permissions) . '`' : '-',
                json_encode(HookableEventDoc::parsingSimpleBusinessEventPayload($event->getData()), \JSON_THROW_ON_ERROR)
            );
        } catch (\JsonException) {
            throw new \RuntimeException('Can not parsing payload for business event');
        }
    }

    /**
     * @param array<string, mixed> $dataTypes
     *
     * @return array<string, string>
     */
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

    /**
     * @return array<string, string>
     */
    private static function parsingSimpleEntityWrittenEvent(string $entity, string $operation): array
    {
        return [
            'entity' => $entity,
            'operation' => $operation === 'written' ? EntityWriteResult::OPERATION_UPDATE . ' ' . EntityWriteResult::OPERATION_INSERT : $operation,
            'primaryKey' => 'array string',
            'payload' => 'array',
        ];
    }
}
