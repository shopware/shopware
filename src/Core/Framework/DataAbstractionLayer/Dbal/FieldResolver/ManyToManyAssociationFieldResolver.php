<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Dbal\FieldResolver;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityDefinitionQueryHelper;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\JoinBuilder\JoinBuilderInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\QueryBuilder;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Inherited;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ReverseInherited;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;

/**
 * @internal
 */
class ManyToManyAssociationFieldResolver extends AbstractFieldResolver implements FieldResolverInterface
{
    /**
     * @deprecated tag:v6.4.0 - will be removed
     */
    private $joinBuilder;

    /**
     * @var EntityDefinitionQueryHelper
     */
    private $queryHelper;

    public function __construct(
        JoinBuilderInterface $joinBuilder, // @deprecated tag:v6.4.0 - Will be removed
        EntityDefinitionQueryHelper $queryHelper
    ) {
        $this->joinBuilder = $joinBuilder;
        $this->queryHelper = $queryHelper;
    }

    /**
     * @deprecated tag:v6.4.0 - will be removed
     */
    public function getJoinBuilder(): JoinBuilderInterface
    {
        return $this->joinBuilder;
    }

    public function join(FieldResolverContext $context): string
    {
        $field = $context->getField();
        if (!$field instanceof ManyToManyAssociationField) {
            throw new \InvalidArgumentException('Expected ' . ManyToManyAssociationField::class);
        }

        $alias = $context->getAlias() . '.' . $field->getPropertyName();
        if ($context->getQuery()->hasState($alias)) {
            return $alias;
        }
        $context->getQuery()->addState($alias);
        $context->getQuery()->addState(EntityDefinitionQueryHelper::HAS_TO_MANY_JOIN);

        $mappingAlias = $alias . '.mapping';

        $source = $this->getMappingSourceColumn($context->getAlias(), $field, $context->getContext());

        $parameters = [
            '#root#' => EntityDefinitionQueryHelper::escape($context->getAlias()),
            '#source#' => $source,
            '#alias#' => EntityDefinitionQueryHelper::escape($mappingAlias),
            '#reference_column#' => EntityDefinitionQueryHelper::escape($field->getMappingLocalColumn()),
        ];

        $context->getQuery()->leftJoin(
            EntityDefinitionQueryHelper::escape($context->getAlias()),
            EntityDefinitionQueryHelper::escape($field->getMappingDefinition()->getEntityName()),
            EntityDefinitionQueryHelper::escape($mappingAlias),
            str_replace(
                array_keys($parameters),
                array_values($parameters),
                '#source# = #alias#.#reference_column# '
                . $this->buildMappingVersionWhere($field, $context->getDefinition())
            )
        );

        $parameters = [
            '#mapping#' => EntityDefinitionQueryHelper::escape($mappingAlias),
            '#source_column#' => EntityDefinitionQueryHelper::escape($field->getMappingReferenceColumn()),
            '#alias#' => EntityDefinitionQueryHelper::escape($alias),
            '#reference_column#' => $this->getReferenceColumn($context, $field),
        ];

        $context->getQuery()->leftJoin(
            EntityDefinitionQueryHelper::escape($mappingAlias),
            EntityDefinitionQueryHelper::escape($field->getToManyReferenceDefinition()->getEntityName()),
            EntityDefinitionQueryHelper::escape($alias),
            str_replace(
                array_keys($parameters),
                array_values($parameters),
                '#mapping#.#source_column# = #alias#.#reference_column# '
                . $this->buildVersionWhere($field->getToManyReferenceDefinition(), $field)
                . $this->buildRuleWhere($context, $field->getToManyReferenceDefinition(), $alias)
            )
        );

        return $alias;
    }

    /**
     * @deprecated tag:v6.4.0 - will be removed
     */
    public function resolve(
        EntityDefinition $definition,
        string $root,
        Field $field,
        QueryBuilder $query,
        Context $context,
        EntityDefinitionQueryHelper $queryHelper
    ): bool {
        if (!$field instanceof ManyToManyAssociationField) {
            return false;
        }

        $query->addState(EntityDefinitionQueryHelper::HAS_TO_MANY_JOIN);

        $alias = $root . '.' . $field->getPropertyName();
        if ($query->hasState($alias)) {
            return true;
        }
        $query->addState($alias);

        $this->getJoinBuilder()->join(
            $definition,
            JoinBuilderInterface::LEFT_JOIN,
            $field,
            $root,
            $alias,
            $query,
            $context
        );

        $reference = $field->getToManyReferenceDefinition();
        if ($definition->getClass() === $reference->getClass()) {
            return true;
        }

        if (!$reference->isInheritanceAware() || !$context->considerInheritance()) {
            return true;
        }

        /** @var ManyToOneAssociationField $parent */
        $parent = $reference->getFields()->get('parent');

        $queryHelper->resolveField($parent, $reference, $alias, $query, $context);

        return true;
    }

    private function buildMappingVersionWhere(ManyToManyAssociationField $association, EntityDefinition $definition): string
    {
        if (!$definition->isVersionAware()) {
            return '';
        }
        if (!$association->is(CascadeDelete::class)) {
            return '';
        }
        $versionField = $definition->getEntityName() . '_version_id';

        return ' AND #root#.`version_id` = #alias#.`' . $versionField . '`';
    }

    private function getMappingSourceColumn(string $root, ManyToManyAssociationField $association, Context $context): string
    {
        if ($association->is(Inherited::class) && $context->considerInheritance()) {
            return EntityDefinitionQueryHelper::escape($root) . '.' . EntityDefinitionQueryHelper::escape($association->getPropertyName());
        }

        return EntityDefinitionQueryHelper::escape($root) . '.' . EntityDefinitionQueryHelper::escape($association->getLocalField());
    }

    private function buildRuleWhere(FieldResolverContext $context, EntityDefinition $definition, string $alias): string
    {
        $ruleCondition = $this->queryHelper->buildRuleCondition($definition, $context->getQuery(), $alias, $context->getContext());

        if ($ruleCondition === null) {
            return '';
        }

        return ' AND ' . $ruleCondition;
    }

    private function getReferenceColumn(FieldResolverContext $context, ManyToManyAssociationField $field): string
    {
        if (!$field->is(ReverseInherited::class)) {
            return EntityDefinitionQueryHelper::escape($field->getReferenceField());
        }

        if (!$context->getContext()->considerInheritance()) {
            return EntityDefinitionQueryHelper::escape($field->getReferenceField());
        }

        /** @var ReverseInherited $flag */
        $flag = $field->getFlag(ReverseInherited::class);

        return EntityDefinitionQueryHelper::escape($flag->getReversedPropertyName());
    }

    private function buildVersionWhere(EntityDefinition $definition, ManyToManyAssociationField $field): string
    {
        if (!$definition->isVersionAware()) {
            return '';
        }
        if (!$field->is(CascadeDelete::class)) {
            return '';
        }

        $versionField = '`' . $definition->getEntityName() . '_version_id`';

        return ' AND #alias#.`version_id` = #mapping#.' . $versionField;
    }
}
