---
title: Track orders on finish page using the order number as transaction id
issue: NEXT-12660
author: Max
author_email: max@swk-web.com
author_github: @aragon999
---
# Storefront
* Changed the Google Analytics order tracking to only track the order on the finish page instead when submitting the confirm form, such that the orders are only tracked once they are really submitted
* Changed the transaction ID of the tracked order to be the order number instead of a random ID preventing tracking of the same order multiple times
