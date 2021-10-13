<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\LineItem\Group\RulesMatcher;

use Shopware\Core\Checkout\Cart\LineItem\Group\LineItemGroupDefinition;
use Shopware\Core\Checkout\Cart\LineItem\Group\LineItemGroupRuleMatcherInterface;
use Shopware\Core\Checkout\Cart\LineItem\LineItemFlatCollection;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class AnyRuleMatcher implements LineItemGroupRuleMatcherInterface
{
    private AbstractAnyRuleLineItemMatcher $anyRuleProvider;

    public function __construct(AbstractAnyRuleLineItemMatcher $anyRuleProvider)
    {
        $this->anyRuleProvider = $anyRuleProvider;
    }

    public function getMatchingItems(
        LineItemGroupDefinition $groupDefinition,
        LineItemFlatCollection $items,
        SalesChannelContext $context
    ): LineItemFlatCollection {
        $matchingItems = [];

        foreach ($items as $item) {
            if ($this->anyRuleProvider->isMatching($groupDefinition, $item, $context)) {
                $matchingItems[] = $item;
            }
        }

        return new LineItemFlatCollection($matchingItems);
    }
}
