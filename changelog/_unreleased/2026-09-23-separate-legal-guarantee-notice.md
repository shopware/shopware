---
title: Separate the legal guarantee notice from terms and conditions
issue: 20360
---
# Storefront
* Added the `checkout.confirmLegalGuaranteeNotice` snippet and the `page_checkout_confirm_legal_guarantee_paragraph` block to show the legal guarantee notice separately from the terms checkbox when `core.cart.showLegalGuaranteeNotice` is enabled.
* Changed the checkout terms checkbox to use `checkout.confirmTermsTextModal` or `checkout.confirmTermsText`, depending on `ACCESSIBILITY_TWEAKS`. The existing combined guarantee snippets remain available for theme overrides.
* Changed the German `general.privacyNoticeTextModal` snippet to identify the terms and conditions link as AGB.
