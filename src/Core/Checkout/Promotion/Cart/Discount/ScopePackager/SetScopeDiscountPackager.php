<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Promotion\Cart\Discount\ScopePackager;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartException;
use Shopware\Core\Checkout\Cart\LineItem\Group\Exception\LineItemGroupPackagerNotFoundException;
use Shopware\Core\Checkout\Cart\LineItem\Group\Exception\LineItemGroupSorterNotFoundException;
use Shopware\Core\Checkout\Cart\LineItem\Group\LineItemGroupBuilder;
use Shopware\Core\Checkout\Cart\LineItem\Group\LineItemGroupDefinition;
use Shopware\Core\Checkout\Cart\LineItem\Group\LineItemQuantity;
use Shopware\Core\Checkout\Cart\LineItem\Group\LineItemQuantityCollection;
use Shopware\Core\Checkout\Promotion\Cart\Discount\DiscountLineItem;
use Shopware\Core\Checkout\Promotion\Cart\Discount\DiscountPackage;
use Shopware\Core\Checkout\Promotion\Cart\Discount\DiscountPackageCollection;
use Shopware\Core\Checkout\Promotion\Cart\Discount\DiscountPackager;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

#[Package('checkout')]
class SetScopeDiscountPackager extends DiscountPackager
{
    /**
     * @internal
     */
    public function __construct(private readonly LineItemGroupBuilder $groupBuilder)
    {
    }

    public function getDecorated(): DiscountPackager
    {
        throw new DecorationPatternException(self::class);
    }

    /**
     * Gets a list of all line items that are part of all groups in the complete set.
     * This will only return full sets. If a group is missing, then the
     * whole set is invalid.
     * In addition to this, a set can indeed occur multiple times. So the
     * result may come from multiple complete sets and their groups.
     *
     * @throws CartException
     * @throws LineItemGroupPackagerNotFoundException
     * @throws LineItemGroupSorterNotFoundException
     */
    public function getMatchingItems(DiscountLineItem $discount, Cart $cart, SalesChannelContext $context): DiscountPackageCollection
    {
        /** @var array<string, mixed> $groups */
        $groups = $discount->getPayloadValue('setGroups');

        $definitions = $this->buildGroupDefinitionList($groups);

        $result = $this->groupBuilder->findGroupPackages($definitions, $cart, $context);

        $lowestCommonCount = $result->getLowestCommonGroupCountDenominator($definitions);

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

        $splitUnits = [];

        foreach ($units as $group) {
            $singleItems = $this->splitQuantities($group->getMetaData()->getElements());

            $splitUnits[] = new DiscountPackage(new LineItemQuantityCollection($singleItems));
        }

        return new DiscountPackageCollection($splitUnits);
    }

    /**
     * Gets a list of in-memory grupo definitions
     * from the list of group settings from the payload
     *
     * @param array<string, mixed> $groups
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
     * @param LineItemQuantity[] $groupItems
     */
    private function splitQuantities(array $groupItems): LineItemQuantityCollection
    {
        $items = [];

        foreach ($groupItems as $item) {
            for ($i = 1; $i <= $item->getQuantity(); ++$i) {
                $items[] = new LineItemQuantity($item->getLineItemId(), 1);
            }
        }

        return new LineItemQuantityCollection($items);
    }
}
