<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Dbal\FieldResolver;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DataAbstractionLayerException;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityDefinitionQueryHelper;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\JoinGroup;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\QueryBuilder;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\AssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Inherited;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ReverseInherited;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Search\CriteriaPartInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\AndFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\Filter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\OrFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\SingleFieldFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Parser\SqlQueryParser;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal This class is not intended for service decoration
 */
#[Package('framework')]
class CriteriaPartResolver
{
    public function __construct(
        private readonly Connection $connection,
        private readonly SqlQueryParser $parser
    ) {
    }

    /**
     * @param array<CriteriaPartInterface> $parts
     */
    public function resolve(array $parts, EntityDefinition $definition, QueryBuilder $query, Context $context): void
    {
        foreach ($parts as $part) {
            if ($part instanceof JoinGroup) {
                $this->resolveJoinGroup($part, $definition, $query, $context);

                continue;
            }

            foreach ($part->getFields() as $accessor) {
                if ($accessor === '_score') {
                    continue;
                }
                $this->resolveField($part, $accessor, $definition, $query, $context);
            }
        }
    }

    private function resolveJoinGroup(JoinGroup $group, EntityDefinition $definition, QueryBuilder $query, Context $context): void
    {
        $fields = EntityDefinitionQueryHelper::getFieldsOfAccessor($definition, $group->getPath(), false);

        $first = array_shift($fields);

        if (!$first instanceof AssociationField) {
            throw DataAbstractionLayerException::expectedAssociationFieldInFirstLevelOfJoinGroup($first ? $first::class : null);
        }

        $nestedQuery = $this->createNestedQuery($first, $definition, $context);

        foreach ($group->getFields() as $accessor) {
            if ($accessor === '_score') {
                continue;
            }
            $this->resolveField($group, $accessor, $definition, $nestedQuery, $context);
        }

        $this->parseAndResolveFilters($group, $definition, $nestedQuery, $context);

        foreach ($nestedQuery->getParameters() as $key => $value) {
            $type = $nestedQuery->getParameterType($key);
            $query->setParameter($key, $value, $type);
        }
    }

    private function parseAndResolveFilters(JoinGroup $group, EntityDefinition $definition, QueryBuilder $subQuery, Context $context): void
    {
        $filter = new AndFilter($group->getQueries());
        if ($group->getOperator() === MultiFilter::CONNECTION_OR) {
            $filter = new OrFilter($group->getQueries());
        }

        $parsed = $this->parser->parse($filter, $definition, $context);
        if (empty($parsed->getWheres())) {
            return;
        }

        foreach ($parsed->getParameters() as $key => $value) {
            $subQuery->setParameter($key, $value, $parsed->getType($key));
        }

        $subQuery->andWhere(implode(' AND ', $parsed->getWheres()));

        $singleFieldFilters = array_filter($filter->getQueries(), fn (Filter $filter): bool => $filter instanceof SingleFieldFilter);
        if (empty($singleFieldFilters)) {
            return;
        }

        // We generate an EXISTS condition using our correlated subquery to avoid joining any table multiple
        // times (even though it might be filtered). This avoids possible exponential join explosions. In some cases,
        // MySQL/MariaDB will perform a semi-join optimization on these EXISTS conditions (see https://dev.mysql.com/doc/refman/8.0/en/semijoins.html).
        // Since all single field filters will just be combined using AND/OR operators on the same level, we only
        // need to include our EXISTS sub query for one of these filters. The where conditions actually representing
        // these filters have been added to the subquery above.
        array_shift($singleFieldFilters)->setResolved('EXISTS (' . $subQuery->getSQL() . ')');

        // All other filters will be resolved to a constant value of FALSE or TRUE, depending on the operator used.
        // For example, if the root filter has 3 queries:
        // - ... and is an AND filter, we will generate `WHERE EXISTS(...) AND TRUE AND TRUE`
        // - ... and is an OR filter, we will generate `WHERE EXISTS(...) OR FALSE OR FALSE`
        // Simply, this no-op value puts the filters in a resolved state but is chosen in a way that it will not affect
        // the result.
        $noOpValue = $group->getOperator() === MultiFilter::CONNECTION_OR ? 'FALSE' : 'TRUE';
        foreach ($singleFieldFilters as $singleFieldFilter) {
            $singleFieldFilter->setResolved($noOpValue);
        }
    }

