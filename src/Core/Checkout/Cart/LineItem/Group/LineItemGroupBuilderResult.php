<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\LineItem\Group;

class LineItemGroupBuilderResult
{
    /**
     * @var array
     */
    private $results = [];

    /**
     * added as additional requirement "on-top"
     * of the existing result list
     *
     * @var array
     */
    private $countResults = [];

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
        if (!array_key_exists($key, $this->results)) {
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
     * Gets a list of all found line item quantity entries
     * for the provided group definition.
     *
     * @return LineItemQuantity[]
     */
    public function getGroupTotalResult(LineItemGroupDefinition $groupDefinition): array
    {
        $key = $groupDefinition->getId();

        if (!array_key_exists($key, $this->results)) {
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

        if (!array_key_exists($key, $this->results)) {
            return [];
        }

        return $this->results[$key]['groups'];
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
            if (\count($groupResult['total']) > 0) {
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

        if (array_key_exists($key, $this->countResults)) {
            return $this->countResults[$key]['count'];
        }

        return 0;
    }

    private function addGroupCount(string $key): void
    {
        // also increase our count of found items
        if (!array_key_exists($key, $this->countResults)) {
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
        /** @var array $total */
        $total = $this->results[$key]['total'];

        foreach ($group->getItems() as $tuple) {
            // either create new entries
            // or just increase the quantity of an existing entry in
            // the result set of our group definition.
            if (!array_key_exists($tuple->getLineItemId(), $total)) {
                // add as new entry to avoid pointer references
                // to our single groups list
                $total[$tuple->getLineItemId()] = new LineItemQuantity(
                    $tuple->getLineItemId(),
                    $tuple->getQuantity()
                );
            } else {
                $package = $total[$tuple->getLineItemId()];
                $package->setQuantity($package->getQuantity() + $tuple->getQuantity());
            }
        }

        $this->results[$key]['total'] = $total;
    }
}
