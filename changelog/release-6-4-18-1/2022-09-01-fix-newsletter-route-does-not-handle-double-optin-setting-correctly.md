---
title: Fix newsletter route does not handle double optin setting correctly
issue: NEXT-22891
author: Michel Bade
author_email: m.bade@shopware.com
---
# API
* Changed option selection behavior in `src/Core/Content/Newsletter/SalesChannel/NewsletterSubscribeRoute.php` to remove client side decision on newsletter double opt-in
___
# Storefront
* Added missing snippets for newsletter double opt-in in `src/Storefront/Controller/NewsletterController.php`
* Changed client side decision on newsletter double opt-in in `src/Storefront/Controller/NewsletterController.php`
