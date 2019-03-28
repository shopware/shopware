<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Dbal\FieldResolver;

use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityDefinitionQueryHelper;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\QueryBuilder;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\AssociationInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Inherited;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ReverseInherited;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;

class ManyToOneAssociationFieldResolver implements FieldResolverInterface
{
    public function resolve(
        string $definition,
        string $root,
        Field $field,
        QueryBuilder $query,
        Context $context,
        EntityDefinitionQueryHelper $queryHelper,
        bool $considerInheritance
    ): bool {
        if (!$field instanceof ManyToOneAssociationField && !$field instanceof OneToOneAssociationField) {
            return false;
        }

        /** @var EntityDefinition|string $reference */
        $reference = $field->getReferenceClass();
        $alias = $root . '.' . $field->getPropertyName();
        if ($query->hasState($alias)) {
            return true;
        }
        $query->addState($alias);

        $this->join($definition, $root, $field, $query, $context, $queryHelper, $considerInheritance);

        if ($definition === $reference) {
            return true;
        }

        if (!$reference::isInheritanceAware() || !$considerInheritance) {
            return true;
        }

        /** @var ManyToOneAssociationField $parent */
        $parent = $reference::getFields()->get('parent');

        $queryHelper->resolveField($parent, $reference, $alias, $query, $context, $considerInheritance);

        return true;
    }

    /**
     * @param EntityDefinition|string                                                 $definition
     * @param AssociationInterface|ManyToOneAssociationField|OneToOneAssociationField $field
     */
    private function join(
        string $definition,
        string $root,
        AssociationInterface $field,
        QueryBuilder $query,
        Context $context,
        EntityDefinitionQueryHelper $queryHelper,
        bool $considerInheritance
    ): void {
        if (!$field instanceof ManyToOneAssociationField && !$field instanceof OneToOneAssociationField) {
            return;
        }

        /** @var EntityDefinition|string $reference */
        $reference = $field->getReferenceClass();

        $table = $reference::getEntityName();

        $alias = $root . '.' . $field->getPropertyName();

        $versionAware = ($definition::isVersionAware() && $reference::isVersionAware());

        $source = EntityDefinitionQueryHelper::escape($root) . '.' . EntityDefinitionQueryHelper::escape($field->getStorageName());

        if ($field->is(Inherited::class) && $considerInheritance) {
            $inherited = EntityDefinitionQueryHelper::escape($root) . '.' . EntityDefinitionQueryHelper::escape($field->getPropertyName());

            $fk = $definition::getFields()->getByStorageName($field->getStorageName());
            if ($fk && $fk->is(Required::class)) {
                $parent = $root . '.parent';

                $inherited = sprintf(
                    'IFNULL(%s, %s)',
                    $source,
                    EntityDefinitionQueryHelper::escape($parent) . '.' . EntityDefinitionQueryHelper::escape($field->getStorageName())
                );
            }

            $source = $inherited;
        }

        $referenceColumn = EntityDefinitionQueryHelper::escape($field->getReferenceField());
        if ($field->is(ReverseInherited::class) && $considerInheritance) {
            /** @var ReverseInherited $flag */
            $flag = $field->getFlag(ReverseInherited::class);

            $referenceColumn = EntityDefinitionQueryHelper::escape($flag->getReversedPropertyName());
        }

        //specified version requested, use sub version call to solve live version or specified
        if ($versionAware && $context->getVersionId() !== Defaults::LIVE_VERSION) {
            $versionQuery = $this->createSubVersionQuery($field, $query, $context, $queryHelper);

            $parameters = [
                '#source#' => $source,
                '#root#' => EntityDefinitionQueryHelper::escape($root),
                '#alias#' => EntityDefinitionQueryHelper::escape($alias),
                '#reference_column#' => $referenceColumn,
            ];

            $query->leftJoin(
                EntityDefinitionQueryHelper::escape($root),
                '(' . $versionQuery->getSQL() . ')',
                EntityDefinitionQueryHelper::escape($alias),
                str_replace(
                    array_keys($parameters),
                    array_values($parameters),
                    '#source# = #alias#.#reference_column#'
                )
            );

            foreach ($versionQuery->getParameters() as $key => $value) {
                $query->setParameter($key, $value, $query->getParameterType($key));
            }

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
                    '#source# = #alias#.#reference_column# AND #root#.`version_id` = #alias#.`version_id`'
                )
            );

            return;
        }

        //No Blacklisting Whitelisting for ManyToOne Association because of possible Dependencies on subentities
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
                '#source# = #alias#.#reference_column#'
            )
        );
    }

    private function createSubVersionQuery(AssociationInterface $field, QueryBuilder $query, Context $context, EntityDefinitionQueryHelper $queryHelper): QueryBuilder
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
