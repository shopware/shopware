---
title: Form validation errors ignored by captcha
issue: NEXT-34914
author: Max Stegmeyer
author_email: m.stegmeyer@shopware.com
---
# Storefront
* Changed `NotFoundSubscriber` to remove Captcha annotation for ErrorController calls to prevent duplicate, failing captcha validation.
* Added `ErrorRedirectRequestEvent` to allow modifying the request before redirecting to the error page.
