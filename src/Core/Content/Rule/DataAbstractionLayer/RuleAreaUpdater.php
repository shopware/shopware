<?php declare(strict_types=1);

namespace Shopware\Core\Content\Rule\DataAbstractionLayer;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Shopware\Core\Checkout\Cart\CachedRuleLoader;
use Shopware\Core\Content\Rule\RuleDefinition;
use Shopware\Core\Framework\Adapter\Cache\CacheInvalidator;
use Shopware\Core\Framework\DataAbstractionLayer\CompiledFieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityDefinitionQueryHelper;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\QueryBuilder;
use Shopware\Core\Framework\DataAbstractionLayer\Doctrine\FetchModeHelper;
use Shopware\Core\Framework\DataAbstractionLayer\Doctrine\RetryableQuery;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Field\AssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\RuleAreas;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\ChangeSetAware;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\DeleteCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\PreWriteValidationEvent;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Rule\Collector\RuleConditionRegistry;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 */
#[Package('business-ops')]
class RuleAreaUpdater implements EventSubscriberInterface
{
    /**
     * @internal
     */
    public function __construct(
        private readonly Connection $connection,
        private readonly RuleDefinition $definition,
        private readonly RuleConditionRegistry $conditionRegistry,
        private readonly CacheInvalidator $cacheInvalidator
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            PreWriteValidationEvent::class => 'triggerChangeSet',
            EntityWrittenContainerEvent::class => 'onEntityWritten',
        ];
    }

    public function triggerChangeSet(PreWriteValidationEvent $event): void
    {
        $associatedEntities = $this->getAssociationEntities();

        foreach ($event->getCommands() as $command) {
            $definition = $command->getDefinition();
            $entity = $definition->getEntityName();

            if (!$command instanceof ChangeSetAware || !\in_array($entity, $associatedEntities, true)) {
                continue;
            }

            if ($command instanceof DeleteCommand) {
                $command->requestChangeSet();

                continue;
            }

            foreach ($this->getForeignKeyFields($definition) as $field) {
                if ($command->hasField($field->getStorageName())) {
                    $command->requestChangeSet();
                }
            }
        }
    }

    public function onEntityWritten(EntityWrittenContainerEvent $event): void
    {
        $associationFields = $this->getAssociationFields();
        $ruleIds = [];

        foreach ($event->getEvents() ?? [] as $nestedEvent) {
            if (!$nestedEvent instanceof EntityWrittenEvent) {
                continue;
            }

            $definition = $this->getAssociationDefinitionByEntity($associationFields, $nestedEvent->getEntityName());

            if (!$definition) {
                continue;
            }

            $ruleIds = $this->hydrateRuleIds($this->getForeignKeyFields($definition), $nestedEvent, $ruleIds);
        }

        if (empty($ruleIds)) {
            return;
        }

        $this->update(Uuid::fromBytesToHexList(array_unique(array_filter($ruleIds))));

        $this->cacheInvalidator->invalidate([CachedRuleLoader::CACHE_KEY]);
    }

    /**
     * @param list<string> $ids
     */
    public function update(array $ids): void
    {
        $associationFields = $this->getAssociationFields();

        $areas = $this->getAreas($ids, $associationFields);

        $update = new RetryableQuery(
            $this->connection,
            $this->connection->prepare('UPDATE `rule` SET `areas` = :areas WHERE `id` = :id')
        );

        /** @var array<string, string[]> $associations */
        foreach ($areas as $id => $associations) {
            $areas = [];

            foreach ($associations as $propertyName => $match) {
                if ((bool) $match === false) {
                    continue;
                }

                if ($propertyName === 'flowCondition') {
                    $areas = array_unique(array_merge($areas, [RuleAreas::FLOW_CONDITION_AREA]));

                    continue;
                }

                $field = $associationFields->get($propertyName);

                if (!$field || !$flag = $field->getFlag(RuleAreas::class)) {
                    continue;
                }

                $areas = array_unique(array_merge($areas, $flag instanceof RuleAreas ? $flag->getAreas() : []));
            }

            $update->execute([
                'areas' => json_encode(array_values($areas), \JSON_THROW_ON_ERROR),
                'id' => Uuid::fromHexToBytes($id),
            ]);
        }
    }

    /**
     * @param FkField[] $fields
     * @param string[] $ruleIds
     *
     * @return string[]
     */
    private function hydrateRuleIds(array $fields, EntityWrittenEvent $nestedEvent, array $ruleIds): array
    {
        foreach ($nestedEvent->getWriteResults() as $result) {
            $changeSet = $result->getChangeSet();
            $payload = $result->getPayload();

            foreach ($fields as $field) {
                if ($changeSet && $changeSet->hasChanged($field->getStorageName())) {
                    $ruleIds[] = $changeSet->getBefore($field->getStorageName());
                    $ruleIds[] = $changeSet->getAfter($field->getStorageName());
                }

                if ($changeSet) {
                    continue;
                }

                if (!empty($payload[$field->getPropertyName()])) {
                    $ruleIds[] = Uuid::fromHexToBytes($payload[$field->getPropertyName()]);
                }
            }
        }

        return $ruleIds;
    }

    /**
     * @param list<string> $ids
     *
     * @return array<string, string[][]>
     */
    private function getAreas(array $ids, CompiledFieldCollection $associationFields): array
    {
        $query = new QueryBuilder($this->connection);
        $query->select('LOWER(HEX(`rule`.`id`)) AS array_key')
            ->from('rule')
            ->andWhere('`rule`.`id` IN (:ids)');

        /** @var AssociationField $associationField */
        foreach ($associationFields->getElements() as $associationField) {
            $this->addSelect($query, $associationField);
        }
        $this->addFlowConditionSelect($query);

        $query->setParameter(
            'ids',
            Uuid::fromHexToBytesList($ids),
            ArrayParameterType::STRING
        )->setParameter(
            'flowTypes',
            $this->conditionRegistry->getFlowRuleNames(),
            ArrayParameterType::STRING
        );

        return FetchModeHelper::groupUnique($query->executeQuery()->fetchAllAssociative());
    }

    private function addSelect(QueryBuilder $query, AssociationField $associationField): void
    {
        $template = 'EXISTS(%s) AS %s';
        $propertyName = $associationField->getPropertyName();

        if ($associationField instanceof OneToOneAssociationField || $associationField instanceof ManyToOneAssociationField) {
            $template = 'IF(%s.%s IS NOT NULL, 1, 0) AS %s';
            $query->addSelect(sprintf($template, '`rule`', $this->escape($associationField->getStorageName()), $propertyName));

            return;
        }

        if ($associationField instanceof ManyToManyAssociationField) {
            $mappingTable = $this->escape($associationField->getMappingDefinition()->getEntityName());
            $mappingLocalColumn = $this->escape($associationField->getMappingLocalColumn());
            $localColumn = $this->escape($associationField->getLocalField());

            $subQuery = (new QueryBuilder($this->connection))
                ->select('1')
                ->from($mappingTable)
                ->andWhere(sprintf('%s = `rule`.%s', $mappingLocalColumn, $localColumn));

            $query->addSelect(sprintf($template, $subQuery->getSQL(), $propertyName));

            return;
        }

        if ($associationField instanceof OneToManyAssociationField) {
            $referenceTable = $this->escape($associationField->getReferenceDefinition()->getEntityName());
            $referenceColumn = $this->escape($associationField->getReferenceField());
            $localColumn = $this->escape($associationField->getLocalField());

            $subQuery = (new QueryBuilder($this->connection))
                ->select('1')
                ->from($referenceTable)
                ->andWhere(sprintf('%s = `rule`.%s', $referenceColumn, $localColumn));

            $query->addSelect(sprintf($template, $subQuery->getSQL(), $propertyName));
        }
    }

    private function addFlowConditionSelect(QueryBuilder $query): void
    {
        $subQuery = (new QueryBuilder($this->connection))
            ->select('1')
            ->from('rule_condition')
            ->andWhere('`rule_id` = `rule`.`id`')
            ->andWhere('`type` IN (:flowTypes)');

        $query->addSelect(sprintf('EXISTS(%s) AS flowCondition', $subQuery->getSQL()));
    }

    private function escape(string $string): string
    {
        return EntityDefinitionQueryHelper::escape($string);
    }

    private function getAssociationFields(): CompiledFieldCollection
    {
        return $this->definition
            ->getFields()
            ->filterByFlag(RuleAreas::class);
    }

    /**
     * @return FkField[]
     */
    private function getForeignKeyFields(EntityDefinition $definition): array
    {
        /** @var FkField[] $fields */
        $fields = $definition->getFields()->filterInstance(FkField::class)->filter(fn (FkField $fk): bool => $fk->getReferenceDefinition()->getEntityName() === $this->definition->getEntityName())->getElements();

        return $fields;
    }

    /**
     * @return string[]
     */
    private function getAssociationEntities(): array
    {
        return $this->getAssociationFields()->filter(fn (AssociationField $associationField): bool => $associationField instanceof OneToManyAssociationField)->map(fn (AssociationField $field): string => $field->getReferenceDefinition()->getEntityName());
    }

    private function getAssociationDefinitionByEntity(CompiledFieldCollection $collection, string $entityName): ?EntityDefinition
    {
        /** @var AssociationField|null $field */
        $field = $collection->filter(function (AssociationField $associationField) use ($entityName): bool {
            if (!$associationField instanceof OneToManyAssociationField) {
                return false;
            }

            return $associationField->getReferenceDefinition()->getEntityName() === $entityName;
        })->first();

        return $field ? $field->getReferenceDefinition() : null;
    }
}
