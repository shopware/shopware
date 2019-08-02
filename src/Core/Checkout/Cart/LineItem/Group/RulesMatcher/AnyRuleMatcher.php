<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\LineItem\Group\RulesMatcher;

use Shopware\Core\Checkout\Cart\LineItem\Group\LineItemGroupDefinition;
use Shopware\Core\Checkout\Cart\LineItem\Group\LineItemGroupRuleMatcherInterface;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\Checkout\Cart\Rule\LineItemScope;
use Shopware\Core\Content\Rule\RuleEntity;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class AnyRuleMatcher implements LineItemGroupRuleMatcherInterface
{
    /**
     * @throws \Shopware\Core\Checkout\Cart\Exception\InvalidQuantityException
     * @throws \Shopware\Core\Checkout\Cart\Exception\LineItemNotStackableException
     * @throws \Shopware\Core\Checkout\Cart\Exception\MixedLineItemTypeException
     */
    public function getMatchingItems(LineItemGroupDefinition $groupDefinition, LineItemCollection $items, SalesChannelContext $context): LineItemCollection
    {
        $matchingItems = new LineItemCollection();

        /** @var LineItem $item */
        foreach ($items as $item) {
            if ($this->isAnyRuleMatching($groupDefinition, $item, $context)) {
                $matchingItems->add($item);
            }
        }

        return $matchingItems;
    }

    /**
     * Gets if the provided line item is allowed for any of the applied
     * rules within the group entity.
     */
    private function isAnyRuleMatching(LineItemGroupDefinition $groupDefinition, LineItem $item, SalesChannelContext $context): bool
    {
        // no rules mean OK
        if ($groupDefinition->getRules()->count() <= 0) {
            return true;
        }

        // if we have rules, make sure
        // they are connected using an OR condition
        $scope = new LineItemScope($item, $context);

        /** @var RuleEntity $rule */
        foreach ($groupDefinition->getRules() as $rule) {
            /** @var Rule $rootCondition */
            $rootCondition = $rule->getPayload();

            // if any rule matches, return OK
            if ($rootCondition->match($scope)) {
                return true;
            }
        }

        return false;
    }
}
