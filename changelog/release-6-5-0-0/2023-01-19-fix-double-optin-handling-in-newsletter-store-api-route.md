---
title: Fix double optin handling in newsletter store-api route
issue: NEXT-22891
author: Michel Bade
author_email: m.bade@shopware.com
author_github: @cyl3x
---
# API
* Changed option selection behavior in `src/Core/Content/Newsletter/SalesChannel/NewsletterSubscribeRoute.php` to remove client side decision on newsletter double opt-in
