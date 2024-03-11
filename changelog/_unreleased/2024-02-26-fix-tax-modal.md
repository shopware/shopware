---
title: Fix tax modal
issue: NEXT-34020
---
# Storefront
* Changed the data-attribute selector of `.product-detail-tax-link` in `Resources/views/storefront/component/buy-widget/buy-widget.html.twig` to `data-ajax-modal="true"` to fix the modal opening error.
