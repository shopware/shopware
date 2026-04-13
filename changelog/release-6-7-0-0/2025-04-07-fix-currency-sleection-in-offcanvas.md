---
title: Fix missing currency text in offcanvas currency selection
issue: #8190
---
# Storefront
* Removed the `d-none` and `d-md-inline` CSS class from the currency button text in `storefront/layout/header/actions/currency-widget.html.twig` so the current selected currency is always displayed.
* Changed the currency symbol reference, so the currency symbol is displayed correctly.
