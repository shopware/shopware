---
title: Implement rule awareness
issue: NEXT-18215
author: Timo Altholtmann
---
# Administration
* Added slot `result-group` to `src/app/component/form/select/base/sw-grouped-single-select/sw-grouped-single-select.html.twig`
* Changed `src/app/component/form/select/entity/sw-entity-many-to-many-select/index.js` `searchCriteria` to also consider the `associations` of the property `criteria`
* Added property `associationEntity` to `src/app/component/rule/sw-condition-tree/index.js`
* Added computed function `restrictedConditions` which is also provided for child components to `src/app/component/rule/sw-condition-tree/index.js`
* Added function `getRestrictedAssociations` to the `/src/app/service/rule-condition.service.js`
* Added function `getRestrictionsByAssociation` to the `/src/app/service/rule-condition.service.js`
* Added function `getTranslatedConditionViolationList` to the `/src/app/service/rule-condition.service.js`
* Added property `ruleAwareGroupKey` to `/src/module/sw-promotion-v2/component/sw-promotion-v2-rule-select/index.js`
* Added property `detailPageLoading` and `conditions` to `/src/module/sw-settings-rule/view/sw-settings-rule-detail-assignments/index.js`
