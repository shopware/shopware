<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExportV2\Job\Mapping;

use Shopware\Core\Content\ImportExportV2\Job\Record\ImportExportRecord;
use Shopware\Core\Content\ImportExportV2\Profile\ImportExportV2ProfileEntity;
use Shopware\Core\Content\ImportExportV2\Support\ArrayPathAccessor;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('fundamentals@after-sales')]
class ExportEntityMapper
{
    public function __construct(
        private readonly DefinitionInstanceRegistry $definitionInstanceRegistry,
        private readonly EntityPathHelper $entityPathHelper
    ) {
    }

    public function enrichCriteria(ImportExportV2ProfileEntity $profile, Criteria $criteria): void
    {
        $definition = $this->definitionInstanceRegistry->getByEntityName($profile->getEntity());
        $this->entityPathHelper->addAssociations($definition, $profile->getIdentifierPaths(), $criteria);
        $this->entityPathHelper->addAssociations($definition, $profile->getPayloadPaths(), $criteria);
    }

    public function toImportExportRecord(Entity $entity, ImportExportV2ProfileEntity $profile): ImportExportRecord
    {
        $definition = $this->definitionInstanceRegistry->getByEntityName($profile->getEntity());
        $serialized = $entity->jsonSerialize();

        $identifier = [];
        foreach ($profile->getIdentifierPaths() as $path) {
            $this->writeValue($identifier, $serialized, $definition, $path);
        }

        $payload = [];
        foreach ($profile->getPayloadPaths() as $path) {
            $this->writeValue($payload, $serialized, $definition, $path);
        }

        // Export rebuilds the same record shape that import expects so both directions stay aligned.
        return new ImportExportRecord($profile->getEntity(), $identifier, $payload);
    }

    /**
     * @param array<string, mixed> $target
     * @param array<string, mixed> $serialized
     */
    private function writeValue(array &$target, array $serialized, EntityDefinition $definition, string $path): void
    {
        if (str_contains($path, '.*.')) {
            $values = ArrayPathAccessor::getList($serialized, $path);
            if ($values !== []) {
                ArrayPathAccessor::setList($target, $path, $values);
            }

            return;
        }

        $value = $this->readValue($serialized, $definition, $path);
        if ($value === null) {
            return;
        }

        ArrayPathAccessor::set($target, $path, $value);
    }

    /**
     * @param array<string, mixed> $serialized
     */
    private function readValue(array $serialized, EntityDefinition $definition, string $path): mixed
    {
        if (str_starts_with($path, 'translations.')) {
            $segments = explode('.', $path);
            $fieldName = $segments[2] ?? null;

            // Shopware serializes translated values separately; export folds them back into the profile path.
            return \is_string($fieldName) ? ($serialized['translated'][$fieldName] ?? $serialized[$fieldName] ?? null) : null;
        }

        $segments = explode('.', $path);
        $topLevel = $segments[0] ?? '';
        $field = $definition->getField($topLevel);
        if ($field instanceof ManyToOneAssociationField && \count($segments) === 2 && $segments[1] === 'id') {
            $fkField = $definition->getFields()->getByStorageName($field->getStorageName());
            if ($fkField instanceof FkField) {
                // For nested many-to-one ids we prefer the stored foreign key, because the association
                // itself may not have been fully loaded or may serialize differently across entities.
                return $serialized[$fkField->getPropertyName()] ?? ArrayPathAccessor::get($serialized, $path);
            }
        }

        return ArrayPathAccessor::get($serialized, $path);
    }
}
