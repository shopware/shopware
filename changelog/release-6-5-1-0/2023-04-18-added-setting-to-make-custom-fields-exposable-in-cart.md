---
title: Added setting to make custom fields exposable in cart
issue: NEXT-26169
author: d.neustadt
author_email: d.neustadt@shopware.com
author_github: dneustadt
---
# Core
* Added field `allowCartExpose` in `CustomFieldDefinition`
* Added `allow-cart-expose` in custom fields schema of app manifest
* Changed `CartSerializationCleaner` to fetch exposable custom fields by `allowCartExpose`
___
# Administration
* Added field for `allowCartExpose` in `sw-custom-field-detail`
* Changed `sw-condition-line-item-custom-field` to disallow selection of custom fields not exposed in carts
