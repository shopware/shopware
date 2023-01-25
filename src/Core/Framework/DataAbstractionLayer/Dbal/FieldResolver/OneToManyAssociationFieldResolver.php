<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Dbal\FieldResolver;

use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityDefinitionQueryHelper;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Inherited;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ReverseInherited;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('core')]
class OneToManyAssociationFieldResolver extends AbstractFieldResolver
{
    public function join(FieldResolverContext $context): string
    {
        $field = $context->getField();
        if (!$field instanceof OneToManyAssociationField) {
            return $context->getAlias();
        }

        $context->getQuery()->addState(EntityDefinitionQueryHelper::HAS_TO_MANY_JOIN);

        $alias = $context->getAlias() . '.' . $field->getPropertyName();
        if ($context->getQuery()->hasState($alias)) {
            return $alias;
        }

        $context->getQuery()->addState($alias);

        $source = $this->getSourceColumn($context, $field);

        $referenceColumn = $this->getReferenceColumn($context, $field);

        $parameters = [
            '#source#' => $source,
            '#alias#' => EntityDefinitionQueryHelper::escape($alias),
            '#reference_column#' => $referenceColumn,
            '#root#' => EntityDefinitionQueryHelper::escape($context->getAlias()),
        ];

        $versionWhere = $this->buildVersionWhere($context, $field);

        $context->getQuery()->leftJoin(
            EntityDefinitionQueryHelper::escape($context->getAlias()),
            EntityDefinitionQueryHelper::escape($field->getReferenceDefinition()->getEntityName()),
            EntityDefinitionQueryHelper::escape($alias),
            str_replace(
                array_keys($parameters),
                array_values($parameters),
                '#source# = #alias#.#reference_column#' . $versionWhere
            )
        );

        return $alias;
    }

    private function buildVersionWhere(FieldResolverContext $context, OneToManyAssociationField $field): string
    {
        if (!$context->getDefinition()->isVersionAware()) {
            return '';
        }
        if (!$field->is(CascadeDelete::class)) {
            return '';
        }

        $fkVersionId = $context->getDefinition()->getEntityName() . '_version_id';

        $reference = $field->getReferenceDefinition();
        if ($reference->getFields()->getByStorageName($fkVersionId)) {
            return ' AND #root#.version_id = #alias#.' . $fkVersionId;
        }

        $fkVersionId = \substr($field->getReferenceField(), 0, -3) . '_version_id';
        if ($reference->getFields()->getByStorageName($fkVersionId)) {
            return ' AND #root#.version_id = #alias#.' . $fkVersionId;
        }

        return ' AND #root#.version_id = #alias#.version_id';
    }

    private function getSourceColumn(FieldResolverContext $context, OneToManyAssociationField $field): string
    {
        if ($field->is(Inherited::class) && $context->getContext()->considerInheritance()) {
            return EntityDefinitionQueryHelper::escape($context->getAlias()) . '.' . EntityDefinitionQueryHelper::escape($field->getPropertyName());
        }

        return EntityDefinitionQueryHelper::escape($context->getAlias()) . '.' . EntityDefinitionQueryHelper::escape($field->getLocalField());
    }

    private function getReferenceColumn(FieldResolverContext $context, OneToManyAssociationField $field): string
    {
        if ($field->is(ReverseInherited::class) && $context->getContext()->considerInheritance()) {
            /** @var ReverseInherited $flag */
            $flag = $field->getFlag(ReverseInherited::class);

            return EntityDefinitionQueryHelper::escape($flag->getReversedPropertyName());
        }

        return EntityDefinitionQueryHelper::escape($field->getReferenceField());
    }
}
