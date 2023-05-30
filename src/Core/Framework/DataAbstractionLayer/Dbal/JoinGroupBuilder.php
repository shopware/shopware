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
#[Package('core')]
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
     *
     * @param list<Filter> $filters
     * @param list<string> $additionalFields
     *
     * @return list<Filter>
     */
    public function group(array $filters, EntityDefinition $definition, array $additionalFields = []): array
    {
        $mapped = $this->recursion($filters, $definition, MultiFilter::CONNECTION_AND, false);

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

            foreach ($groups as $path => $filters) {
                $relevant = \in_array($path, $duplicates, true) || $negated;

                if (!$relevant) {
                    $new = array_merge($new, $filters);

                    continue;
                }

                $new[] = new JoinGroup($filters, $path, '_' . $level, $operator);
                ++$level;
            }
        }

        return $new;
    }

    /**
     * @param list<Filter> $filters
     *
     * @return array<string, mixed>
     */
    private function recursion(array $filters, EntityDefinition $definition, string $operator, bool $negated): array
    {
        $mapped = [];

        // for each nesting level we need an own group to keep the mathematical logic
        $prefix = Uuid::randomHex();

        foreach ($filters as $filter) {
            if ($filter instanceof MultiFilter) {
                $nested = $this->recursion($filter->getQueries(), $definition, $filter->getOperator(), $filter instanceof NotFilter || $negated);
                $mapped = array_merge_recursive($mapped, $nested);

                continue;
            }

            if (!$filter instanceof SingleFieldFilter) {
                // this case should never happen, because all core filters are an instead of SingleFieldFilter or MultiFilter
                $mapped[self::NOT_RELEVANT][] = $filter;

                continue;
            }

            // find the first to many association path
            $association = $this->findToManyPath($filter, $definition);
            if ($association === null) {
                // filters which not point to a to-many association are not relevant
                $mapped[self::NOT_RELEVANT][] = $filter;

                continue;
            }

            // checks if the current filter should check if the records has entries for the to many association
            if ($this->isEmptyFilter($filter)) {
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

    private function findToManyPath(SingleFieldFilter $filter, EntityDefinition $definition): ?string
    {
        $fields = EntityDefinitionQueryHelper::getFieldsOfAccessor($definition, $filter->getField(), false);

        if (\count($fields) === 0) {
            return null;
        }

        // contains later the path to the first to many association
        $path = [$definition->getEntityName()];

        $found = false;

        /** @var Field $field */
        foreach ($fields as $field) {
            if (!($field instanceof AssociationField)) {
                break;
            }

            // if to many not already detected, continue with path building
            $path[] = $field->getPropertyName();

            if ($field instanceof ManyToManyAssociationField || $field instanceof OneToManyAssociationField) {
                $found = true;
            }
        }
        $field = array_pop($fields);

        $filter->setIsPrimary($field->is(PrimaryKey::class));

        if ($found) {
            return implode('.', $path);
        }

        return null;
    }

    private function isEmptyFilter(SingleFieldFilter $filter): bool
    {
        if (!$filter instanceof EqualsFilter) {
            return false;
        }

        if (!$filter->isPrimary()) {
            return false;
        }

        return $filter->getValue() === null;
    }

    /**
     * @param array<string, mixed> $mapped
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

        $duplicates = array_filter($duplicates, fn (int $count) => $count > 1);

        return array_keys($duplicates);
    }
}
