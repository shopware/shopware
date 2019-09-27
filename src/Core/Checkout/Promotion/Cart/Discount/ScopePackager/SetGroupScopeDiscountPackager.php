<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Promotion\Cart\Discount\ScopePackager;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\LineItem\Group\LineItemGroup;
use Shopware\Core\Checkout\Cart\LineItem\Group\LineItemGroupBuilder;
use Shopware\Core\Checkout\Cart\LineItem\Group\LineItemGroupBuilderResult;
use Shopware\Core\Checkout\Cart\LineItem\Group\LineItemGroupDefinition;
use Shopware\Core\Checkout\Cart\LineItem\Group\LineItemQuantityCollection;
use Shopware\Core\Checkout\Promotion\Cart\Discount\DiscountLineItem;
use Shopware\Core\Checkout\Promotion\Cart\Discount\DiscountPackage;
use Shopware\Core\Checkout\Promotion\Cart\Discount\DiscountPackageCollection;
use Shopware\Core\Checkout\Promotion\Cart\Discount\DiscountPackagerInterface;
use Shopware\Core\Checkout\Promotion\Exception\SetGroupNotFoundException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class SetGroupScopeDiscountPackager implements DiscountPackagerInterface
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
     * Gets a list of all line items that are part of the group definition.
     * This will only return full groups. If a item is missing, then the whole group is invalid.
     * In addition to this, a group can indeed occur multiple times.
     * So the result may come from multiple complete groups.
     *
     * @throws SetGroupNotFoundException
     * @throws \Shopware\Core\Checkout\Cart\Exception\InvalidQuantityException
     * @throws \Shopware\Core\Checkout\Cart\Exception\LineItemNotStackableException
     * @throws \Shopware\Core\Checkout\Cart\Exception\MixedLineItemTypeException
     * @throws \Shopware\Core\Checkout\Cart\LineItem\Group\Exception\LineItemGroupPackagerNotFoundException
     * @throws \Shopware\Core\Checkout\Cart\LineItem\Group\Exception\LineItemGroupSorterNotFoundException
     */
    public function getMatchingItems(DiscountLineItem $discount, Cart $cart, SalesChannelContext $context): DiscountPackageCollection
    {
        /** @var array $groups */
        $groups = $discount->getPayload()['setGroups'];

        /** @var string $groupId */
        $groupId = $discount->getPayload()['groupId'];

        /** @var LineItemGroupDefinition $definition */
        $definition = $this->getGroupDefinition($groupId, $groups);

        /** @var LineItemGroupBuilderResult $result */
        $result = $this->groupBuilder->findGroupPackages([$definition], $cart, $context);

        $units = [];

        /** @var LineItemGroup $group */
        foreach ($result->getGroupResult($definition) as $group) {
            $units[] = new DiscountPackage(new LineItemQuantityCollection($group->getItems()));
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

        /** @var array $group */
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
}
