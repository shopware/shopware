---
title: Update info texts in price rounding
issue: NEXT-15058
author: Daniel Meyer
author_email: d.meyer@shopware.com   
author_github: GitEvil
---
# Administration
* Deprecated twig block `sw_settings_price_rounding_subline_description` use `sw_settings_price_rounding_header_warning` instead
* Added twig block `sw_settings_price_rounding_header_info`
* Added computed properties `showHeaderInfo` and `showHeaderWarning` in `Resources/app/administration/src/module/sw-settings-currency/component/sw-settings-price-rounding/index.js`
* Removed unused snippet key `sw-settings-currency.price-rounding.infoDescription`
