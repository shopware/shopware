---
title: Add a new tab in rule details to show rule assignments
issue: NEXT-12289
author: Tobias Berge
author_email: t.berge@shopware.com 
author_github: @tobiasberge
---
# Administration
* Changed `sw-settings-rule/index.js` module manifest and added new child routes:
    * `sw.settings.rule.detail.base`
    * `sw.settings.rule.detail.assignments`
* Changed `sw-settings-rule/acl/index.js` and added the following privileges for `viewer` role:
    * `shipping_method_price:read`
    * `product:read`
    * `product_price:read`
    * `promotion:read`
    * `promotion_discount:read`
    * `promotion_setgroup:read`
    * `event_action:read`
* Added new component `sw-settings-rule-detail-assignments`
* Added new computed prop `tabItems` to `sw-settings-rule-detail/index.js`
* Deprecated the following blocks in `sw-settings-rule-detail/sw-settings-rule-detail.html.twig`
    * `sw_settings_rule_detail_content_card`
    * `sw_settings_rule_detail_content_card_field_name`
    * `sw_settings_rule_detail_content_card_field_priority`
    * `sw_settings_rule_detail_content_card_field_description`
    * `sw_settings_rule_detail_content_card_field_type`
    * `sw_settings_rule_detail_conditions_card`
* Deprecated computed prop `availableModuleTypes` in `sw-settings-rule-detail/index.js`, will be moved to `sw-settings-rule-detail-base/index.js`
* Deprecated computed prop `moduleTypes` in `sw-settings-rule-detail/index.js`, will be moved to `sw-settings-rule-detail-base/index.js`
* Deprecated computed prop `mapPropertyErrors` in `sw-settings-rule-detail/index.js`, will be moved to `sw-settings-rule-detail-base/index.js`
* Added new prop `steps` to `sw-entity-listing` component to allow overriding the default pagination steps
* Changed method `createTreeRecursive` in `sw-condition-tree/index.js` and filter out already existing children
* Changed method `applyRootIfNecessary` in `sw-condition-tree/index.js` in order to always add the `rootContainer` to the `initialConditions` to ensure the `parentId` is always correct
