<?php declare(strict_types=1);

namespace Shopware\Api\Entity\Dbal\FieldResolver;

use Shopware\Api\Entity\Dbal\EntityDefinitionQueryHelper;
use Shopware\Api\Entity\Dbal\QueryBuilder;
use Shopware\Api\Entity\EntityDefinition;
use Shopware\Api\Entity\Field\Field;
use Shopware\Api\Entity\Field\ManyToOneAssociationField;
use Shopware\Api\Entity\Write\Flag\Inherited;
use Shopware\Context\Struct\ApplicationContext;
use Shopware\Defaults;

class ManyToOneAssociationFieldResolver implements FieldResolverInterface
{
    public function resolve(
        string $definition,
        string $root,
        Field $field,
        QueryBuilder $query,
        ApplicationContext $context,
        EntityDefinitionQueryHelper $queryHelper,
        bool $raw
    ): void {
        if (!$field instanceof ManyToOneAssociationField) {
            return;
        }

        /** @var EntityDefinition|string $reference */
        $reference = $field->getReferenceClass();
        $alias = $root . '.' . $field->getPropertyName();
        if ($query->hasState($alias)) {
            return;
        }
        $query->addState($alias);

        $this->join($definition, $root, $field, $query, $context, $queryHelper);

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

    private function join(string $definition, string $root, ManyToOneAssociationField $field, QueryBuilder $query, ApplicationContext $context, EntityDefinitionQueryHelper $queryHelper): void
    {
        /** @var EntityDefinition|string $reference */
        /** @var EntityDefinition|string $definition */
        $reference = $field->getReferenceClass();
        $table = $reference::getEntityName();
        $alias = $root . '.' . $field->getPropertyName();

        $catalogJoinCondition = '';
        if ($definition::isCatalogAware() && $reference::isCatalogAware()) {
            $catalogJoinCondition = ' AND #root#.`catalog_id` = #alias#.`catalog_id`';
        }

        $tenantJoinCondition = '';
        if ($definition::isTenantAware() && $reference::isTenantAware()) {
            $tenantJoinCondition = ' AND #root#.`tenant_id` = #alias#.`tenant_id`';
        }

        $versionAware = ($definition::isVersionAware() && $reference::isVersionAware());

        $source = EntityDefinitionQueryHelper::escape($root) . '.' . EntityDefinitionQueryHelper::escape($field->getStorageName());
        if ($field->is(Inherited::class)) {
            $source = EntityDefinitionQueryHelper::escape($root) . '.' . EntityDefinitionQueryHelper::escape($field->getPropertyName());
        }

        //specified version requested, use sub version call to solve live version or specified
        if ($versionAware && $context->getVersionId() !== Defaults::LIVE_VERSION) {
            $versionQuery = $this->createSubVersionQuery($field, $query, $context, $queryHelper);

            $parameters = [
                '#source#' => $source,
                '#root#' => EntityDefinitionQueryHelper::escape($root),
                '#alias#' => EntityDefinitionQueryHelper::escape($alias),
                '#reference_column#' => EntityDefinitionQueryHelper::escape($field->getReferenceField()),
            ];

            $query->leftJoin(
                EntityDefinitionQueryHelper::escape($root),
                '(' . $versionQuery->getSQL() . ')',
                EntityDefinitionQueryHelper::escape($alias),
                str_replace(
                    array_keys($parameters),
                    array_values($parameters),
                    '#source# = #alias#.#reference_column#' .
                    $catalogJoinCondition .
                    $tenantJoinCondition
                )
            );

            return;
        }

        if ($versionAware) {

            $parameters = [
                '#source#' => $source,
                '#root#' => EntityDefinitionQueryHelper::escape($root),
                '#alias#' => EntityDefinitionQueryHelper::escape($alias),
                '#reference_column#' => EntityDefinitionQueryHelper::escape($field->getReferenceField()),
            ];

            $query->leftJoin(
                EntityDefinitionQueryHelper::escape($root),
                EntityDefinitionQueryHelper::escape($table),
                EntityDefinitionQueryHelper::escape($alias),
                str_replace(
                    array_keys($parameters),
                    array_values($parameters),
                    '#source# = #alias#.#reference_column# AND #root#.`version_id` = #alias#.`version_id`' .
                    $catalogJoinCondition .
                    $tenantJoinCondition
                )
            );

            return;
        }

        $parameters = [
            '#source#' => $source,
            '#root#' => EntityDefinitionQueryHelper::escape($root),
            '#alias#' => EntityDefinitionQueryHelper::escape($alias),
            '#reference_column#' => EntityDefinitionQueryHelper::escape($field->getReferenceField()),
        ];

        $query->leftJoin(
            EntityDefinitionQueryHelper::escape($root),
            EntityDefinitionQueryHelper::escape($table),
            EntityDefinitionQueryHelper::escape($alias),
            str_replace(
                array_keys($parameters),
                array_values($parameters),
                '#source# = #alias#.#reference_column#' .
                $catalogJoinCondition .
                $tenantJoinCondition
            )
        );
    }

    private function createSubVersionQuery(ManyToOneAssociationField $field, QueryBuilder $query, ApplicationContext $context, EntityDefinitionQueryHelper $queryHelper): QueryBuilder
    {
        $subRoot = $field->getReferenceClass()::getEntityName();

        $versionQuery = new QueryBuilder($query->getConnection());
        $versionQuery->select(EntityDefinitionQueryHelper::escape($subRoot) . '.*');
        $versionQuery->from(
            EntityDefinitionQueryHelper::escape($subRoot),
            EntityDefinitionQueryHelper::escape($subRoot)
        );
        $queryHelper->joinVersion($versionQuery, $field->getReferenceClass(), $subRoot, $context);

        return $versionQuery;
    }
}
