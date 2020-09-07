<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Dbal\JoinBuilder;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityDefinitionQueryHelper;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\QueryBuilder;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\AssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Inherited;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ReverseInherited;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;

class AntiJoinBuilder implements JoinBuilderInterface
{
    /**
     * @param AntiJoinInfo $antiJoinInfo
     */
    public function join(
        EntityDefinition $definition,
        string $joinType,
        $antiJoinInfo,
        string $on,
        string $alias,
        QueryBuilder $parentQueryBuilder,
        Context $context
    ): void {
        if (!$antiJoinInfo instanceof AntiJoinInfo) {
            throw new \InvalidArgumentException('Expected $antiJoinInfo to be ' . AntiJoinInfo::class);
        }

        $builder = clone $parentQueryBuilder;
        $builder->resetQueryParts();

        $associations = $antiJoinInfo->getAssociations();

        /** @var AssociationField $firstAssociation */
        $firstAssociation = array_shift($associations);
        if ($firstAssociation instanceof ManyToManyAssociationField) {
            $mapping = $firstAssociation->getMappingDefinition();
            $mappingAlias = $on . '.' . $firstAssociation->getPropertyName() . '.mapping';

            $builder->addSelect(EntityDefinitionQueryHelper::escape($mappingAlias) . '.' . EntityDefinitionQueryHelper::escape($firstAssociation->getMappingLocalColumn()));

            if ($definition->isVersionAware() && $firstAssociation->is(CascadeDelete::class)) {
                $builder->addSelect(EntityDefinitionQueryHelper::escape($mappingAlias) . '.' . EntityDefinitionQueryHelper::escape($definition->getEntityName() . '_version_id'));
            }

            $builder->from(
                EntityDefinitionQueryHelper::escape($mapping->getEntityName()),
                EntityDefinitionQueryHelper::escape($mappingAlias)
            );

            $reference = $firstAssociation->getToManyReferenceDefinition();
            $this->innerJoin($mappingAlias, $on, $firstAssociation, $reference, $builder, $context);
        } else {
            $reference = $firstAssociation->getReferenceDefinition();
            $entityName = $reference->getEntityName();
            $fromAlias = $on . '.' . $firstAssociation->getPropertyName();

            if ($firstAssociation instanceof AssociationField) {
                $builder->addSelect(EntityDefinitionQueryHelper::escape($fromAlias) . '.' . EntityDefinitionQueryHelper::escape($firstAssociation->getReferenceField()));

                if ($definition->isVersionAware() && $firstAssociation->is(CascadeDelete::class)) {
                    $builder->addSelect(EntityDefinitionQueryHelper::escape($fromAlias) . '.' . EntityDefinitionQueryHelper::escape($definition->getEntityName() . '_version_id'));
                }
            }

            $builder->from(
                EntityDefinitionQueryHelper::escape($entityName),
                EntityDefinitionQueryHelper::escape($fromAlias)
            );
        }

        foreach ($antiJoinInfo->getAdditionalSelects() as $selectField) {
            $builder->addSelect($selectField);
        }

        foreach ($associations as $subRoot => $association) {
            $resolver = $association->getResolver();
            if (!$resolver) {
                throw new \RuntimeException('Resolver not found');
            }

            $subAlias = $subRoot . '.' . $association->getPropertyName();
            $resolver->getJoinBuilder()->join(
                $reference,
                JoinBuilderInterface::INNER_JOIN,
                $association,
                $subRoot,
                $subAlias,
                $builder,
                $context
            );
            $reference = $association instanceof ManyToManyAssociationField
                ? $association->getToManyReferenceDefinition()
                : $association->getReferenceDefinition();
        }

        $builder->andWhere($antiJoinInfo->getCondition());

        $subQuery = $builder->getSQL();
        $paramTypes = $builder->getParameterTypes();
        foreach ($builder->getParameters() as $key => $value) {
            $parentQueryBuilder->setParameter($key, $value, $paramTypes[$key] ?? null);
        }

        $versionJoinCondition = '';
        if ($definition->isVersionAware() && $firstAssociation->is(CascadeDelete::class)) {
            $versionField = $definition->getEntityName() . '_version_id';
            $versionJoinCondition = ' AND #root#.version_id = #alias#.' . $versionField;
        }

        $source = $this->getSource($definition, $firstAssociation, $on, $context);

        if ($firstAssociation instanceof ManyToManyAssociationField) {
            $referenceColumn = $firstAssociation->getMappingLocalColumn();
        } else {
            $referenceColumn = $firstAssociation->getReferenceField();
        }

        $parameters = [
            '#root#' => EntityDefinitionQueryHelper::escape($on),
            '#source#' => $source,
            '#alias#' => EntityDefinitionQueryHelper::escape($alias),
            '#reference_column#' => EntityDefinitionQueryHelper::escape($referenceColumn),
        ];

        if ($joinType === JoinBuilderInterface::INNER_JOIN) {
            $parentQueryBuilder->innerJoin(
                EntityDefinitionQueryHelper::escape($on),
                '(' . $subQuery . ')',
                EntityDefinitionQueryHelper::escape($alias),
                str_replace(
                    array_keys($parameters),
                    array_values($parameters),
                    '#source# = #alias#.#reference_column# ' . $versionJoinCondition
                )
            );
        } else {
            $parentQueryBuilder->leftJoin(
                EntityDefinitionQueryHelper::escape($on),
                '(' . $subQuery . ')',
                EntityDefinitionQueryHelper::escape($alias),
                str_replace(
                    array_keys($parameters),
                    array_values($parameters),
                    '#source# = #alias#.#reference_column# ' . $versionJoinCondition
                )
            );
        }
        // TODO: add inheritance support
    }

