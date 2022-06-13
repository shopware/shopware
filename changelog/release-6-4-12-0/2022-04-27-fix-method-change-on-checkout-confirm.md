---
title: Fix method changes on checkout confirm page
issue: NEXT-20216
author: Max Stegmeyer
author_email: m.stegmeyer@shopware.com
---
# Storefront
* Changed `frontend.checkout.confirm.page` to correctly reload, if the payment or shipping method has been automatically switched.
* Changed `form-auto-submit` plugin to only re-submit GET parameters if not specified in the form
