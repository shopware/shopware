---
title: Country not show in order details
issue: NEXT-29628
author: Florian Keller
author_email: f.keller@shopware.com
author_github: Florian Keller
---
# Administration
* Changed `src/Administration/Resources/app/administration/src/module/sw-order/component/sw-order-address-selection/sw-order-address-selection.html.twig` and added translated country to select.
* Changed `src/Administration/Resources/app/administration/src/module/sw-order/component/sw-order-address-selection/index.js` to load the country with the address.
* Changed `src/Administration/Resources/app/administration/src/module/sw-order/component/sw-order-address-selection/index.js` to void display null, when zipcode is not set.

