---
title: Fix URL Punycode conversion
issue: NEXT-9348
author_github: @Dominik28111
---
# Administration
* Added new Vue filter `unicodeUri`. This filter converts the uri to unicode format without url encoding.
* Added `unicodeUri` fiter to `currentValue` in `sw-url-field.html.twig`.
* Added `{% block sw_sales_channel_detail_domains_column_url %}` in `sw-sales-channel-detail-domains.html.twig` to display unicode url in data grid.
* Changed `{% block sw_sales_channel_detail_domains_delete_modal_confirm_text %}` in `sw-sales-channel-detail-domains.html.twig` to display url in unicode format in deletion modal. 
* Changed computed `currentDomainModalTitle` in `sw-sales-channel-detail-domains/index.js` to return title with unicode url format.
* Changed method `validateCurrentValue` in `sw-url-field/index.js` to convert host to unicode format.
