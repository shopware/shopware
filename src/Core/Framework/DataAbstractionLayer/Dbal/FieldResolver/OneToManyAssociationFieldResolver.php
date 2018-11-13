<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Dbal\FieldResolver;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityDefinitionQueryHelper;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\QueryBuilder;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\CascadeDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\Inherited;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\ReverseInherited;

class OneToManyAssociationFieldResolver implements FieldResolverInterface
{
    public function resolve(
        string $definition,
        string $root,
        Field $field,
        QueryBuilder $query,
        Context $context,
        EntityDefinitionQueryHelper $queryHelper,
        bool $raw
    ): bool {
        if (!$field instanceof OneToManyAssociationField) {
            return false;
        }

        $query->addState(EntityDefinitionQueryHelper::HAS_TO_MANY_JOIN);

        /** @var EntityDefinition|string $reference */
        $reference = $field->getReferenceClass();

        $table = $reference::getEntityName();

        $alias = $root . '.' . $field->getPropertyName();
        if ($query->hasState($alias)) {
            return true;
        }
        $query->addState($alias);

        $versionJoin = '';
        /** @var string|EntityDefinition $definition */
        if ($definition::isVersionAware() && $field->is(CascadeDelete::class)) {
            $fkVersionId = $definition::getEntityName() . '_version_id';

            if ($reference::getFields()->getByStorageName($fkVersionId) === null) {
                $fkVersionId = 'version_id';
            }

            $versionJoin = ' AND #root#.version_id = #alias#.' . $fkVersionId;
        }

        $catalogJoinCondition = '';
        if ($definition::isCatalogAware() && $reference::isCatalogAware()) {
            $catalogJoinCondition = ' AND #root#.catalog_id = #alias#.catalog_id';
        }

        $source = EntityDefinitionQueryHelper::escape($root) . '.' . EntityDefinitionQueryHelper::escape($field->getLocalField());
        if ($field->is(Inherited::class)) {
            $source = EntityDefinitionQueryHelper::escape($root) . '.' . EntityDefinitionQueryHelper::escape($field->getPropertyName());
        }

        $referenceColumn = EntityDefinitionQueryHelper::escape($field->getReferenceField());
        if ($field->is(ReverseInherited::class)) {
            /** @var ReverseInherited $flag */
            $flag = $field->getFlag(ReverseInherited::class);

            $referenceColumn = EntityDefinitionQueryHelper::escape($flag->getName());
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
                '#source# = #alias#.#reference_column#' .
                $versionJoin .
                $catalogJoinCondition
            )
        );

        if ($definition === $reference) {
            return true;
        }

        if (!$reference::isInheritanceAware()) {
            return true;
        }

        $parent = $reference::getFields()->get('parent');
        $queryHelper->resolveField($parent, $reference, $alias, $query, $context);

        return true;
    }
}
