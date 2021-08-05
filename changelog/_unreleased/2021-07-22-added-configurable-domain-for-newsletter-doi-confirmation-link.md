---
title: Added configurable domain for newsletter doi confirmation link
issue: NEXT-16200
flag: FEATURE_NEXT_14001


---
# Core
* Changed `Core/Checkout/Customer/SalesChannel/NewsletterSubscribeRoute.php` to use configured domain instead of storefrontUrl
* Added system config `core.newsletter.doubleOptInDomain` in `Core/System/Resources/config/newsletter.xml`
