<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Dbal;

use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\AssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\Filter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\SingleFieldFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('framework')]
class JoinGroupBuilder
{
    private const NOT_RELEVANT = 'not-relevant';

    /**
     * For the sql implementation of the DAL, we have to detect how often we have to join an association.
     * This function groups the provided filters. For each generated `JoinGroup`, the sql implementation will
     * create an additional join to the association with the contained filters of the `JoinGroup`.
     *
     * This function follows the following logic
     * - Filters will be analyzed recursive
     * - Filters inside a `multi-filter` will be grouped together
     * - A `JoinGroup` is generated when a to-many association is filtered with a `not-filter`
     * - A `JoinGroup` is generated when a to-many association is filtered by more than one `multi-filter`
     * - An "empty" filter will not lead to a join group (example `new EqualsFilter('product.tags.id', null)`)
     * - A `JoinGroup` is generated for every to-many association when `$spillToSubQueries` is set
     *
     * MySQL and MariaDB can only reference 61 tables per join. A `JoinGroup` is
     * resolved as a correlated `EXISTS` sub query, which is a query block of its
     * own and therefore does not consume one of those 61 slots. `$spillToSubQueries`
     * is set by the query builder when a criteria would otherwise exceed the limit,
     * so a query that used to fail with error 1116 can be executed.
     *
     * @param list<Filter> $filters
     * @param list<string> $additionalFields
     * @param list<string> $keepJoined association paths that must keep a real join, because sortings or groupings reference them
     *
     * @return list<Filter>
     */
    public function group(array $filters, EntityDefinition $definition, array $additionalFields = [], bool $spillToSubQueries = false, array $keepJoined = []): array
    {
        $mapped = $this->recursion($filters, $definition, MultiFilter::CONNECTION_AND, false, $spillToSubQueries);

        $new = [];
        if (\array_key_exists(self::NOT_RELEVANT, $mapped)) {
            $new = $mapped[self::NOT_RELEVANT];
            unset($mapped[self::NOT_RELEVANT]);
        }

        $duplicates = $this->getDuplicates($mapped, $additionalFields);

        $level = 1;
        foreach ($mapped as $groups) {
            $operator = $groups['operator'];
            $negated = $groups['negated'];

            unset($groups['operator'], $groups['negated']);

            foreach ($groups as $path => $groupFilters) {
                $relevant = \in_array($path, $duplicates, true) || $negated;

                // The criteria does not fit into a single join, so every association
                // that is only referenced by filters is moved into a sub query.
                if ($spillToSubQueries && !\in_array($path, $keepJoined, true)) {
                    $relevant = true;
                }

                if (!$relevant) {
                    $new = array_merge($new, $groupFilters);

                    continue;
                }

                if (!\is_string($operator)) {
                    continue;
                }

                $new[] = new JoinGroup($groupFilters, $path, '_' . $level, $operator);
                ++$level;
            }
        }

        return $new;
    }

    /**
     * Returns the path to the first to-many association of the accessor, or null when it traverses none.
     */
    public static function findToManyPath(EntityDefinition $definition, string $accessor): ?string
    {
        $fields = EntityDefinitionQueryHelper::getFieldsOfAccessor($definition, $accessor, false);

        // contains later the path to the first to many association
        $path = [$definition->getEntityName()];

        /** @var Field $field */
        foreach ($fields as $field) {
            if (!$field instanceof AssociationField) {
                break;
            }

            // if to many not already detected, continue with path building
            $path[] = $field->getPropertyName();

            if ($field instanceof ManyToManyAssociationField || $field instanceof OneToManyAssociationField) {
                return implode('.', $path);
            }
        }

        return null;
    }

    /**
     * @param array<Filter> $filters
     *
     * @return array<string, mixed> Returned array shape looks like this:
     *                              array<string(random-uuid), array{self::NOT_RELEVANT?: list<Filter>, operator: MultiFilter::CONNECTION_*, negated: bool, string(association-name): list<Filter>}>
     *                              `association-name` is different for each call, but such array shape could not be handled by PHPStan, see https://github.com/phpstan/phpstan/issues/8438
     */
    private function recursion(array $filters, EntityDefinition $definition, string $operator, bool $negated, bool $spillToSubQueries): array
    {
        $mapped = [];

        // for each nesting level we need an own group to keep the mathematical logic
        $prefix = Uuid::randomHex();

        foreach ($filters as $filter) {
            if ($filter instanceof MultiFilter) {
                $nested = $this->recursion($filter->getQueries(), $definition, $filter->getOperator(), $filter instanceof NotFilter || $negated, $spillToSubQueries);
                $mapped = array_merge_recursive($mapped, $nested);

                continue;
            }

            if (!$filter instanceof SingleFieldFilter) {
                // this case should never happen, because all core filters are an instead of SingleFieldFilter or MultiFilter
                $mapped[self::NOT_RELEVANT][] = $filter;

                continue;
            }

            // find the first to many association path
            $association = $this->findToManyPathOfFilter($filter, $definition);
            if ($association === null) {
                // filters which not point to a to-many association are not relevant
                $mapped[self::NOT_RELEVANT][] = $filter;

                continue;
            }

            // checks if the current filter should check if the records has entries for the to many association
            if ($this->isEmptyFilter($filter, $spillToSubQueries)) {
                $mapped[self::NOT_RELEVANT][] = $filter;

                continue;
            }

            $mapped[$prefix][$association][] = $filter;
        }

        if (isset($mapped[$prefix])) {
            $mapped[$prefix]['operator'] = $operator;
            $mapped[$prefix]['negated'] = $negated;
        }

        return $mapped;
    }

    private function findToManyPathOfFilter(SingleFieldFilter $filter, EntityDefinition $definition): ?string
    {
        $fields = EntityDefinitionQueryHelper::getFieldsOfAccessor($definition, $filter->getField(), false);

        if ($fields === []) {
            return null;
        }

        $path = self::findToManyPath($definition, $filter->getField());

        $field = array_pop($fields);

        $filter->setIsPrimary($field->is(PrimaryKey::class));

        return $path;
    }

    private function isEmptyFilter(SingleFieldFilter $filter, bool $spillToSubQueries): bool
    {
        if (!$filter instanceof EqualsFilter) {
            return false;
        }

        if ($filter->getValue() !== null) {
            return false;
        }

        // A null check has to keep its left join. Inside an `EXISTS` sub query it
        // would stop matching the records that have no associated row at all,
        // which is exactly what such a filter looks for.
        return $spillToSubQueries || $filter->isPrimary();
    }

    /**
     * @param array<string, array<string, mixed>> $mapped
     * @param list<string> $fields
     *
     * @return list<string>
     */
    private function getDuplicates(array $mapped, array $fields): array
    {
        $paths = $fields;
        foreach ($mapped as $groups) {
            unset($groups['operator'], $groups['negated']);

            $paths = [...$paths, ...array_keys($groups)];
        }
        $duplicates = array_count_values($paths);

        $duplicates = array_filter($duplicates, static fn (int $count) => $count > 1);

        return array_keys($duplicates);
    }
}
