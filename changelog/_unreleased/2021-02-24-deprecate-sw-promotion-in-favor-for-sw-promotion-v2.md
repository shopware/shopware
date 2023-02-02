---
title: Deprecate sw-promotion in favor for sw-promotion-v2
issue: NEXT-12669
flag: FEATURE_NEXT_13810
author: Stephan Pohl
author_email: s.pohl@shopware.com 
author_github: klarstil
---

# Administration
*  Deprecated module `sw-promotion` in favor for `sw-promotion-v2` which can only be accessed using feature flag `FEATURE_NEXT_13810`. The deprecation will be removed in v6.5.0.0.
    * The following components got deprecated:
        * `sw-promotion-basic-form`
        * `sw-promotion-cart-condition-form`
        * `sw-promotion-code-form`
        * `sw-promotion-discount-component`
        * `sw-promotion-individualcodes`
        * `sw-promotion-order-condition-form`
        * `sw-promotion-persona-form`
        * `sw-promotion-rule-select`
        * `sw-promotion-sales-channel-select`
        * `sw-promotion-detail`
        * `sw-promotion-list`
        * `sw-promotion-detail-base`
        * `sw-promotion-detail-discounts`
        * `sw-promotion-detail-restrictions`
    * The following services and helpers got deprecated:
        * `sw-promotion/acl/index.js`
        * `code-entity-hydrator.helper.js`
        * `promotion-entity-hydrator.helper.js`
        * `promotion.helper.js`