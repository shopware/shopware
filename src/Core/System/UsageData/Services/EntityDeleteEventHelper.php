<?php declare(strict_types=1);

namespace Shopware\Core\System\UsageData\Services;

use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityDeleteEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StorageAware;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\WriteCommand;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('data-services')]
class EntityDeleteEventHelper
{
    /**
     * @var EntityDefinition[]
     */
    private array $includedEntityDefinitions;

    /**
     * @var string[]
     */
    private array $excludedFields;

    /**
     * @var array<string, list<array<string, string>>>
     */
    private array $eventEntityIds;

    public function __construct(private readonly EntityDeleteEvent $event)
    {
        $this->includedEntityDefinitions = [];
        $this->excludedFields = [];
    }

    /**
     * @param iterable<EntityDefinition> $entityDefinitions
     */
    public function forEntityDefinitions(iterable $entityDefinitions): self
    {
        foreach ($entityDefinitions as $entityDefinition) {
            $this->includedEntityDefinitions[$entityDefinition->getEntityName()] = $entityDefinition;
        }

        return $this;
    }

    /**
     * @param string[] $excludeFields
     */
    public function withExcludedFields(array $excludeFields): self
    {
        $this->excludedFields = $excludeFields;

        return $this;
    }

    public function prepare(): self
    {
        $this->eventEntityIds = [];

        foreach ($this->event->getCommands() as $entityWriteResult) {
            if (!\array_key_exists($entityWriteResult->getEntityName(), $this->includedEntityDefinitions)) {
                continue;
            }

            $definition = $this->includedEntityDefinitions[$entityWriteResult->getEntityName()];

            $this->eventEntityIds[$entityWriteResult->getEntityName()][] = $this->getCommandPrimaryKeys(
                $entityWriteResult,
                $definition->getPrimaryKeys(),
            );
        }

        return $this;
    }

    /**
     * @return array<string, list<array<string, string>>>
     */
    public function getEntityIds(): array
    {
        return $this->eventEntityIds;
    }

    /**
     * @return array<string, string>
     */
    private function getCommandPrimaryKeys(WriteCommand $command, FieldCollection $entityPkFields): array
    {
        $pks = [];

        $filteredFields = $entityPkFields->filter(
            function (Field $field) {
                foreach ($this->excludedFields as $excludedField) {
                    if ($field instanceof $excludedField) {
                        return false;
                    }
                }

                return true;
            }
        );

        $primaryKey = $command->getPrimaryKey();

        foreach ($filteredFields as $field) {
            if (!$field instanceof StorageAware) {
                continue;
            }

            $pks[$field->getPropertyName()] = Uuid::fromBytesToHex($primaryKey[$field->getStorageName()]);
        }

        return $pks;
    }
}
