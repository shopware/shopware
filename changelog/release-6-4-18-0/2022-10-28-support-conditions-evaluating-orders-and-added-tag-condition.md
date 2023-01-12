---
title: Support conditions evaluating orders and added tag condition
issue: NEXT-20720
author: d.neustadt
author_email: d.neustadt@shopware.com
author_github: dneustadt
---
# Core
* Added `FlowRuleScope` that also includes the `OrderEntity` to be evaluated in conditions
* Added the abstract `FlowRule` which condition classes may extend when they should be used exclusively in the context of a flow
* Added the service `FlowRuleScopeBuilder` which will build a `FlowRuleScope` from an `OrderEntity`
* Changed `FlowExecutor` to to use the `FlowRuleScopeBuilder` and evaluate the order on order aware events
* Changed `CachedRuleLoader` and `RuleLoader` to cache and retrieve either rules excluding flow builder specific rules or only rules used in flows
* Added `OrderTagRule` for use in the context of flows
