---
title: Do not yield an error if the `.finish-ordernumber` element cannot be found
issue: NEXT-38781
author: Max
author_email: max@swk-web.com
author_github: @aragon999
---
# Storefront
* Changed the Google Analytics `purchase.event` handler to not run into an error if the `.finish-ordernumber` element is missing and instead log a warning
