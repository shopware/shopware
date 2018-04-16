<?php

namespace Shopware\Api\Entity\Dbal\FieldResolver;

use Shopware\Api\Entity\Dbal\EntityDefinitionQueryHelper;
use Shopware\Api\Entity\Dbal\QueryBuilder;
use Shopware\Api\Entity\EntityDefinition;
use Shopware\Api\Entity\Field\Field;
use Shopware\Api\Entity\Field\ManyToManyAssociationField;
use Shopware\Api\Entity\Field\ManyToOneAssociationField;
use Shopware\Context\Struct\ApplicationContext;

class ManyToManyAssociationFieldResolver implements FieldResolverInterface
{
    public function resolve(
        string $definition,
        string $root,
        Field $field,
        QueryBuilder $query,
        ApplicationContext $context,
        EntityDefinitionQueryHelper $queryHelper,
        bool $raw
    ): void
    {
        if (!$field instanceof ManyToManyAssociationField) {
            return;
        }
        $query->addState(EntityDefinitionQueryHelper::HAS_TO_MANY_JOIN);

        /** @var EntityDefinition $mapping */
        $mapping = $field->getMappingDefinition();
        $table = $mapping::getEntityName();

        $mappingAlias = $root . '.' . $field->getPropertyName() . '.mapping';

        if ($query->hasState($mappingAlias)) {
            return;
        }
        $query->addState($mappingAlias);

        $versionJoinCondition = '';
        /** @var string|EntityDefinition $definition */
        if ($definition::isVersionAware() && $mapping::isVersionAware() && $field->is(CascadeDelete::class)) {
            $versionField = $definition::getEntityName() . '_version_id';
            $versionJoinCondition = ' AND #root#.version_id = #alias#.' . $versionField;
        }

        $query->leftJoin(
            EntityDefinitionQueryHelper::escape($root),
            EntityDefinitionQueryHelper::escape($table),
            EntityDefinitionQueryHelper::escape($mappingAlias),
            str_replace(
                ['#root#', '#source_column#', '#alias#', '#reference_column#'],
                [
                    EntityDefinitionQueryHelper::escape($root),
                    EntityDefinitionQueryHelper::escape('id'),
                    EntityDefinitionQueryHelper::escape($mappingAlias),
                    EntityDefinitionQueryHelper::escape($field->getMappingLocalColumn()),
                ],
                '#root#.#source_column# = #alias#.#reference_column#' . $versionJoinCondition
            )
        );

        /** @var EntityDefinition|string $reference */
        $reference = $field->getReferenceDefinition();
        $table = $reference::getEntityName();

        $alias = $root . '.' . $field->getPropertyName();

        $versionJoinCondition = '';
        /* @var string|EntityDefinition $definition */
        if ($reference::isVersionAware()) {
            $versionField = $reference::getEntityName() . '_version_id';
            $versionJoinCondition = 'AND #alias#.version_id = #mapping#.' . $versionField;
        }

        $catalogJoinCondition = '';
        if ($definition::isCatalogAware() && $reference::isCatalogAware()) {
            $catalogJoinCondition = ' AND #root#.catalog_id = #alias#.catalog_id';
        }

        $query->leftJoin(
            EntityDefinitionQueryHelper::escape($mappingAlias),
            EntityDefinitionQueryHelper::escape($table),
            EntityDefinitionQueryHelper::escape($alias),
            str_replace(
                ['#mapping#', '#source_column#', '#alias#', '#reference_column#', '#root#'],
                [
                    EntityDefinitionQueryHelper::escape($mappingAlias),
                    EntityDefinitionQueryHelper::escape($field->getMappingReferenceColumn()),
                    EntityDefinitionQueryHelper::escape($alias),
                    EntityDefinitionQueryHelper::escape($field->getReferenceField()),
                    EntityDefinitionQueryHelper::escape($root),
                ],
                '#mapping#.#source_column# = #alias#.#reference_column# ' . $versionJoinCondition . $catalogJoinCondition
            )
        );

        if ($definition === $reference) {
            return;
        }

        if (!$reference::getParentPropertyName()) {
            return;
        }

        /** @var ManyToOneAssociationField $parent */
        $parent = $reference::getFields()->get($reference::getParentPropertyName());

        $queryHelper->resolveField($parent, $reference, $alias, $query, $context);
    }
}