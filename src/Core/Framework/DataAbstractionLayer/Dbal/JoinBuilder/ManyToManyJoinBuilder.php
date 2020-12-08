<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Dbal\JoinBuilder;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityDefinitionQueryHelper;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\QueryBuilder;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Inherited;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ReverseInherited;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;

class ManyToManyJoinBuilder implements JoinBuilderInterface
{
    /**
     * @var EntityDefinitionQueryHelper
     */
    private $queryHelper;

    public function __construct(EntityDefinitionQueryHelper $queryHelper)
    {
        $this->queryHelper = $queryHelper;
    }

    public function join(EntityDefinition $definition, string $joinType, $field, string $on, string $alias, QueryBuilder $queryBuilder, Context $context): void
    {
        if (!$field instanceof ManyToManyAssociationField) {
            throw new \InvalidArgumentException('Expected ' . ManyToManyAssociationField::class);
        }

        $mappingAlias = $alias . '.mapping';
        $this->joinMapping($joinType, $on, $mappingAlias, $field, $definition, $queryBuilder, $context);

        $reference = $field->getToManyReferenceDefinition();

        $this->joinMainTable($joinType, $mappingAlias, $alias, $field, $reference, $queryBuilder, $context);
    }

    private function joinMapping(string $joinType, string $on, string $alias, ManyToManyAssociationField $association, EntityDefinition $definition, QueryBuilder $builder, Context $context): void
    {
        $mapping = $association->getMappingDefinition();
        $table = $mapping->getEntityName();

        $versionJoinCondition = '';
        if ($definition->isVersionAware() && $association->is(CascadeDelete::class)) {
            $versionField = $definition->getEntityName() . '_version_id';
            $versionJoinCondition = ' AND #root#.version_id = #alias#.' . $versionField;
        }

        $source = EntityDefinitionQueryHelper::escape($on) . '.' . EntityDefinitionQueryHelper::escape($association->getLocalField());
        if ($association->is(Inherited::class) && $context->considerInheritance()) {
            $source = EntityDefinitionQueryHelper::escape($on) . '.' . EntityDefinitionQueryHelper::escape($association->getPropertyName());
        }

        $parameters = [
            '#root#' => EntityDefinitionQueryHelper::escape($on),
            '#source#' => $source,
            '#alias#' => EntityDefinitionQueryHelper::escape($alias),
            '#reference_column#' => EntityDefinitionQueryHelper::escape($association->getMappingLocalColumn()),
        ];

        if ($joinType === self::INNER_JOIN) {
            $builder->innerJoin(
                EntityDefinitionQueryHelper::escape($on),
                EntityDefinitionQueryHelper::escape($table),
                EntityDefinitionQueryHelper::escape($alias),
                str_replace(
                    array_keys($parameters),
                    array_values($parameters),
                    '#source# = #alias#.#reference_column# ' . $versionJoinCondition
                )
            );
        } else {
            $builder->leftJoin(
                EntityDefinitionQueryHelper::escape($on),
                EntityDefinitionQueryHelper::escape($table),
                EntityDefinitionQueryHelper::escape($alias),
                str_replace(
                    array_keys($parameters),
                    array_values($parameters),
                    '#source# = #alias#.#reference_column# ' . $versionJoinCondition
                )
            );
        }
    }

    private function joinMainTable(string $joinType, string $joinAlias, string $alias, ManyToManyAssociationField $association, EntityDefinition $referenceDefinition, QueryBuilder $builder, Context $context): void
    {
        $table = $referenceDefinition->getEntityName();

        $versionJoinCondition = '';
        if ($referenceDefinition->isVersionAware() && $association->is(CascadeDelete::class)) {
            $versionField = '`' . $referenceDefinition->getEntityName() . '_version_id`';
            $versionJoinCondition = ' AND #alias#.`version_id` = #mapping#.' . $versionField;
        }

        $referenceColumn = EntityDefinitionQueryHelper::escape($association->getReferenceField());
        if ($association->is(ReverseInherited::class) && $context->considerInheritance()) {
            /** @var ReverseInherited $flag */
            $flag = $association->getFlag(ReverseInherited::class);

            $referenceColumn = EntityDefinitionQueryHelper::escape($flag->getReversedPropertyName());
        }

        $ruleCondition = $this->queryHelper->buildRuleCondition($referenceDefinition, $builder, $alias, $context);
        if ($ruleCondition !== null) {
            $ruleCondition = ' AND ' . $ruleCondition;
        }

        $parameters = [
            '#mapping#' => EntityDefinitionQueryHelper::escape($joinAlias),
            '#source_column#' => EntityDefinitionQueryHelper::escape($association->getMappingReferenceColumn()),
            '#alias#' => EntityDefinitionQueryHelper::escape($alias),
            '#reference_column#' => $referenceColumn,
        ];

        if ($joinType === self::INNER_JOIN) {
            $builder->innerJoin(
                EntityDefinitionQueryHelper::escape($joinAlias),
                EntityDefinitionQueryHelper::escape($table),
                EntityDefinitionQueryHelper::escape($alias),
                str_replace(
                    array_keys($parameters),
                    array_values($parameters),
                    '#mapping#.#source_column# = #alias#.#reference_column# ' . $versionJoinCondition . $ruleCondition
                )
            );
        } else {
            $builder->leftJoin(
                EntityDefinitionQueryHelper::escape($joinAlias),
                EntityDefinitionQueryHelper::escape($table),
                EntityDefinitionQueryHelper::escape($alias),
                str_replace(
                    array_keys($parameters),
                    array_values($parameters),
                    '#mapping#.#source_column# = #alias#.#reference_column# ' . $versionJoinCondition . $ruleCondition
                )
            );
        }
    }
}
