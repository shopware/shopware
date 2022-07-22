---
title: Google Product Feed - set shipping fee to 0.00 if this product.shippingFree checkbox is set.
issue: NEXT-22049
author: wolf128058
author_email: jonas.hess@mailbox.org
author_github: wolf128058
---
# Storefront
* Changed `src/Administration/Resources/app/administration/src/module/sw-sales-channel/product-export-templates/google-product-search-de/body.xml.twig`
  * If the free-shipping-checkbox for this product is set, the shipping fees are set to 0.00 EUR (or whatever currency.isoCode you use), too.
