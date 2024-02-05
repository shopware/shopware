---
title: Unify sw-form-field-renderer-events
issue: NEXT-31166
author: Sebastian Seggewiss
author_email: s.seggewiss@shopware.com
author_github: @seggewiss
---
# Administration
* Changed `sw-form-field-renderer` events. All automatically mapped components now use the prop `value` and the event `update:value`.
___
# Upgrade Information
## sw-entity-multi-id-select
* Change model `ids` to `value`.
* Change event `update:ids` to `update:value`
## sw-price-field
* Change model `price` to `value`
* Change event `update:price` to `update:value`
