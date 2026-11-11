<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExport\DataAbstractionLayer\Serializer;

use Shopware\Core\Content\ImportExport\DataAbstractionLayer\Serializer\Field\AbstractFieldSerializer;
use Shopware\Core\Content\ImportExport\Exception\UpdatedByValueNotFoundException;
use Shopware\Core\Content\ImportExport\Struct\Config;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslationsAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Language\LanguageDefinition;

#[Package('fundamentals@after-sales')]
class PrimaryKeyResolver
{
    /**
     * @internal
     */
    public function __construct(
        private readonly DefinitionInstanceRegistry $definitionInstanceRegistry,
        private readonly AbstractFieldSerializer $fieldSerializer
    ) {
    }

    /**
     * @param iterable<string, mixed> $record
     *
     * @return iterable<string, mixed>
     */
    public function resolvePrimaryKeyFromUpdatedBy(Config $config, ?EntityDefinition $definition, iterable $record): iterable
    {
        if (!$definition) {
            return $record;
        }

        $context = Context::createDefaultContext();

        $record = $this->resolvePrimaryKey(
            $config,
            $definition,
            $this->handleManyToManyAssociations($config, $definition, $record, $context),
            $context
        );

        return $this->resolveReverseOneToOnePrimaryKeys($config, $definition, $record, $context);
    }

    /**
     * @param iterable<string, mixed> $record
     *
     * @return iterable<string, mixed>
     */
    private function resolvePrimaryKey(Config $config, EntityDefinition $definition, iterable $record, Context $context): iterable
    {
        $updatedBy = $config->getUpdateBy()->get($definition->getEntityName());

        if (!$updatedBy) {
            return $record;
        }

        $updateByField = $updatedBy->getMappedKey();

        if ($updateByField === null || $updateByField === '' || $definition->getField($updateByField) instanceof IdField) {
            return $record;
        }

        $idFields = $definition->getPrimaryKeys()->filter(static fn (Field $field) => $field instanceof IdField);
        $idField = $idFields->first();

        if ($idFields->count() !== 1 || !$idField) {
            return $record;
        }

        $primaryKeyProperty = $idField->getPropertyName();

        $updateByFieldPath = explode('.', $updateByField);
        $record = \is_array($record) ? $record : iterator_to_array($record);
        $updateByValue = $this->getValueFromPath($record, $updateByFieldPath);

        if ($updateByValue === null) {
            $record['_error'] = new UpdatedByValueNotFoundException($definition->getEntityName(), $updateByField);

            return $record;
        }

        $criteria = new Criteria();
        $criteria->setLimit(1);

        $updateByField = $this->handleTranslationsAssociation(
            $definition,
            $updateByFieldPath,
            $criteria,
            $context
        );

        if (!$updateByField) {
            return $record;
        }

        if ($field = $definition->getField($updateByField)) {
            // deserialize for bool, date, int fields...
            $updateByValue = $this->fieldSerializer->deserialize($config, $field, $updateByValue);
        }

        $criteria->addFilter(new EqualsFilter(
            $updateByField,
            $updateByValue
        ));

        $repository = $this->definitionInstanceRegistry->getRepository($definition->getEntityName());
        $id = $repository->searchIds($criteria, $context)->firstId();

        if ($id) {
            $record[$primaryKeyProperty] = $id;
        }

        return $record;
    }

    /**
     * A one-to-one association whose foreign key lives on the referenced entity (e.g. `order.orderCustomer`) can only
     * be updated if the record carries the referenced entity's primary key. Without it, the DAL generates a new id and
     * tries to insert a second referenced entity. Look the existing one up by its foreign key to the imported entity.
     *
     * The record still holds the raw import values here, so ids are deserialized before they are used in a search.
     * Errors are reported through `_error`, because this runs outside the per-row error handling of the import.
     *
     * @param iterable<string, mixed> $record
     *
     * @return iterable<string, mixed>
     */
    private function resolveReverseOneToOnePrimaryKeys(Config $config, EntityDefinition $definition, iterable $record, Context $context): iterable
    {
        $record = \is_array($record) ? $record : iterator_to_array($record);

        if (isset($record['_error']) || $this->isCreateOnly($config)) {
            return $record;
        }

        foreach ($definition->getFields() as $field) {
            if (!$field instanceof OneToOneAssociationField) {
                continue;
            }

            $association = $record[$field->getPropertyName()] ?? null;
            if (!\is_array($association)) {
                continue;
            }

            $localField = $definition->getFields()->getByStorageName($field->getStorageName());
            if (!$localField instanceof IdField) {
                continue;
            }

            try {
                $parentId = $this->fieldSerializer->deserialize($config, $localField, $record[$localField->getPropertyName()] ?? null);
            } catch (\Throwable $exception) {
                $record['_error'] = $exception;

                return $record;
            }

            if (!\is_string($parentId) || !Uuid::isValid($parentId)) {
                continue;
            }

            $referenceDefinition = $field->getReferenceDefinition();
            $referenceFkField = $referenceDefinition->getFields()->getByStorageName($field->getReferenceField());
            $referenceIdFields = $referenceDefinition->getPrimaryKeys()->filter(static fn (Field $field) => $field instanceof IdField);
            $referenceIdField = $referenceIdFields->first();

            if (!$referenceFkField instanceof FkField || $referenceIdFields->count() !== 1 || !$referenceIdField) {
                continue;
            }

            // an exported file always contains the id column, which stays blank for associations that should be resolved
            $associationId = $association[$referenceIdField->getPropertyName()] ?? null;
            if (\is_string($associationId) ? trim($associationId) !== '' : $associationId !== null) {
                continue;
            }

            $criteria = new Criteria();
            $criteria->addFilter(new EqualsFilter($referenceFkField->getPropertyName(), $parentId));
            $criteria->setLimit(1);

            $id = $this->definitionInstanceRegistry
                ->getRepository($referenceDefinition->getEntityName())
                ->searchIds($criteria, $context)
                ->firstId();

            if ($id) {
                $association[$referenceIdField->getPropertyName()] = $id;
                $record[$field->getPropertyName()] = $association;
            }
        }

        return $record;
    }

