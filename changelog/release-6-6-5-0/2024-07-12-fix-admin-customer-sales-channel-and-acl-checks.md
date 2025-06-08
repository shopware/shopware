---
title: Fix admin customer sales channel & acl checks
issue: NEXT-37237
author: Benjamin Wittwer
author_email: benjamin.wittwer@a-k-f.de
author_github: akf-bw
---
# Administration
* Changed `sw-customer/acl/index` by moving `sales_channel_domain:read` from `editor` to `viewer` to prevent missing permission messages
* Changed `sw-customer/component/sw-customer-card/index` to inject `contextStoreService` correctly, use `State` instead of `Store` and fix `api_proxy_imitate-customer` acl check
* Changed `sw-customer/component/sw-customer-card/sw-customer-card.html.twig` to fix acl check
* Changed `sw-customer/page/sw-customer-detail/index` by moving `boundSalesChannel.domains` to default criteria to prevent missing template data
* Changed `sw-customer/page/sw-customer-list/index` to only load the `boundSalesChannel` instead of `salesChannel` to only load needed data
* Changed `sw-customer/page/sw-customer-list/sw-customer-list.html.twig` to correctly access `boundSalesChannel` instead of `salesChannel`
