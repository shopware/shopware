---
title: Rule condition field abstraction
date: 2022-05-23
area: services-settings
tags: [rule, abstraction, administration]
---

## Context
Conditions for the Rule Builder consist of a `shopware.rule.definition` tagged service and a corresponding Vue component. Many of these Vue components follow a common scheme, so it is possible to use an abstracted single component for all of these.

## Decision
We want to reduce the number of rule condition components in the administration and use a single abstracted component instead. This would also decrease the number of necessary steps when introducing a new rule condition and would require writing less JavaScript.

Conditions will still be able to register and use their own custom components, as it used to be, in cases where the needed functionality is beyond the capabilities of the generic abstracted component.

To make use of the generic abstracted component, rule conditions, whose component may be abstracted, may implement a new method `getConfig` and return an instance of `RuleConfig`. Via `RuleConfig` the appropriate set of operators and a number of corresponding fields can be defined:

```php
public function getConfig(): RuleConfig
{
    return (new RuleConfig())
        ->operatorSet(RuleConfig::OPERATOR_SET_STRING, false, true)
        ->entitySelectField('customerGroupIds', CustomerGroupDefinition::ENTITY_NAME, true)
        ->selectField('customSelect', ['foo', 'bar', 'baz'])
        ->numberField('amount', ['unit' => RuleConfig::UNIT_DIMENSION])
        ->booleanField('active')
        ->dateTimeField('creationDate');
}
```

Within the administration, the configurations for the different types of conditions are being requested and stored. The new generic condition component then makes use of the configurations to render the various fields.

## Consequences

Starting from now, newly introduced rule conditions will make use of the `Rule::getConfig()` implementation whenever possible and hence no longer require a new Vue component. If the new condition cannot be abstracted, as it may need special functionality within the administration, it may still introduce its own custom component.

The original components of conditions are being deprecated and marked to be removed by the next major release.

If you used or extended any of these components, use/extend `sw-condition-generic` or `sw-condition-generic-line-item` instead and refer to `this.condition.type` to introduce changes for a specific type of condition.
