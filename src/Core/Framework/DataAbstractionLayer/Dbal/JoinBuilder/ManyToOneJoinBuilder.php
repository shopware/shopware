<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Dbal\JoinBuilder;

use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityDefinitionQueryHelper;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\QueryBuilder;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\AssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Inherited;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ReverseInherited;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;

/**
 * @deprecated tag:v6.4.0 - Will be removed
 */
class ManyToOneJoinBuilder implements JoinBuilderInterface
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
        if (!$field instanceof ManyToOneAssociationField && !$field instanceof OneToOneAssociationField) {
            return;
        }

        $reference = $field->getReferenceDefinition();

        $table = $reference->getEntityName();

        $versionAware = $definition->isVersionAware() && $reference->isVersionAware();

        $source = EntityDefinitionQueryHelper::escape($on) . '.' . EntityDefinitionQueryHelper::escape($field->getStorageName());

        if ($field->is(Inherited::class) && $context->considerInheritance()) {
            $inherited = EntityDefinitionQueryHelper::escape($on) . '.' . EntityDefinitionQueryHelper::escape($field->getPropertyName());

            $fk = $definition->getFields()->getByStorageName($field->getStorageName());
            if ($fk && $fk->is(Required::class)) {
                $parent = $on . '.parent';

                $inherited = sprintf(
                    'IFNULL(%s, %s)',
                    $source,
                    EntityDefinitionQueryHelper::escape($parent) . '.' . EntityDefinitionQueryHelper::escape($field->getStorageName())
                );
            }

            $source = $inherited;
        }

        $referenceColumn = EntityDefinitionQueryHelper::escape($field->getReferenceField());
        if ($field->is(ReverseInherited::class) && $context->considerInheritance()) {
            /** @var ReverseInherited $flag */
            $flag = $field->getFlag(ReverseInherited::class);

            $referenceColumn = EntityDefinitionQueryHelper::escape($flag->getReversedPropertyName());
        }

        //specified version requested, use sub version call to solve live version or specified
        if ($versionAware && $context->getVersionId() !== Defaults::LIVE_VERSION) {
            $versionQuery = $this->createSubVersionQuery($field, $queryBuilder, $context, $this->queryHelper);

            $parameters = [
                '#source#' => $source,
                '#root#' => EntityDefinitionQueryHelper::escape($on),
                '#alias#' => EntityDefinitionQueryHelper::escape($alias),
                '#reference_column#' => $referenceColumn,
            ];

            $queryBuilder->leftJoin(
                EntityDefinitionQueryHelper::escape($on),
                '(' . $versionQuery->getSQL() . ')',
                EntityDefinitionQueryHelper::escape($alias),
                str_replace(
                    array_keys($parameters),
                    array_values($parameters),
                    '#source# = #alias#.#reference_column#'
                )
            );

            foreach ($versionQuery->getParameters() as $key => $value) {
                $queryBuilder->setParameter($key, $value, $queryBuilder->getParameterType($key));
            }

            return;
        }

        if ($versionAware) {
            $parameters = [
                '#source#' => $source,
                '#root#' => EntityDefinitionQueryHelper::escape($on),
                '#alias#' => EntityDefinitionQueryHelper::escape($alias),
                '#reference_column#' => EntityDefinitionQueryHelper::escape($field->getReferenceField()),
            ];

            $queryBuilder->leftJoin(
                EntityDefinitionQueryHelper::escape($on),
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
            '#root#' => EntityDefinitionQueryHelper::escape($on),
            '#alias#' => EntityDefinitionQueryHelper::escape($alias),
            '#reference_column#' => $referenceColumn,
        ];

        $queryBuilder->leftJoin(
            EntityDefinitionQueryHelper::escape($on),
            EntityDefinitionQueryHelper::escape($table),
            EntityDefinitionQueryHelper::escape($alias),
            str_replace(
                array_keys($parameters),
                array_values($parameters),
                '#source# = #alias#.#reference_column#'
            )
        );
    }

    private function createSubVersionQuery(AssociationField $field, QueryBuilder $queryBuilder, Context $context, EntityDefinitionQueryHelper $queryHelper): QueryBuilder
    {
        $subRoot = $field->getReferenceDefinition()->getEntityName();

        $versionQuery = new QueryBuilder($queryBuilder->getConnection());
        $versionQuery->select(EntityDefinitionQueryHelper::escape($subRoot) . '.*');
        $versionQuery->from(
            EntityDefinitionQueryHelper::escape($subRoot),
            EntityDefinitionQueryHelper::escape($subRoot)
        );
        $queryHelper->joinVersion($versionQuery, $field->getReferenceDefinition(), $subRoot, $context);

        return $versionQuery;
    }
}
