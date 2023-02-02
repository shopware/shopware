<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Promotion\Cart\Discount\ScopePackager;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\LineItem\Group\LineItemGroupBuilder;
use Shopware\Core\Checkout\Cart\LineItem\Group\LineItemGroupDefinition;
use Shopware\Core\Checkout\Cart\LineItem\Group\LineItemQuantity;
use Shopware\Core\Checkout\Cart\LineItem\Group\LineItemQuantityCollection;
use Shopware\Core\Checkout\Promotion\Cart\Discount\DiscountLineItem;
use Shopware\Core\Checkout\Promotion\Cart\Discount\DiscountPackage;
use Shopware\Core\Checkout\Promotion\Cart\Discount\DiscountPackageCollection;
use Shopware\Core\Checkout\Promotion\Cart\Discount\DiscountPackager;
use Shopware\Core\Checkout\Promotion\Exception\SetGroupNotFoundException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

#[Package('checkout')]
class SetGroupScopeDiscountPackager extends DiscountPackager
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
     * Gets a list of all line items that are part of the group definition.
     * This will only return full groups. If a item is missing, then the whole group is invalid.
     * In addition to this, a group can indeed occur multiple times.
     * So the result may come from multiple complete groups.
     */
    public function getMatchingItems(DiscountLineItem $discount, Cart $cart, SalesChannelContext $context): DiscountPackageCollection
    {
        /** @var array $groups */
        $groups = $discount->getPayloadValue('setGroups');

        $groupDefinitions = $this->buildGroupDefinitionList($groups);

        $resultGroups = $this->groupBuilder->findGroupPackages($groupDefinitions, $cart, $context);

        $maximumSetCount = $resultGroups->getLowestCommonGroupCountDenominator($groupDefinitions);

        if ($maximumSetCount <= 0) {
            return new DiscountPackageCollection();
        }

        /** @var string $groupId */
        $groupId = $discount->getPayloadValue('groupId');

        $definition = $this->getGroupDefinition($groupId, $groups);

        $result = $this->groupBuilder->findGroupPackages([$definition], $cart, $context);

        $units = [];

        $addedGroupCount = 0;
        foreach ($result->getGroupResult($definition) as $group) {
            $singleItems = $this->splitQuantities($group->getItems());

            $units[] = new DiscountPackage(new LineItemQuantityCollection($singleItems));

            ++$addedGroupCount;

            if ($addedGroupCount === $maximumSetCount) {
                break;
            }
        }

        return new DiscountPackageCollection($units);
    }

    /**
     * Gets the group definition for the provided groupId
     * within the list of available set groups from the payload.
     *
     * @throws SetGroupNotFoundException
     */
    private function getGroupDefinition(string $groupId, array $groups): LineItemGroupDefinition
    {
        $index = 1;

        foreach ($groups as $group) {
            if ((string) $index === $groupId) {
                return new LineItemGroupDefinition(
                    $group['groupId'],
                    $group['packagerKey'],
                    $group['value'],
                    $group['sorterKey'],
                    $group['rules']
                );
            }

            ++$index;
        }

        throw new SetGroupNotFoundException($groupId);
    }

    /**
     * @param LineItemQuantity[] $groupItems
     */
    private function splitQuantities(array $groupItems): LineItemQuantityCollection
    {
        $items = [];

        foreach ($groupItems as $item) {
            $cloneItem = new LineItemQuantity($item->getLineItemId(), 1);
            for ($i = 1; $i <= $item->getQuantity(); ++$i) {
                $items[] = clone $cloneItem;
            }
        }

        return new LineItemQuantityCollection($items);
    }

    /**
     * Gets a list of in-memory group definitions
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
}
