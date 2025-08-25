---
title: Added missing rule filter for shipping price matrix
issue: https://github.com/shopware/shopware/issues/7228
author_github: @En0Ma1259
---
# Administration
* Added missing `rule-filter` option for `sw-select-rule-create` in `src/Administration/Resources/app/administration/src/module/sw-settings-shipping/component/sw-settings-shipping-price-matrix/sw-settings-shipping-price-matrix.html.twig`
* Deprecated `shippingRuleFilterCriteria` method for `v6.8.0.0` in `src/Administration/Resources/app/administration/src/module/sw-settings-shipping/component/sw-settings-shipping-price-matrix/sw-settings-shipping-price-matrix.html.twig`. Type of rules for the matrix should be "price" instead of "shipping"
