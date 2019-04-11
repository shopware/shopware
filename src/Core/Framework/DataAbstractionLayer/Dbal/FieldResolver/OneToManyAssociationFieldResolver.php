<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Dbal\FieldResolver;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityDefinitionQueryHelper;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\QueryBuilder;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Inherited;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ReverseInherited;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;

class OneToManyAssociationFieldResolver implements FieldResolverInterface
{
    public function resolve(
        EntityDefinition $definition,
        string $root,
        Field $field,
        QueryBuilder $query,
        Context $context,
        EntityDefinitionQueryHelper $queryHelper
    ): bool {
        if (!$field instanceof OneToManyAssociationField) {
            return false;
        }

        $query->addState(EntityDefinitionQueryHelper::HAS_TO_MANY_JOIN);

        $reference = $field->getReferenceDefinition();

        $table = $reference->getEntityName();

        $alias = $root . '.' . $field->getPropertyName();
        if ($query->hasState($alias)) {
            return true;
        }
        $query->addState($alias);

        $versionJoin = '';
        if ($definition->isVersionAware() && $field->is(CascadeDelete::class)) {
            $fkVersionId = $definition->getEntityName() . '_version_id';

            if ($reference->getFields()->getByStorageName($fkVersionId) === null) {
                $fkVersionId = 'version_id';
            }

            $versionJoin = ' AND #root#.version_id = #alias#.' . $fkVersionId;
        }

        $source = EntityDefinitionQueryHelper::escape($root) . '.' . EntityDefinitionQueryHelper::escape($field->getLocalField());
        if ($field->is(Inherited::class) && $context->considerInheritance()) {
            $source = EntityDefinitionQueryHelper::escape($root) . '.' . EntityDefinitionQueryHelper::escape($field->getPropertyName());
        }

        $referenceColumn = EntityDefinitionQueryHelper::escape($field->getReferenceField());
        if ($field->is(ReverseInherited::class) && $context->considerInheritance()) {
            /** @var ReverseInherited $flag */
            $flag = $field->getFlag(ReverseInherited::class);

            $referenceColumn = EntityDefinitionQueryHelper::escape($flag->getReversedPropertyName());
        }

        $ruleCondition = $queryHelper->buildRuleCondition($reference, $query, $alias, $context);
        if ($ruleCondition !== null) {
            $ruleCondition = ' AND ' . $ruleCondition;
        }

        $parameters = [
            '#source#' => $source,
            '#alias#' => EntityDefinitionQueryHelper::escape($alias),
            '#reference_column#' => $referenceColumn,
            '#root#' => EntityDefinitionQueryHelper::escape($root),
        ];

        $query->leftJoin(
            EntityDefinitionQueryHelper::escape($root),
            EntityDefinitionQueryHelper::escape($table),
            EntityDefinitionQueryHelper::escape($alias),
            str_replace(
                array_keys($parameters),
                array_values($parameters),
                '#source# = #alias#.#reference_column#' . $versionJoin . $ruleCondition
            )
        );

        if ($definition->getClass() === $reference->getClass()) {
            return true;
        }

        if (!$reference->isInheritanceAware() || !$context->considerInheritance()) {
            return true;
        }

        $parent = $reference->getFields()->get('parent');
        $queryHelper->resolveField($parent, $reference, $alias, $query, $context);

        return true;
    }
}
