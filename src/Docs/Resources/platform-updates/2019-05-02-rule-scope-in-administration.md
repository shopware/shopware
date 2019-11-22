[titleEn]: <>(Rule scope in administration)

The rule scope of rules are now supported and required to define in the administration.

The scopes filter the matching rules so it's possible to show only `cart` or `lineItem` based rules.

The scopes can be added in the in the condition type data provider. See `platform/src/Administration/Resources/app/administration/src/app/decorator/condition-type-data-provider.js`

At the moment, these types are supported
- `global` --> used for rules which has no restriction (like `DateRangeRule`)
- `cart` --> used for rules which require the CartRuleScope (like `CartAmountRule`)
- `checkout` --> used for rules which require the CheckoutRuleScope (like `LastNameRule`)
- `lineItem` --> used for rules which require the LineItemScope (like `LineItemTagRule`)