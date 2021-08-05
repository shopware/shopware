<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Dbal\FieldResolver;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Context;
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
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\OrFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Parser\SqlQueryParser;

/**
 * @internal This class is not intended for service decoration
 */
class CriteriaPartResolver
{
    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var SqlQueryParser
     */
    private $parser;

    public function __construct(Connection $connection, SqlQueryParser $parser)
    {
        $this->connection = $connection;
        $this->parser = $parser;
    }

    public function resolve(array $parts, EntityDefinition $definition, QueryBuilder $query, Context $context): void
    {
        /** @var CriteriaPartInterface $part */
        foreach ($parts as $part) {
            if ($part instanceof JoinGroup) {
                $this->resolveSubJoin($part, $definition, $query, $context);

                $query->addState(EntityDefinitionQueryHelper::HAS_TO_MANY_JOIN);

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

    private function resolveSubJoin(JoinGroup $group, EntityDefinition $definition, QueryBuilder $query, Context $context): void
    {
        $fields = EntityDefinitionQueryHelper::getFieldsOfAccessor($definition, $group->getPath(), false);

        $first = array_shift($fields);

        if (!$first instanceof AssociationField) {
            throw new \RuntimeException('Expect association field in first level of join group');
        }

        $nested = $this->createNestedQuery($first, $definition, $context);

        foreach ($group->getFields() as $accessor) {
            if ($accessor === '_score') {
                continue;
            }
            $this->resolveField($group, $accessor, $definition, $nested, $context);
        }

        $alias = $definition->getEntityName() . '.' . $first->getPropertyName() . $group->getSuffix();

        $this->parseFilter($group, $definition, $nested, $context, $alias);

        $parameters = [
            '#root#' => self::escape($definition->getEntityName()),
            '#source_column#' => $this->getSourceColumn($first, $context),
            '#alias#' => self::escape($alias),
        ];

        $query->leftJoin(
            self::escape($definition->getEntityName()),
            '(' . $nested->getSQL() . ')',
            self::escape($alias),
            str_replace(
                array_keys($parameters),
                array_values($parameters),
                '#root#.#source_column# = #alias#.`id`'
                . $this->buildVersionWhere($definition, $first)
            )
        );

        foreach ($nested->getParameters() as $key => $value) {
            $type = $nested->getParameterType($key);
            $query->setParameter($key, $value, $type);
        }
    }

    private function parseFilter(JoinGroup $group, EntityDefinition $definition, QueryBuilder $query, Context $context, string $alias): void
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
            $query->setParameter($key, $value, $parsed->getType($key));
        }

        foreach ($filter->getQueries() as $filter) {
            $filter->setResolved(self::escape($alias) . '.id IS NOT NULL');
        }

        $query->andWhere(implode(' AND ', $parsed->getWheres()));
    }

    private function createNestedQuery(AssociationField $field, EntityDefinition $definition, Context $context): QueryBuilder
    {
        $query = new QueryBuilder($this->connection);

        if ($field instanceof OneToManyAssociationField) {
            $reference = $field->getReferenceDefinition();
            $alias = $definition->getEntityName() . '.' . $field->getPropertyName();

            $query->addSelect(self::accessor($alias, $field->getReferenceField()) . ' as id');
            if ($definition->isVersionAware()) {
                $query->addSelect(self::accessor($alias, $definition->getEntityName() . '_version_id'));
            }

            $query->from(self::escape($reference->getEntityName()), self::escape($alias));
            $query->addState($alias);

            return $query;
        }

        if ($field instanceof ManyToOneAssociationField || $field instanceof OneToOneAssociationField) {
            $reference = $field->getReferenceDefinition();
            $alias = $definition->getEntityName() . '.' . $field->getPropertyName();

            $query->addSelect(self::accessor($alias, $field->getReferenceField()) . ' as id');
            if ($reference->isVersionAware()) {
                $query->addSelect(self::accessor($alias, $definition->getEntityName() . '_version_id'));
            }

            $query->from(self::escape($reference->getEntityName()), self::escape($alias));
            $query->addState($alias);

            return $query;
        }

        if (!$field instanceof ManyToManyAssociationField) {
            throw new \RuntimeException(sprintf('Unknown association class provided %s', \get_class($field)));
        }

        $reference = $field->getReferenceDefinition();

        $mappingAlias = $definition->getEntityName() . '.' . $field->getPropertyName() . '.mapping';
        $alias = $definition->getEntityName() . '.' . $field->getPropertyName();

        $query->addSelect(self::accessor($mappingAlias, $field->getMappingLocalColumn()) . ' as id');
        if ($definition->isVersionAware()) {
            $query->addSelect(self::accessor($mappingAlias, $definition->getEntityName() . '_version_id'));
        }

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

        return $query;
    }

    private function resolveField(CriteriaPartInterface $criteriaPart, string $accessor, EntityDefinition $definition, QueryBuilder $query, Context $context): void
    {
        $accessor = str_replace('extensions.', '', $accessor);

        $root = $definition->getEntityName();

        $parts = explode('.', $accessor);
        if (empty($parts)) {
            return;
        }

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

        /** @var ReverseInherited $flag */
        $flag = $field->getFlag(ReverseInherited::class);

        return self::escape($flag->getReversedPropertyName());
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

    private static function accessor(string $alias, string $field): string
    {
        return self::escape($alias) . '.' . self::escape($field);
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

        throw new \RuntimeException(sprintf('Unknown association class provided %s', \get_class($association)));
    }
}
