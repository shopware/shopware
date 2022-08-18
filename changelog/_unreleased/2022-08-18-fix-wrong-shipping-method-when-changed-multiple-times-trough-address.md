---
title: Fix wrong shipping method when changed multiple times trough address
issue: NEXT-22790
author: Michel Bade
author_email: m.bade@shopware.com
---
# Storefront
* Changed `redirectParameters` to be zero in `src/Storefront/Resources/views/storefront/component/shipping/shipping-form.html.twig` to avoid submitting an order with invalid shipping method
* Removed line to clear cart errors in `src/Storefront/Controller/CheckoutController.php` before rendering the checkout confirm page
* Changed `redirectParameters` to be zero in `src/Storefront/Resources/app/storefront/src/plugin/address-editor/address-editor.plugin.js` on reload when address modal is closed to prevent unwanted behaviour
