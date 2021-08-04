---
title: Fixed country selection if only one country is available
issue: NEXT-16315
author: Lennart Tinkloh
author_email: l.tinkloh@shopware.com 
author_github: @lernhart
---
# Administration
*  Added `countryCriteria` in `src/module/sw-customer/component/sw-customer-address-form/index.js` to sort countries by position.
___
# Storefront
*  Changed `Resources/views/storefront/component/address/address-form.html.twig` to preselect country, whenever only one country is available in the sales channel.