    private function createNestedQuery(AssociationField $field, EntityDefinition $definition, Context $context): QueryBuilder
    {
        $query = new QueryBuilder($this->connection);

        if ($field instanceof OneToManyAssociationField) {
            $reference = $field->getReferenceDefinition();
            $alias = $definition->getEntityName() . '.' . $field->getPropertyName();

            // Since this query only acts as an EXISTS check, we select a dummy value that will cause the EXISTS to be true.
            $query->addSelect('1');
            $query->from(self::escape($reference->getEntityName()), self::escape($alias));
            $query->addState($alias);

            $parameters = [
                '#root#' => self::escape($definition->getEntityName()),
                '#source_column#' => $this->getSourceColumn($field, $context),
                '#alias#' => self::escape($alias),
                '#reference_column#' => self::escape($field->getReferenceField()),
            ];

            $query->andWhere(str_replace(
                array_keys($parameters),
                array_values($parameters),
                '#root#.#source_column# = #alias#.#reference_column#' . $this->buildVersionWhere($definition, $field),
            ));

            return $query;
        }

        if ($field instanceof ManyToOneAssociationField || $field instanceof OneToOneAssociationField) {
            $reference = $field->getReferenceDefinition();
            $alias = $definition->getEntityName() . '.' . $field->getPropertyName();

            // Since this query only acts as an EXISTS check, we select a dummy value that will cause the EXISTS to be true.
            $query->addSelect('1');
            $query->from(self::escape($reference->getEntityName()), self::escape($alias));
            $query->addState($alias);

            $versionWhere = '';
            if ($reference->isVersionAware()) {
                $aliasVersionId = $this->getVersionIdFieldForManyToOneRelation($reference, $definition);
                $rootVersionId = $this->getVersionIdFieldForManyToOneRelation($definition, $reference);

                $versionWhere = ' AND #root#.`' . $rootVersionId . '` = #alias#.`' . $aliasVersionId . '`';
            }

            $parameters = [
                '#root#' => self::escape($definition->getEntityName()),
                '#source_column#' => $this->getSourceColumn($field, $context),
                '#alias#' => self::escape($alias),
                '#reference_column#' => self::escape($field->getReferenceField()),
            ];

            $query->andWhere(str_replace(
                array_keys($parameters),
                array_values($parameters),
                '#root#.#source_column# = #alias#.#reference_column#' . $versionWhere,
            ));

            return $query;
        }

        if (!$field instanceof ManyToManyAssociationField) {
            throw DataAbstractionLayerException::unexpectedAssociationFieldClass($field::class);
        }

        $reference = $field->getReferenceDefinition();

        $mappingAlias = $definition->getEntityName() . '.' . $field->getPropertyName() . '.mapping';
        $alias = $definition->getEntityName() . '.' . $field->getPropertyName();

        // Since this query only acts as an EXISTS check, we select a dummy value that will cause the EXISTS to be true.
        $query->addSelect('1');
        $query->from(self::escape($reference->getEntityName()), self::escape($mappingAlias));
        $query->addState($alias);

        $parameters = [
            '#mapping#' => self::escape($mappingAlias),
            '#source_column#' => self::escape($field->getMappingReferenceColumn()),
            '#alias#' => self::escape($alias),
            '#reference_column#' => $this->getReferenceColumn($context, $field),
        ];

        $query->leftJoin(
            self::escape($mappingAlias),
            self::escape($field->getToManyReferenceDefinition()->getEntityName()),
            self::escape($alias),
            str_replace(
                array_keys($parameters),
                array_values($parameters),
                '#mapping#.#source_column# = #alias#.#reference_column# '
                . $this->buildMappingVersionWhere($field->getToManyReferenceDefinition(), $field)
            )
        );

        $parameters = [
            '#alias#' => self::escape($definition->getEntityName()),
            '#source_column#' => $this->getSourceColumn($field, $context),
            '#mapping#' => self::escape($mappingAlias),
            '#reference_column#' => self::escape($field->getMappingLocalColumn()),
        ];

        $query->andWhere(str_replace(
            array_keys($parameters),
            array_values($parameters),
            '#alias#.#source_column# = #mapping#.#reference_column#' . $this->buildMappingVersionWhere($definition, $field),
        ));

        return $query;
    }

