---
title: Fix JS error in account overview
issue: NEXT-25391
---
# Storefront
* Added twig variable `addressEditorOptions` inside block `page_account_overview_billing_address_actions` in `Resources/views/storefront/page/account/address.html.twig` to prevent `[data-address-editor-options]` from being empty
