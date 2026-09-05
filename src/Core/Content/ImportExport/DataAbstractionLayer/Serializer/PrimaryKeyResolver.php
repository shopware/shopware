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
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslationsAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\PartialEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Language\LanguageDefinition;

#[Package('fundamentals@after-sales')]
class PrimaryKeyResolver
{
    /**
     * The primary keys resolved by {@see warmUp()}, as entity name => update-by value => id.
     *
     * Only records that already existed when the window was announced are in here. A value without a record must not
     * be remembered: a row of the same window may still create it, and the rows behind that one have to find it.
     *
     * @var array<string, array<string, string>>
     */
    private array $resolvedPrimaryKeys = [];

    /**
     * @internal
     */
    public function __construct(
        private readonly DefinitionInstanceRegistry $definitionInstanceRegistry,
        private readonly AbstractFieldSerializer $fieldSerializer
    ) {
    }

    /**
     * Resolves the primary keys of a whole window of records with one query, so that
     * {@see resolvePrimaryKeyFromUpdatedBy()} does not have to look up one record at a time.
     *
     * Only an update-by field that is a plain field of the entity can be batched: its value has to be read back next
     * to the id to map the rows onto the records, which is not possible for a path into an association such as
     * `translations.DEFAULT.name`. Those keep resolving per record, as do the values that have no record yet.
     *
     * @param list<array<string, mixed>> $records
     */
    public function warmUp(Config $config, ?EntityDefinition $definition, array $records): void
    {
        if (!$definition || $records === []) {
            return;
        }

        $updateByField = $config->getUpdateBy()->get($definition->getEntityName())?->getMappedKey();

        if ($updateByField === null || $updateByField === '' || str_contains($updateByField, '.')) {
            return;
        }

        $field = $definition->getField($updateByField);

        if ($field === null || $field instanceof IdField) {
            return;
        }

        $context = Context::createDefaultContext();
        $entityName = $definition->getEntityName();

        // Only the current window is kept: a later window has to see the records the windows before it wrote, so a
        // value that had no record must not stay unresolved for the rest of the import.
        $this->resolvedPrimaryKeys = [];

        $values = [];
        foreach ($records as $record) {
            $value = $record[$updateByField] ?? null;

            if ($value === null) {
                continue;
            }

            $value = $this->fieldSerializer->deserialize($config, $field, $value);

            if (\is_scalar($value)) {
                $values[(string) $value] = true;
            }
        }

        if ($values === []) {
            return;
        }

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsAnyFilter($updateByField, array_keys($values)));
        $criteria->addFields([$updateByField]);

        foreach ($this->definitionInstanceRegistry->getRepository($entityName)->search($criteria, $context)->getEntities() as $entity) {
            \assert($entity instanceof PartialEntity);
            $this->resolvedPrimaryKeys[$entityName][(string) $entity->get($updateByField)] = (string) $entity->get('id');
        }
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

        return $this->resolvePrimaryKey(
            $config,
            $definition,
            $this->handleManyToManyAssociations($config, $definition, $record, $context),
            $context
        );
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

        $resolved = $this->resolvedPrimaryKeys[$definition->getEntityName()] ?? [];

        // already resolved for the whole window by warmUp()
        if (\is_scalar($updateByValue) && isset($resolved[(string) $updateByValue])) {
            $record[$primaryKeyProperty] = $resolved[(string) $updateByValue];

            return $record;
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

        // a translated update-by field needs the locale of the translation, e.g. `translations.DEFAULT.name`
        if (($updateByFieldPath[1] ?? '') === '') {
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

            if (!$updatedBy || ($record[$field->getPropertyName()] ?? '') === '') {
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
