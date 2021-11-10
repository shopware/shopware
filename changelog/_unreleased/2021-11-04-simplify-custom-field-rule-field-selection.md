---
title: Simplify custom field rule field selection
issue: NEXT-18219
flag: FEATURE_NEXT_16800
author: d.neustadt
author_email: d.neustadt@shopware.com 
author_github: dneustadt
---
# Administration
* Added slot `result-description-property` for additional description respectively in `sw-entity-single-select` component
* Added content for result list description slot in `sw-condition-line-item-custom-field` and `sw-condition-customer-custom-field` components
* Changed `sw-condition-line-item-custom-field` and `sw-condition-customer-custom-field` to no longer require selection of custom field set first
* Deprecated blocks `sw_condition_customer_custom_field_fieldset` and `sw_condition_line_item_custom_field_fieldset`
* Deprecated computed property `customFieldSetCriteria` and method `onFieldSetChange` in both components `sw-condition-line-item-custom-field` and `sw-condition-customer-custom-field`
