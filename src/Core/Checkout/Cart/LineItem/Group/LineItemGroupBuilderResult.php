<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\LineItem\Group;

use Shopware\Core\Framework\Log\Package;

/**
 * @phpstan-type ResultArray array<string, array{groups: array<LineItemGroup>, total: array<LineItemQuantity>}>
 * @phpstan-type CountResultArray array<string, array{count: int}>
 */
#[Package('checkout')]
class LineItemGroupBuilderResult
{
    /**
     * @var ResultArray
     */
    private array $results = [];

    /**
     * added as additional requirement "on-top"
     * of the existing result list
     *
     * @var CountResultArray
     */
    private array $countResults = [];

    /**
     * Adds a new group to the provided group definition result.
     * If the items for a group do already exist in the result of the
     * particular group definition, only quantities will be increased.
     */
    public function addGroup(LineItemGroupDefinition $groupDefinition, LineItemGroup $group): void
    {
        $key = $groupDefinition->getId();

        // prepare root entry
        // if no data exists for this group
        if (!\array_key_exists($key, $this->results)) {
            $this->results[$key] = [
                'groups' => [],
                'total' => [],
            ];
        }

        // also increase our count of found items
        $this->addGroupCount($key);

        // add new group
        $this->addGroupEntry($key, $group);

        // add to total aggregation
        $this->addGroupAggregationTotal($key, $group);
    }

    /**
     * add the result of another group definition builder result
     */
    public function addGroupResult(string $groupDefinitionId, LineItemGroupBuilderResult $groupResult): void
    {
        $this->results[$groupDefinitionId] = $groupResult->results[$groupDefinitionId];
        $this->countResults[$groupDefinitionId] = $groupResult->countResults[$groupDefinitionId];
    }

    /**
     * Gets a list of all found line item quantity entries
     * for the provided group definition.
     *
     * @return LineItemQuantity[]
     */
    public function getGroupTotalResult(LineItemGroupDefinition $groupDefinition): array
    {
        $key = $groupDefinition->getId();

        if (!\array_key_exists($key, $this->results)) {
            return [];
        }

        return $this->results[$key]['total'];
    }

    /**
     * Gets a list of all found groups of the
     * provided group definition
     *
     * @return LineItemGroup[]
     */
    public function getGroupResult(LineItemGroupDefinition $groupDefinition): array
    {
        $key = $groupDefinition->getId();

        if (!\array_key_exists($key, $this->results)) {
            return [];
        }

        return $this->results[$key]['groups'];
    }

    /**
     * Gets a result instance of the provided group definition
     */
    public function getResult(string $groupDefinitionId): ?self
    {
        if (!\array_key_exists($groupDefinitionId, $this->results)) {
            return null;
        }

        $result = new self();

        $result->setResults([
            $groupDefinitionId => $this->results[$groupDefinitionId],
        ]);

        $result->setCountResults([
            $groupDefinitionId => $this->countResults[$groupDefinitionId],
        ]);

        return $result;
    }

    /**
     * Gets if line items have been found at all.
     */
    public function hasFoundItems(): bool
    {
        if (\count($this->results) <= 0) {
            return false;
        }

        foreach ($this->results as $groupResult) {
            if ((is_countable($groupResult['total']) ? \count($groupResult['total']) : 0) > 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * Gets the count of found groups for the
     * provided group definition.
     */
    public function getGroupCount(LineItemGroupDefinition $groupDefinition): int
    {
        $key = $groupDefinition->getId();

        if (\array_key_exists($key, $this->countResults)) {
            return $this->countResults[$key]['count'];
        }

        return 0;
    }

    /**
     * Gets the lowest common denominator of possible groups.
     * This means, we compare how often each group of the set
     * has been found, and search the maximum count of complete sets.
     * 2 GROUPS of A and 1 GROUP of B would mean a count of 1 for
     * the whole set combination of A and B.
     *
     * @param LineItemGroupDefinition[] $definitions
     */
    public function getLowestCommonGroupCountDenominator(array $definitions): int
    {
        $lowestCommonCount = null;

        foreach ($definitions as $definition) {
            $count = $this->getGroupCount($definition);

            if ($lowestCommonCount === null) {
                $lowestCommonCount = $count;
            }

            if ($count < $lowestCommonCount) {
                $lowestCommonCount = $count;
            }
        }

        return $lowestCommonCount ?? 0;
    }

    private function addGroupCount(string $key): void
    {
        // also increase our count of found items
        if (!\array_key_exists($key, $this->countResults)) {
            $this->countResults[$key] = ['count' => 1];
        } else {
            ++$this->countResults[$key]['count'];
        }
    }

    private function addGroupEntry(string $key, LineItemGroup $group): void
    {
        $this->results[$key]['groups'][] = $group;
    }

    private function addGroupAggregationTotal(string $key, LineItemGroup $group): void
    {
        $total = $this->results[$key]['total'];

        foreach ($group->getItems() as $tuple) {
            $lineItemId = $tuple->getLineItemId();

            // either create new entries
            // or just increase the quantity of an existing entry in
            // the result set of our group definition.
            if (!\array_key_exists($lineItemId, $total)) {
                $total[$lineItemId] = new LineItemQuantity($lineItemId, $tuple->getQuantity());
            } else {
                $package = $total[$lineItemId];
                $package->setQuantity($package->getQuantity() + $tuple->getQuantity());
            }
        }

        $this->results[$key]['total'] = $total;
    }

    /**
     * @param ResultArray $results
     */
    private function setResults(array $results): void
    {
        $this->results = $results;
    }

    /**
     * @param CountResultArray $countResults
     */
    private function setCountResults(array $countResults): void
    {
        $this->countResults = $countResults;
    }
}
