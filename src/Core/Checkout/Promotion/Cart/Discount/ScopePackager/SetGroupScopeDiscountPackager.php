<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Promotion\Cart\Discount\ScopePackager;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\Exception\InvalidQuantityException;
use Shopware\Core\Checkout\Cart\Exception\LineItemNotStackableException;
use Shopware\Core\Checkout\Cart\Exception\MixedLineItemTypeException;
use Shopware\Core\Checkout\Cart\LineItem\Group\Exception\LineItemGroupPackagerNotFoundException;
use Shopware\Core\Checkout\Cart\LineItem\Group\Exception\LineItemGroupSorterNotFoundException;
use Shopware\Core\Checkout\Cart\LineItem\Group\LineItemGroupBuilder;
use Shopware\Core\Checkout\Cart\LineItem\Group\LineItemGroupDefinition;
use Shopware\Core\Checkout\Cart\LineItem\Group\LineItemQuantityCollection;
use Shopware\Core\Checkout\Promotion\Cart\Discount\DiscountLineItem;
use Shopware\Core\Checkout\Promotion\Cart\Discount\DiscountPackage;
use Shopware\Core\Checkout\Promotion\Cart\Discount\DiscountPackageCollection;
use Shopware\Core\Checkout\Promotion\Cart\Discount\DiscountPackager;
use Shopware\Core\Checkout\Promotion\Exception\SetGroupNotFoundException;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class SetGroupScopeDiscountPackager extends DiscountPackager
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
        return self::RESULT_CONTEXT_PACKAGE;
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
     *
     * @throws SetGroupNotFoundException
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

        /** @var string $groupId */
        $groupId = $discount->getPayloadValue('groupId');

        $definition = $this->getGroupDefinition($groupId, $groups);

        $result = $this->groupBuilder->findGroupPackages([$definition], $cart, $context);

        $units = [];

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
