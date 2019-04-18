[titleEn]: <>(Rules)

[Back to modules](./../10-modules.md)

Rules are used throughout the platform to provide dynamic decision management. For instance shipping and billing methods are matched to customers, carts and line items based on rules from these resources.

![Rules](./dist/erd-shopware-core-content-rule.png)


### Table `rule`

A rule is the collection of a complex set of conditions, that can be used to influence multiple workflows of the order process.


### Table `rule_condition`

Each row is related to a rule and represents a single part of the query the rule needs for validation.


[Back to modules](./../10-modules.md)
