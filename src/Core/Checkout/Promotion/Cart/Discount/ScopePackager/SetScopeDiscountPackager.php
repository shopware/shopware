<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Promotion\Cart\Discount\ScopePackager;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\Exception\InvalidQuantityException;
use Shopware\Core\Checkout\Cart\Exception\LineItemNotStackableException;
use Shopware\Core\Checkout\Cart\Exception\MixedLineItemTypeException;
use Shopware\Core\Checkout\Cart\LineItem\Group\Exception\LineItemGroupPackagerNotFoundException;
use Shopware\Core\Checkout\Cart\LineItem\Group\Exception\LineItemGroupSorterNotFoundException;
use Shopware\Core\Checkout\Cart\LineItem\Group\LineItemGroupBuilder;
use Shopware\Core\Checkout\Cart\LineItem\Group\LineItemGroupBuilderResult;
use Shopware\Core\Checkout\Cart\LineItem\Group\LineItemGroupDefinition;
use Shopware\Core\Checkout\Cart\LineItem\Group\LineItemQuantityCollection;
use Shopware\Core\Checkout\Promotion\Cart\Discount\DiscountLineItem;
use Shopware\Core\Checkout\Promotion\Cart\Discount\DiscountPackage;
use Shopware\Core\Checkout\Promotion\Cart\Discount\DiscountPackageCollection;
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

    public function getResultContext(): string
    {
        return DiscountPackagerInterface::RESULT_CONTEXT_PACKAGE;
    }

    /**
     * Gets a list of all line items that are part of all groups in the complete set.
     * This will only return full sets. If a group is missing, then the
     * whole set is invalid.
     * In addition to this, a set can indeed occur multiple times. So the
     * result may come from multiple complete sets and their groups.
     *
     * @throws InvalidQuantityException
     * @throws LineItemNotStackableException
     * @throws MixedLineItemTypeException
     * @throws LineItemGroupPackagerNotFoundException
     * @throws LineItemGroupSorterNotFoundException
     */
    public function getMatchingItems(DiscountLineItem $discount, Cart $cart, SalesChannelContext $context): DiscountPackageCollection
    {
        /** @var array $groups */
        $groups = $discount->getPayloadValue('setGroups');

        $definitions = $this->buildGroupDefinitionList($groups);

        $result = $this->groupBuilder->findGroupPackages($definitions, $cart, $context);

        $lowestCommonCount = $this->getLowestCommonGroupCountDenominator($definitions, $result);

        // if no max possible groups that have
        // the same count have been found, then return no items
        if ($lowestCommonCount <= 0) {
            return new DiscountPackageCollection();
        }

        $units = [];

        for ($i = 0; $i < $lowestCommonCount; ++$i) {
            $itemsInSet = [];

            // now run through all definitions
            // and check if our set and count is valid
            foreach ($definitions as $definition) {
                $groupResult = $result->getGroupResult($definition);

                $itemsInGroup = $groupResult[$i]->getItems();

                $itemsInSet = array_merge($itemsInSet, $itemsInGroup);
            }

            $units[] = new DiscountPackage(new LineItemQuantityCollection($itemsInSet));
        }

        return new DiscountPackageCollection($units);
    }

    /**
     * Gets a list of in-memory grupo definitions
     * from the list of group settings from the payload
     *
     * @return LineItemGroupDefinition[]
     */
    private function buildGroupDefinitionList(array $groups): array
    {
        $definitions = [];
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
     *
     * @param LineItemGroupDefinition[] $definitions
     */
    private function getLowestCommonGroupCountDenominator(array $definitions, LineItemGroupBuilderResult $result): int
    {
        $lowestCommonCount = null;

        foreach ($definitions as $definition) {
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
}
