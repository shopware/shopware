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
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Language\LanguageDefinition;

#[Package('core')]
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

    public function resolvePrimaryKeyFromUpdatedBy(Config $config, ?EntityDefinition $definition, iterable $record): iterable
    {
        if (!$definition) {
            return $record;
        }

        return $this->resolvePrimaryKey(
            $config,
            $definition,
            $this->handleManyToManyAssociations($config, $definition, $record)
        );
    }

    private function resolvePrimaryKey(Config $config, EntityDefinition $definition, iterable $record): iterable
    {
        $updatedBy = $config->getUpdateBy()->get($definition->getEntityName());

        if (!$updatedBy) {
            return $record;
        }

        $updateByField = $updatedBy->getMappedKey();

        if (empty($updateByField) || $definition->getField($updateByField) instanceof IdField) {
            return $record;
        }

        $idFields = $definition->getPrimaryKeys()->filter(fn (Field $field) => $field instanceof IdField);
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
            $criteria
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
        $id = $repository->searchIds($criteria, Context::createDefaultContext())->firstId();

        if ($id) {
            $record[$primaryKeyProperty] = $id;
        }

        return $record;
    }

    /**
     * @return mixed|null
     */
    private function getValueFromPath(array $data, array $keyPath)
    {
        $key = array_shift($keyPath);

        if (!isset($data[$key])) {
            return null;
        }

        if (!\is_array($data[$key])) {
            return $data[$key];
        }

        return $this->getValueFromPath($data[$key], $keyPath);
    }

    private function handleTranslationsAssociation(
        EntityDefinition $definition,
        array $updateByFieldPath,
        Criteria $criteria
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
                    Context::createDefaultContext()
                )->firstId();
        }

        if (!$languageId) {
            return null;
        }

        $criteria->addFilter(new EqualsFilter(
            sprintf('%s.languageId', $updateByFieldPath[0]),
            $languageId
        ));

        unset($updateByFieldPath[1]);

        return implode('.', $updateByFieldPath);
    }

    private function handleManyToManyAssociations(Config $config, EntityDefinition $definition, iterable $record): iterable
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

            if (empty($updateByField) || $definition->getField($updateByField) instanceof IdField) {
                continue;
            }

            $manyToManyValues = explode('|', (string) $record[$field->getPropertyName()]);

            $criteria = new Criteria();
            $updateByField = $this->handleTranslationsAssociation(
                $definition,
                explode('.', $updateByField),
                $criteria
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

            /** @var array<string> $ids */
            $ids = $repository->searchIds($criteria, Context::createDefaultContext())->getIds();

            $record[$field->getPropertyName()] = implode('|', $ids);
        }

        return $record;
    }
}
