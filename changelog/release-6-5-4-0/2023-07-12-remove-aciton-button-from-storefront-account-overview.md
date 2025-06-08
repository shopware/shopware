---
title: Remove aciton button from storefront account overview
issue: NEXT-29218
author: Marcel Brode
author_email: m.brode@shopware.com
author_github: Marcel Brode
---
# Storefront
* Removed all action buttons from the account overview page in div with class `card-actions` from:
  * `src/Storefront/Resources/views/storefront/page/account/index.html.twig`
  * `src/Storefront/Resources/views/storefront/page/account/address.html.twig`
* Deprecated blocks associated with account overview actions:
  * `page_account_overview_billing_address_actions_link`
  * `page_account_overview_shipping_address_actions_link`