    private function getSource(EntityDefinition $definition, AssociationField $field, string $on, Context $context): string
    {
        if ($field instanceof ManyToManyAssociationField) {
            if ($field->is(Inherited::class) && $context->considerInheritance()) {
                return EntityDefinitionQueryHelper::escape($on) . '.' . EntityDefinitionQueryHelper::escape($field->getPropertyName());
            }

            return EntityDefinitionQueryHelper::escape($on) . '.' . EntityDefinitionQueryHelper::escape($field->getLocalField());
        }

        if ($field instanceof OneToManyAssociationField) {
            if ($field->is(Inherited::class) && $context->considerInheritance()) {
                return EntityDefinitionQueryHelper::escape($on) . '.' . EntityDefinitionQueryHelper::escape($field->getPropertyName());
            }

            return EntityDefinitionQueryHelper::escape($on) . '.' . EntityDefinitionQueryHelper::escape($field->getLocalField());
        }

        if ($field instanceof ManyToOneAssociationField || $field instanceof OneToOneAssociationField) {
            $source = EntityDefinitionQueryHelper::escape($on) . '.' . EntityDefinitionQueryHelper::escape($field->getStorageName());
            if (!$field->is(Inherited::class) || !$context->considerInheritance()) {
                return $source;
            }
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

            return $inherited;
        }

        throw new \RuntimeException(sprintf('Unexpected field in %s::%s given', self::class, __METHOD__));
    }

    private function innerJoin(string $joinAlias, string $root, ManyToManyAssociationField $association, EntityDefinition $referenceDefinition, QueryBuilder $builder, Context $context): void
    {
        $table = $referenceDefinition->getEntityName();
        $alias = $root . '.' . $association->getPropertyName();

        $referenceColumn = EntityDefinitionQueryHelper::escape($association->getReferenceField());

        if ($association->is(ReverseInherited::class) && $context->considerInheritance()) {
            /** @var ReverseInherited $flag */
            $flag = $association->getFlag(ReverseInherited::class);

            $referenceColumn = EntityDefinitionQueryHelper::escape($flag->getReversedPropertyName());
        }

        $versionJoinCondition = '';
        if ($referenceDefinition->isVersionAware() && $association->is(CascadeDelete::class)) {
            $versionField = '`' . $referenceDefinition->getEntityName() . '_version_id`';
            $versionJoinCondition = ' AND #alias#.`version_id` = #mapping#.' . $versionField;
        }

        $parameters = [
            '#mapping#' => EntityDefinitionQueryHelper::escape($joinAlias),
            '#source_column#' => EntityDefinitionQueryHelper::escape($association->getMappingReferenceColumn()),
            '#alias#' => EntityDefinitionQueryHelper::escape($alias),
            '#reference_column#' => $referenceColumn,
        ];

        $builder->innerJoin(
            EntityDefinitionQueryHelper::escape($joinAlias),
            EntityDefinitionQueryHelper::escape($table),
            EntityDefinitionQueryHelper::escape($alias),
            str_replace(
                array_keys($parameters),
                array_values($parameters),
                '#mapping#.#source_column# = #alias#.#reference_column# ' . $versionJoinCondition
            )
        );
    }
}
