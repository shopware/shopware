# Context rules
The context rule system allows to customized or validated different data sources, depending on the context, customer or application.
The corresponding sources are located in `src/Context/Rule`. It is possible to cover complex conditions with simple object orientation sources.
The rules can access the current storefront context data and the current customer's cart to be validated. 
An entire simple rule might look like this:

```php
<?php declare(strict_types=1);

use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Rule\RuleScope;

class TrueRule extends Rule
{
    public function match(
        RuleScope $ruleScope
    ): bool {
        return true;
    }
}
```

This rule will always return TRUE, which means that this rule always applies. 
Unlike the above rule, there are also rule containers which allow multiple rules to be nested within each other:

```php
<?php

$nested = new AndRule([
    new TrueRule(),
    new OrRule([
        new TrueRule(),
        new FalseRule(),
    ])
]);
```

The idea behind these rules is to replace static assignment, such as "which customer group is allowed to see a product and which not", with this more flexible system.
For example, products can be whitelisted or blacklisted with this rules, prices are defined per rule, shipping methods are blocked for specified rules.
The shop owner can create the rules in the administration itself. The data ends up in the database table context_rule.
When the storefront is called, all rules are read out and validated with the current context and shopping cart to find out which ones are valid for the current state.

***Important performance hint!***

**However, this also means that it is fundamentally that the rules are based on simple PHP operations and never cause a database or HTTP access**.

Assuming the following scenario exists:
- The shop owner has defined 100 context rules for his shop
- In each of these rules, the database is accessed once

As a result, the database is accessed 100 times each time storefront page is requested.

All matching rules of a request state are stored in the `\Shopware\Core\System\SalesChannel\CheckoutContext` and the `\Shopware\Core\SalesChannel\Context\Struct\ShopContext`.
