---
title: Improve cart helper form accessibility
issue: 14796
author: Daniel Vien
author_email: d.vienphamtri@shopware.com
---
# Storefront
* Changed the cart product-number label to be visible and removed redundant ARIA attributes from the product-number and promotion-code inputs.
* Removed the required attribute from the optional product-number and promotion-code fields in the cart and offcanvas cart.
* Changed `CartLineItemController` to reject blank helper inputs with a validation message before product lookup or promotion processing, while preserving exact product numbers.
