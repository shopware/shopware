---
title: Refactor rule awareness
issue: NEXT-19245
flag: FEATURE_NEXT_18215
author: Timo Altholtmann

---
# Administration
* Added property `ruleAwareGroupKey` to `src/app/component/form/sw-select-rule-create/index.js`
* Added function `getRestrictedRuleTooltipConfig` to `rule-condition.service.js`
* Added function `isRuleRestricted` to `rule-condition.service.js`
