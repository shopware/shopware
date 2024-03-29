---
title: countryId error handling
issue: NEXT-31660
author: Florian Keller
author_email: f.keller@shopware.com
author_github: Florian Keller
---
# Core
* Changed `src/Core/Checkout/Customer/SalesChannel/RegisterRoute.php` to handle missing countryId
___
# Storefront
* Changed `src/Storefront/Resources/views/storefront/component/address/address-form.html.twig` to display countryId error
