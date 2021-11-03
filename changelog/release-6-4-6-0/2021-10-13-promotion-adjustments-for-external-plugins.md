---
title: Promotion adjustments for external plugins
issue: NEXT-17910
author_github: @Dominik28111
---
# Core
* Added class `Shopware\Core\Checkout\Cart\LineItem\Group\RulesMatcher\AnyRuleLineItemMatcher` to add the possibillity to extend the matching condition.
* Changed method `Shopware\Core\Checkout\Cart\LineItem\Group\RulesMatcher\AnyRuleMatcher::getMatchingItems()` to use `AnyRuleLineItemMatcher`.
* Changed method `Shopware\Core\Checkout\Cart\LineItem\Group\LineItemGroupBuilder::findGroupPackages()` to use `ProductLineItemProvider`.
* Changed method `Shopware\Core\Checkout\Cart\LineItem\Group\LineItemGroupBuilder::splitQuantities()` to set stackable state to true temporarily to avoid issues with external plugins.
* Added class `Shopware\Core\Checkout\Cart\LineItem\Group\ProductLineItemProvider` to add the possibility to extend the line item types used in promotions.
