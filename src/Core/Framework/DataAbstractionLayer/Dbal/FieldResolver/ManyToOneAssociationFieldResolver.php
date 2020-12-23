<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Dbal\FieldResolver;

use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityDefinitionQueryHelper;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\JoinBuilder\JoinBuilderInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\QueryBuilder;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\AssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Inherited;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ReverseInherited;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;

/**
 * @internal
 */
class ManyToOneAssociationFieldResolver extends AbstractFieldResolver implements FieldResolverInterface
{
    /**
     * @deprecated tag:v6.4.0 - Will be removed
     *
     * @var JoinBuilderInterface
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
        // @deprecated tag:v6.4.0 - Will be removed
        $this->joinBuilder = $joinBuilder;
        $this->queryHelper = $queryHelper;
    }

    /**
     * @deprecated tag:v6.4.0 - Will be removed
     */
    public function getJoinBuilder(): JoinBuilderInterface
    {
        return $this->joinBuilder;
    }

    public function join(FieldResolverContext $context): string
    {
        $field = $context->getField();
        if (!$field instanceof ManyToOneAssociationField && !$field instanceof OneToOneAssociationField) {
            return $context->getAlias();
        }

        $alias = $context->getAlias() . '.' . $field->getPropertyName();
        if ($context->getQuery()->hasState($alias)) {
            return $alias;
        }
        $context->getQuery()->addState($alias);

        $reference = $field->getReferenceDefinition();

        $table = $reference->getEntityName();

        $versionAware = $context->getDefinition()->isVersionAware() && $reference->isVersionAware();

        $source = $this->getSourceColumn($context->getDefinition(), $field, $context->getAlias(), $context->getContext());

        $referenceColumn = $this->getReferenceColumn($field, $context->getContext());

        //specified version requested, use sub version call to solve live version or specified
        if ($versionAware && $context->getContext()->getVersionId() !== Defaults::LIVE_VERSION) {
            $this->joinVersion($field, $context->getAlias(), $alias, $context->getQuery(), $context->getContext(), $source, $referenceColumn);

            return $alias;
        }

        //No Blacklisting Whitelisting for ManyToOne Association because of possible Dependencies on subentities
        $parameters = [
            '#source#' => $source,
            '#root#' => EntityDefinitionQueryHelper::escape($context->getAlias()),
            '#alias#' => EntityDefinitionQueryHelper::escape($alias),
            '#reference_column#' => $referenceColumn,
        ];

        $context->getQuery()->leftJoin(
            EntityDefinitionQueryHelper::escape($context->getAlias()),
            EntityDefinitionQueryHelper::escape($table),
            EntityDefinitionQueryHelper::escape($alias),
            str_replace(
                array_keys($parameters),
                array_values($parameters),
                '#source# = #alias#.#reference_column#' . $this->buildWhere($field, $context)
            )
        );

        return $alias;
    }

    /**
     * @deprecated tag:v6.4.0 - Will be removed
     */
    public function resolve(
        EntityDefinition $definition,
        string $root,
        Field $field,
        QueryBuilder $query,
        Context $context,
        EntityDefinitionQueryHelper $queryHelper
    ): bool {
        if (!$field instanceof ManyToOneAssociationField && !$field instanceof OneToOneAssociationField) {
            return false;
        }

        $reference = $field->getReferenceDefinition();
        $alias = $root . '.' . $field->getPropertyName();
        if ($query->hasState($alias)) {
            return true;
        }
        $query->addState($alias);

        $this->getJoinBuilder()->join($definition, JoinBuilderInterface::LEFT_JOIN, $field, $root, $alias, $query, $context);

        if ($definition === $reference) {
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

    /**
     * @internal Overwritten in parent association field resolver to handle join filters
     */
    protected function getSourceColumn(EntityDefinition $definition, AssociationField $field, string $root, Context $context): string
    {
        if (!$field instanceof ManyToOneAssociationField && !$field instanceof OneToOneAssociationField) {
            throw new \RuntimeException('Expected field of type ManyToOneAssociationField or OneToOneAssociationField');
        }

        if (!$field->is(Inherited::class)) {
            return EntityDefinitionQueryHelper::escape($root) . '.' . EntityDefinitionQueryHelper::escape($field->getStorageName());
        }

        if (!$context->considerInheritance()) {
            return EntityDefinitionQueryHelper::escape($root) . '.' . EntityDefinitionQueryHelper::escape($field->getStorageName());
        }

        $inherited = EntityDefinitionQueryHelper::escape($root) . '.' . EntityDefinitionQueryHelper::escape($field->getPropertyName());

        $fk = $definition->getFields()->getByStorageName($field->getStorageName());
        if ($fk && $fk->is(Required::class)) {
            $parent = $root . '.parent';

            $inherited = sprintf(
                'IFNULL(%s, %s)',
                EntityDefinitionQueryHelper::escape($root) . '.' . EntityDefinitionQueryHelper::escape($field->getStorageName()),
                EntityDefinitionQueryHelper::escape($parent) . '.' . EntityDefinitionQueryHelper::escape($field->getStorageName())
            );
        }

        return $inherited;
    }

    /**
     * @internal Overwritten in parent association field resolver to add join conditions for inherited associations
     */
    protected function buildWhere(AssociationField $field, FieldResolverContext $context): string
    {
        $reference = $field->getReferenceDefinition();

        $versionAware = $context->getDefinition()->isVersionAware() && $reference->isVersionAware();

        if ($versionAware) {
            return ' AND #root#.`version_id` = #alias#.`version_id`';
        }

        return '';
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

    private function getReferenceColumn(AssociationField $field, Context $context): string
    {
        if ($field->is(ReverseInherited::class) && $context->considerInheritance()) {
            /** @var ReverseInherited $flag */
            $flag = $field->getFlag(ReverseInherited::class);

            return EntityDefinitionQueryHelper::escape($flag->getReversedPropertyName());
        }

        return EntityDefinitionQueryHelper::escape($field->getReferenceField());
    }

    private function joinVersion(AssociationField $field, string $root, string $alias, QueryBuilder $query, Context $context, string $source, string $referenceColumn): void
    {
        $versionQuery = $this->createSubVersionQuery($field, $query, $context, $this->queryHelper);

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
    }
}
