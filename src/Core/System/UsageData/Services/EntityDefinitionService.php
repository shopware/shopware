<?php declare(strict_types=1);

namespace Shopware\Core\System\UsageData\Services;

use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToManyIdField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('data-services')]
class EntityDefinitionService
{
    /**
     * @var array<string, EntityDefinition>
     */
    private array $definitionsByName = [];

    /**
     * @param iterable<EntityDefinition> $entityDefinitions
     */
    public function __construct(
        iterable $entityDefinitions,
        private readonly UsageDataAllowListService $usageDataAllowListService,
    ) {
        foreach ($entityDefinitions as $entityDefinition) {
            $this->addEntityDefinition($entityDefinition);
        }
    }

    public function getAllowedEntityDefinition(string $entityName): ?EntityDefinition
    {
        return $this->definitionsByName[$entityName] ?? null;
    }

    /**
     * @return EntityDefinition[]
     */
    public function getAllowedEntityDefinitions(): array
    {
        return array_values($this->definitionsByName);
    }

    /**
     * @return list<array{associationField: ManyToManyAssociationField, idField: ManyToManyIdField|null}>
     */
    public function getManyToManyAssociationIdFields(FieldCollection $fields): array
    {
        $assocFields = [];
        $idFieldsByAssocFieldName = [];

        foreach ($fields as $field) {
            if ($field instanceof ManyToManyAssociationField) {
                $assocFields[] = $field;
            }

            if ($field instanceof ManyToManyIdField) {
                $idFieldsByAssocFieldName[$field->getAssociationName()] = $field;
            }
        }

        $associations = [];

        foreach ($assocFields as $assocField) {
            $idField = $this->findManyToManyIdField($idFieldsByAssocFieldName, $assocField);
            $associations[] = ['associationField' => $assocField, 'idField' => $idField];
        }

        return $associations;
    }

    public function addEntityDefinition(EntityDefinition $entityDefinition): void
    {
        if (!$entityDefinition->hasCreatedAndUpdatedAtFields()) {
            return;
        }

        if (!$this->usageDataAllowListService->isEntityAllowed($entityDefinition->getEntityName())) {
            return;
        }

        $this->definitionsByName[$entityDefinition->getEntityName()] = $entityDefinition;
    }

    /**
     * @param array<string, ManyToManyIdField> $idFieldsByAssocFieldName
     */
    private function findManyToManyIdField(
        array $idFieldsByAssocFieldName,
        ManyToManyAssociationField $associationField,
    ): ?ManyToManyIdField {
        return $idFieldsByAssocFieldName[$associationField->getPropertyName()] ?? null;
    }
}
