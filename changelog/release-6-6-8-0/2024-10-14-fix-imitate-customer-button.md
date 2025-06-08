---
title: Fix imitate customer button
issue: NEXT-38948
author: Benjamin Wittwer
author_email: benjamin.wittwer@a-k-f.de
author_github: akf-bw
---
# Administration
* Changed `sw-customer/component/sw-customer-card/index.js` to check if the current customer is a guest
* Changed `sw-customer/component/sw-customer-card/sw-customer-card.html.twig` to check if the current customer is a guest
* Added `tooltipImitateCustomerGuest` snippet to `sw-customer/snippet/de-DE.json`
* Added `tooltipImitateCustomerGuest` snippet to `sw-customer/snippet/en-GB.json`