    private function resolveField(CriteriaPartInterface $criteriaPart, string $accessor, EntityDefinition $definition, QueryBuilder $query, Context $context): void
    {
        $accessor = str_replace('extensions.', '', $accessor);

        $root = $definition->getEntityName();

        $parts = explode('.', $accessor);

        if ($parts[0] === $root) {
            unset($parts[0]);
        }

        $alias = $root;

        $path = [$root];

        $rootDefinition = $definition;

        foreach ($parts as $part) {
            $field = $definition->getFields()->get($part);

            if ($field === null) {
                return;
            }

            $resolver = $field->getResolver();
            if ($resolver === null) {
                continue;
            }

            if ($field instanceof AssociationField) {
                $path[] = $field->getPropertyName();
            }

            $currentPath = implode('.', $path);
            $resolverContext = new FieldResolverContext($currentPath, $alias, $field, $definition, $rootDefinition, $query, $context, $criteriaPart);

            $alias = $this->callResolver($resolverContext);

            if (!$field instanceof AssociationField) {
                return;
            }

            $definition = $field->getReferenceDefinition();
            if ($field instanceof ManyToManyAssociationField) {
                $definition = $field->getToManyReferenceDefinition();
            }

            $parent = $definition->getField('parent');
            if ($parent && $definition->isInheritanceAware() && $context->considerInheritance()) {
                $resolverContext = new FieldResolverContext($currentPath, $alias, $parent, $definition, $rootDefinition, $query, $context, $criteriaPart);

                $this->callResolver($resolverContext);
            }
        }
    }

    private function callResolver(FieldResolverContext $context): string
    {
        $resolver = $context->getField()->getResolver();

        if (!$resolver) {
            return $context->getAlias();
        }

        return $resolver->join($context);
    }

    private function getReferenceColumn(Context $context, ManyToManyAssociationField $field): string
    {
        if (!$field->is(ReverseInherited::class)) {
            return self::escape($field->getReferenceField());
        }

        if (!$context->considerInheritance()) {
            return self::escape($field->getReferenceField());
        }

        return self::escape($field->getFlag(ReverseInherited::class)->getReversedPropertyName());
    }

    private function buildMappingVersionWhere(EntityDefinition $definition, AssociationField $field): string
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

    private function buildVersionWhere(EntityDefinition $definition, AssociationField $field): string
    {
        if (!$definition->isVersionAware()) {
            return '';
        }
        if (!$field->is(CascadeDelete::class)) {
            return '';
        }

        $versionField = '`' . $definition->getEntityName() . '_version_id`';

        return ' AND #root#.`version_id` = #alias#.' . $versionField;
    }

    private static function escape(string $string): string
    {
        return EntityDefinitionQueryHelper::escape($string);
    }

    private function getSourceColumn(AssociationField $association, Context $context): string
    {
        if ($association->is(Inherited::class) && $context->considerInheritance()) {
            return EntityDefinitionQueryHelper::escape($association->getPropertyName());
        }

        if ($association instanceof ManyToOneAssociationField || $association instanceof OneToOneAssociationField) {
            return EntityDefinitionQueryHelper::escape($association->getStorageName());
        }

        if ($association instanceof OneToManyAssociationField) {
            return EntityDefinitionQueryHelper::escape($association->getLocalField());
        }

        if ($association instanceof ManyToManyAssociationField) {
            return EntityDefinitionQueryHelper::escape($association->getLocalField());
        }

        throw DataAbstractionLayerException::unexpectedAssociationFieldClass($association::class);
    }

    private function getVersionIdFieldForManyToOneRelation(EntityDefinition $definition, EntityDefinition $reference): string
    {
        $rootVersionId = 'version_id';
        // it could be the case that we have a reverse join and the reference is the "parent" definition
        if ($definition->getFields()->getByStorageName($reference->getEntityName() . '_version_id')) {
            $rootVersionId = $reference->getEntityName() . '_version_id';
        }

        return $rootVersionId;
    }
}
