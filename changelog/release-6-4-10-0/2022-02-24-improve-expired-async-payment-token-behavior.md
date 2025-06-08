---
title: Improve expired async payment token behavior
issue: NEXT-20227
author: Max Stegmeyer
author_email: m.stegmeyer@shopware.com
---
# Core
* Changed return type in payment finalization if payment token is expired
___
# Storefront
* Added alert for expired payment token in `account/order/index.html.twig`
