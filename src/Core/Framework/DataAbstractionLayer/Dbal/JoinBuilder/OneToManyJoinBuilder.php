<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Dbal\JoinBuilder;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityDefinitionQueryHelper;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\QueryBuilder;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Inherited;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ReverseInherited;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;

/**
 * @deprecated tag:v6.4.0 - Will be removed
 */
class OneToManyJoinBuilder implements JoinBuilderInterface
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
        if (!$field instanceof OneToManyAssociationField) {
            throw new \InvalidArgumentException('Expected ' . OneToManyAssociationField::class);
        }

        $reference = $field->getReferenceDefinition();
        $table = $reference->getEntityName();

        $versionJoin = '';
        if ($definition->isVersionAware() && $field->is(CascadeDelete::class)) {
            $fkVersionId = $definition->getEntityName() . '_version_id';

            if ($reference->getFields()->getByStorageName($fkVersionId) === null) {
                $fkVersionId = 'version_id';
            }

            $versionJoin = ' AND #root#.version_id = #alias#.' . $fkVersionId;
        }

        $source = EntityDefinitionQueryHelper::escape($on) . '.' . EntityDefinitionQueryHelper::escape($field->getLocalField());
        if ($field->is(Inherited::class) && $context->considerInheritance()) {
            $source = EntityDefinitionQueryHelper::escape($on) . '.' . EntityDefinitionQueryHelper::escape($field->getPropertyName());
        }

        $referenceColumn = EntityDefinitionQueryHelper::escape($field->getReferenceField());
        if ($field->is(ReverseInherited::class) && $context->considerInheritance()) {
            /** @var ReverseInherited $flag */
            $flag = $field->getFlag(ReverseInherited::class);

            $referenceColumn = EntityDefinitionQueryHelper::escape($flag->getReversedPropertyName());
        }

        $ruleCondition = $this->queryHelper->buildRuleCondition($reference, $queryBuilder, $alias, $context);
        if ($ruleCondition !== null) {
            $ruleCondition = ' AND ' . $ruleCondition;
        }

        $parameters = [
            '#source#' => $source,
            '#alias#' => EntityDefinitionQueryHelper::escape($alias),
            '#reference_column#' => $referenceColumn,
            '#root#' => EntityDefinitionQueryHelper::escape($on),
        ];

        if ($joinType === JoinBuilderInterface::INNER_JOIN) {
            $queryBuilder->innerJoin(
                EntityDefinitionQueryHelper::escape($on),
                EntityDefinitionQueryHelper::escape($table),
                EntityDefinitionQueryHelper::escape($alias),
                str_replace(
                    array_keys($parameters),
                    array_values($parameters),
                    '#source# = #alias#.#reference_column#' . $versionJoin . $ruleCondition
                )
            );
        } else {
            $queryBuilder->leftJoin(
                EntityDefinitionQueryHelper::escape($on),
                EntityDefinitionQueryHelper::escape($table),
                EntityDefinitionQueryHelper::escape($alias),
                str_replace(
                    array_keys($parameters),
                    array_values($parameters),
                    '#source# = #alias#.#reference_column#' . $versionJoin . $ruleCondition
                )
            );
        }
    }
}
