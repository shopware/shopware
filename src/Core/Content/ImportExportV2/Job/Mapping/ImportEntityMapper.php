<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExportV2\Job\Mapping;

use Shopware\Core\Content\ImportExportV2\ImportExportV2Exception;
use Shopware\Core\Content\ImportExportV2\Job\Record\ImportExportRecord;
use Shopware\Core\Content\ImportExportV2\Profile\ImportExportV2ProfileEntity;
use Shopware\Core\Content\ImportExportV2\Support\ArrayPathAccessor;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslatedField;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('fundamentals@after-sales')]
class ImportEntityMapper
{
    public function __construct(
        private readonly DefinitionInstanceRegistry $definitionInstanceRegistry,
        private readonly EntityPathHelper $entityPathHelper
    ) {
    }

    public function validateRecord(ImportExportRecord $record, ImportExportV2ProfileEntity $profile, Context $context, int $recordIndex): void
    {
        $definition = $this->getDefinition($profile->getEntity());
        $this->entityPathHelper->assertPathsExist($definition, $profile->getIdentifierPaths(), $recordIndex);
        $this->entityPathHelper->assertPathsExist($definition, $profile->getPayloadPaths(), $recordIndex);

        // For the spike we only update existing root entities, so the configured identifier paths
        // must resolve to one concrete entity id before we build the write payload.
        $this->resolveRootEntityId($record, $profile, $context, $recordIndex);
        $this->validateReferenceValues($definition, $record, $context);
    }

    /**
     * @return array<string, mixed>
     */
    public function buildWritePayload(ImportExportRecord $record, ImportExportV2ProfileEntity $profile, Context $context): array
    {
        $definition = $this->getDefinition($profile->getEntity());
        $payload = ['id' => $this->resolveRootEntityId($record, $profile, $context, 0)];

        foreach ($record->getPayload() as $property => $value) {
            $field = $definition->getField((string) $property);
            if ($field instanceof ManyToOneAssociationField) {
                // Many-to-one input arrives as a nested object in the shared record shape,
                // but DAL usually expects the foreign key field on write.
                $this->mapManyToOneAssociation($payload, $definition, $field, $value);

                continue;
            }

            if ($property === 'translations' && \is_array($value)) {
                // DEFAULT translations are flattened back to the root translated field values
                // because that is how Shopware DAL expects them on write.
                $this->mapTranslations($payload, $definition, $value);

                continue;
            }

            $payload[(string) $property] = $value;
        }

        return $payload;
    }

    private function getDefinition(string $entityName): EntityDefinition
    {
        return $this->definitionInstanceRegistry->getByEntityName($entityName);
    }

    private function resolveRootEntityId(
        ImportExportRecord $record,
        ImportExportV2ProfileEntity $profile,
        Context $context,
        int $recordIndex
    ): string {
        $criteria = new Criteria();

        foreach ($profile->getIdentifierPaths() as $identifierPath) {
            if (str_contains($identifierPath, '.') || str_contains($identifierPath, '*')) {
                throw ImportExportV2Exception::invalidImportRecord(
                    $recordIndex,
                    \sprintf('Identifier path "%s" is not supported for entity resolution.', $identifierPath)
                );
            }

            $value = ArrayPathAccessor::get($record->getIdentifier(), $identifierPath);
            if (!\is_scalar($value) || (string) $value === '') {
                throw ImportExportV2Exception::invalidImportRecord(
                    $recordIndex,
                    \sprintf('Missing required identifier "%s".', $identifierPath)
                );
            }

            $criteria->addFilter(new EqualsFilter($identifierPath, $value));
        }

        $repository = $this->definitionInstanceRegistry->getRepository($profile->getEntity());
        $id = $repository->searchIds($criteria, $context)->firstId();

        if ($id === null) {
            $firstIdentifier = $profile->getIdentifierPaths()[0] ?? 'id';
            $identifierValue = (string) (ArrayPathAccessor::get($record->getIdentifier(), $firstIdentifier) ?? '');

            throw ImportExportV2Exception::entityNotFound($profile->getEntity(), $firstIdentifier, $identifierValue);
        }

        return $id;
    }

    private function validateReferenceValues(EntityDefinition $definition, ImportExportRecord $record, Context $context): void
    {
        foreach ($record->getPayload() as $property => $value) {
            $field = $definition->getField((string) $property);
            if ($field instanceof ManyToOneAssociationField) {
                $this->validateManyToOneReference($field, $value, $context);

                continue;
            }

            if ($field instanceof ManyToManyAssociationField) {
                $this->validateManyToManyReferences($field, $value, $context);
            }
        }
    }

    private function validateManyToOneReference(ManyToOneAssociationField $field, mixed $value, Context $context): void
    {
        if (!\is_array($value)) {
            return;
        }

        $referenceId = $value['id'] ?? null;
        if (!\is_string($referenceId) || $referenceId === '') {
            return;
        }

        $repository = $this->definitionInstanceRegistry->getRepository($field->getReferenceDefinition()->getEntityName());
        if ($repository->searchIds(new Criteria([$referenceId]), $context)->firstId() === null) {
            throw ImportExportV2Exception::entityNotFound($field->getReferenceDefinition()->getEntityName(), 'id', $referenceId);
        }
    }

    private function validateManyToManyReferences(ManyToManyAssociationField $field, mixed $value, Context $context): void
    {
        if (!\is_array($value)) {
            return;
        }

        $repository = $this->definitionInstanceRegistry->getRepository($field->getToManyReferenceDefinition()->getEntityName());

        foreach ($value as $item) {
            if (!\is_array($item)) {
                continue;
            }

            $referenceId = $item['id'] ?? null;
            if (!\is_string($referenceId) || $referenceId === '') {
                continue;
            }

            if ($repository->searchIds(new Criteria([$referenceId]), $context)->firstId() === null) {
                throw ImportExportV2Exception::entityNotFound($field->getToManyReferenceDefinition()->getEntityName(), 'id', $referenceId);
            }
        }
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function mapManyToOneAssociation(
        array &$payload,
        EntityDefinition $definition,
        ManyToOneAssociationField $field,
        mixed $value
    ): void {
        if (!\is_array($value)) {
            $payload[$field->getPropertyName()] = $value;

            return;
        }

        $referenceId = $value['id'] ?? null;
        $fkField = $definition->getFields()->getByStorageName($field->getStorageName());
        if ($fkField instanceof FkField && \is_string($referenceId) && $referenceId !== '') {
            $payload[$fkField->getPropertyName()] = $referenceId;

            return;
        }

        // If we cannot safely flatten to a foreign key, keep the original value and let DAL validate it.
        $payload[$field->getPropertyName()] = $value;
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $translations
     */
    private function mapTranslations(array &$payload, EntityDefinition $definition, array $translations): void
    {
        foreach ($translations as $languageKey => $translationPayload) {
            if (!\is_array($translationPayload)) {
                continue;
            }

            if ($languageKey !== 'DEFAULT') {
                $payload['translations'][$languageKey] = $translationPayload;

                continue;
            }

            foreach ($translationPayload as $fieldName => $translatedValue) {
                $field = $definition->getField((string) $fieldName);
                if ($field instanceof TranslatedField) {
                    $payload[(string) $fieldName] = $translatedValue;

                    continue;
                }

                $payload['translations'][$languageKey][(string) $fieldName] = $translatedValue;
            }
        }
    }
}
