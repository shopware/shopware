---
title: Fix captcha validation to respect errorRoute parameter
issue: 12859
author: Björn Meyer
author_email: b.meyer@shopware.com
author_github: @BrocksiNet
---
# Storefront
* Changed `ErrorController::onCaptchaFailure` to respect the `errorRoute` parameter when forwarding after captcha validation failure, instead of always using the current route
