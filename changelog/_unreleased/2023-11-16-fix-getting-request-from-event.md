---
title: fix-getting-request-from-event
issue: NEXT-26134
author: Alexandru Dumea
author_email: a.dumea@shopware.com
author_github: Alexandru Dumea
---
# Core
* Added a PHPStan rule, NoNewRequestInStorefrontRule.php  to discourage using new Request() in storefront controllers.
___
# Storefront
* Changed the way the request is defined in storefront controllers. Instead of instantiating new Request objects, we now consistently utilize the current request from the request stack. This change ensures that the request context is accurately preserved throughout the application flow.
* Added exceptions for the scenarios where a request is not available from the request stack.
