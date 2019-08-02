<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Promotion\Cart\Discount\ScopePackager;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\LineItem\Group\LineItemGroup;
use Shopware\Core\Checkout\Cart\LineItem\Group\LineItemGroupBuilder;
use Shopware\Core\Checkout\Cart\LineItem\Group\LineItemGroupBuilderResult;
use Shopware\Core\Checkout\Cart\LineItem\Group\LineItemGroupDefinition;
use Shopware\Core\Checkout\Cart\LineItem\Group\LineItemQuantity;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Promotion\Cart\Discount\DiscountPackagerInterface;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class SetScopeDiscountPackager implements DiscountPackagerInterface
{
    /**
     * @var LineItemGroupBuilder
     */
    private $groupBuilder;

    public function __construct(LineItemGroupBuilder $groupBuilder)
    {
        $this->groupBuilder = $groupBuilder;
    }

    /**
     * Gets a list of all line items that are part of all groups in the complete set.
     * This will only return full sets. If a group is missing, then the
     * whole set is invalid.
     * In addition to this, a set can indeed occur multiple times. So the
     * result may come from multiple complete sets and their groups.
     *
     * @throws \Shopware\Core\Checkout\Cart\Exception\InvalidQuantityException
     * @throws \Shopware\Core\Checkout\Cart\Exception\LineItemNotStackableException
     * @throws \Shopware\Core\Checkout\Cart\Exception\MixedLineItemTypeException
     * @throws \Shopware\Core\Checkout\Cart\LineItem\Group\Exception\LineItemGroupPackagerNotFoundException
     * @throws \Shopware\Core\Checkout\Cart\LineItem\Group\Exception\LineItemGroupSorterNotFoundException
     */
    public function getMatchingItems(LineItem $discount, Cart $cart, SalesChannelContext $context): array
    {
        /** @var array $groups */
        $groups = $discount->getPayload()['setGroups'];

        /** @var array $definitions */
        $definitions = $this->buildGroupDefinitionList($groups);

        /** @var LineItemGroupBuilderResult $result */
        $result = $this->groupBuilder->findGroupPackages($definitions, $cart, $context);

        /** @var int $lowestCommonCount */
        $lowestCommonCount = $this->getLowestCommonGroupCountDenominator($definitions, $result);

        // if no max possible groups that have
        // the same count have been found, then return no items
        if ($lowestCommonCount <= 0) {
            return [];
        }

        $allItemTuples = [];

        // now run through all definitions
        // and check if our set and count is valid
        /** @var LineItemGroupDefinition $definition */
        foreach ($definitions as $definition) {
            /** @var LineItemGroup[] $groupResult */
            $groupResult = $result->getGroupResult($definition);

            // now only add the number of groups that
            // are available for all group definitions
            for ($i = 0; $i < $lowestCommonCount; ++$i) {
                /** @var LineItemQuantity[] $itemsInGroup */
                $itemsInGroup = $groupResult[$i]->getItems();
                // add to our aggregated list
                $allItemTuples = array_merge($itemsInGroup, $allItemTuples);
            }
        }

        // if we have found multiple sets
        // make sure to aggregate all results for a single discount package
        return $this->buildAggregatedResult($allItemTuples);
    }

    /**
     * Gets a list of in-memory grupo definitions
     * from the list of group settings from the payload
     */
    private function buildGroupDefinitionList(array $groups): array
    {
        $definitions = [];
        /** @var array $group */
        foreach ($groups as $group) {
            $definitions[] = new LineItemGroupDefinition(
                $group['groupId'],
                $group['packagerKey'],
                $group['value'],
                $group['sorterKey'],
                $group['rules']
            );
        }

        return $definitions;
    }

    /**
     * Gets the lowest common denominator of possible groups.
     * This means, we compare how often each group of the set
     * has been found, and search the maximum count of complete sets.
     * 2 GROUPS of A and 1 GROUP of B would mean a count of 1 for
     * the whole set combination of A and B.
     */
    private function getLowestCommonGroupCountDenominator(array $definitions, LineItemGroupBuilderResult $result): int
    {
        $lowestCommonCount = null;

        /** @var LineItemGroupDefinition $definition */
        foreach ($definitions as $definition) {
            /** @var int $count */
            $count = $result->getGroupCount($definition);

            if ($lowestCommonCount === null) {
                $lowestCommonCount = $count;
            }

            if ($count < $lowestCommonCount) {
                $lowestCommonCount = $count;
            }
        }

        return $lowestCommonCount ?? 0;
    }

    /**
     * Runs through all found line items and builds an aggregated
     * result by having each line item only once, but with
     * adjusted quantities.
     *
     * @param LineItemQuantity[] $allItemTuples
     */
    private function buildAggregatedResult(array $allItemTuples): array
    {
        $aggregatedList = [];

        /** @var LineItemQuantity $tuple */
        foreach ($allItemTuples as $tuple) {
            /** @var LineItemQuantity|null $found */
            $found = null;

            /** @var LineItemQuantity $existing */
            foreach ($aggregatedList as $existing) {
                if ($tuple->getLineItemId() === $existing->getLineItemId()) {
                    $found = $existing;
                    break;
                }
            }

            if ($found !== null) {
                $found->setQuantity($found->getQuantity() + $tuple->getQuantity());
            } else {
                $aggregatedList[] = $tuple;
            }
        }

        return $aggregatedList;
    }
}
