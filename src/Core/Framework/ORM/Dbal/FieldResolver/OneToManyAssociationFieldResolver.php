<?php declare(strict_types=1);

namespace Shopware\Framework\ORM\Dbal\FieldResolver;

use Shopware\Framework\Context;
use Shopware\Framework\ORM\Dbal\EntityDefinitionQueryHelper;
use Shopware\Framework\ORM\Dbal\QueryBuilder;
use Shopware\Framework\ORM\EntityDefinition;
use Shopware\Framework\ORM\Field\Field;
use Shopware\Framework\ORM\Field\OneToManyAssociationField;
use Shopware\Framework\ORM\Write\Flag\CascadeDelete;
use Shopware\Framework\ORM\Write\Flag\Inherited;

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
    ): void {
        if (!$field instanceof OneToManyAssociationField) {
            return;
        }

        $query->addState(EntityDefinitionQueryHelper::HAS_TO_MANY_JOIN);

        /** @var EntityDefinition|string $reference */
        $reference = $field->getReferenceClass();

        $table = $reference::getEntityName();

        $alias = $root . '.' . $field->getPropertyName();
        if ($query->hasState($alias)) {
            return;
        }
        $query->addState($alias);

        $versionJoin = '';
        /** @var string|EntityDefinition $definition */
        if ($definition::isVersionAware() && $field->is(CascadeDelete::class)) {
            $versionJoin = ' AND #root#.version_id = #alias#.version_id';
        }

        $catalogJoinCondition = '';
        if ($definition::isCatalogAware() && $reference::isCatalogAware()) {
            $catalogJoinCondition = ' AND #root#.catalog_id = #alias#.catalog_id';
        }

        $tenantJoinCondition = '';
        if ($definition::isTenantAware() && $reference::isTenantAware()) {
            $tenantJoinCondition = ' AND #root#.tenant_id = #alias#.tenant_id';
        }

        $source = EntityDefinitionQueryHelper::escape($root) . '.' . EntityDefinitionQueryHelper::escape($field->getLocalField());
        if ($field->is(Inherited::class)) {
            $source = EntityDefinitionQueryHelper::escape($root) . '.' . EntityDefinitionQueryHelper::escape($field->getPropertyName());
        }
        $parameters = [
            '#source#' => $source,
            '#alias#' => EntityDefinitionQueryHelper::escape($alias),
            '#reference_column#' => EntityDefinitionQueryHelper::escape($field->getReferenceField()),
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
                $catalogJoinCondition .
                $tenantJoinCondition
            )
        );

        if ($definition === $reference) {
            return;
        }

        if (!$reference::getParentPropertyName()) {
            return;
        }

        $parent = $reference::getFields()->get($reference::getParentPropertyName());
        $queryHelper->resolveField($parent, $reference, $alias, $query, $context);
    }
}