    /**
     * A create-only import fails for existing entities anyway, so there is nothing to resolve.
     */
    private function isCreateOnly(Config $config): bool
    {
        return ($config->get('createEntities') ?? true) === true
            && ($config->get('updateEntities') ?? true) === false;
    }

    /**
     * @param array<string, mixed> $data
     * @param list<string> $keyPath
     */
    private function getValueFromPath(array $data, array $keyPath): mixed
    {
        $key = array_shift($keyPath);
        if ($key === null) {
            return null;
        }

        if (!isset($data[$key])) {
            return null;
        }

        if (!\is_array($data[$key])) {
            return $data[$key];
        }

        return $this->getValueFromPath($data[$key], $keyPath);
    }

    /**
     * @param list<string> $updateByFieldPath
     */
    private function handleTranslationsAssociation(
        EntityDefinition $definition,
        array $updateByFieldPath,
        Criteria $criteria,
        Context $context
    ): ?string {
        \assert(\is_string($updateByFieldPath[0]));

        if (!$definition->getField($updateByFieldPath[0]) instanceof TranslationsAssociationField) {
            return implode('.', $updateByFieldPath);
        }

        if (empty($updateByFieldPath[1])) {
            return null;
        }

        if ($updateByFieldPath[1] === 'DEFAULT') {
            $languageId = Defaults::LANGUAGE_SYSTEM;
        } else {
            $languageId = $this->definitionInstanceRegistry
                ->getRepository(LanguageDefinition::ENTITY_NAME)
                ->searchIds(
                    (new Criteria())->addFilter(new EqualsFilter('locale.code', $updateByFieldPath[1]))->setLimit(1),
                    $context
                )->firstId();
        }

        if (!$languageId) {
            return null;
        }

        $criteria->addFilter(new EqualsFilter(
            \sprintf('%s.languageId', $updateByFieldPath[0]),
            $languageId
        ));

        unset($updateByFieldPath[1]);

        return implode('.', $updateByFieldPath);
    }

    /**
     * @param iterable<string, mixed> $record
     *
     * @return iterable<string, mixed>
     */
    private function handleManyToManyAssociations(Config $config, EntityDefinition $definition, iterable $record, Context $context): iterable
    {
        foreach ($definition->getFields() as $field) {
            if (!$field instanceof ManyToManyAssociationField) {
                continue;
            }

            $manyToManyDefinition = $field->getToManyReferenceDefinition();
            $updatedBy = $config->getUpdateBy()->get($manyToManyDefinition->getEntityName());
            $record = \is_array($record) ? $record : iterator_to_array($record);

            if (!$updatedBy || empty($record[$field->getPropertyName()])) {
                continue;
            }

            $updateByField = $updatedBy->getMappedKey();

            if ($updateByField === null || $updateByField === '' || $definition->getField($updateByField) instanceof IdField) {
                continue;
            }

            $manyToManyValues = explode('|', (string) $record[$field->getPropertyName()]);

            $criteria = new Criteria();
            $updateByField = $this->handleTranslationsAssociation(
                $definition,
                explode('.', $updateByField),
                $criteria,
                $context
            );

            if (!$updateByField) {
                continue;
            }

            $orQueries = [];
            foreach ($manyToManyValues as $manyToManyValue) {
                $orQueries[] = new EqualsFilter($updateByField, $manyToManyValue);
            }

            $criteria->addFilter(new MultiFilter(MultiFilter::CONNECTION_OR, $orQueries));

            $repository = $this->definitionInstanceRegistry->getRepository($manyToManyDefinition->getEntityName());

            $ids = $repository->searchIds($criteria, $context)->getIds();

            $record[$field->getPropertyName()] = implode('|', $ids);
        }

        return $record;
    }
}
