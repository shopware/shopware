---
title: Changed wishlist cookie from a session cookie to a cookie valid for 30 days
issue: NEXT-38509
author: Max
author_email: max@swk-web.com
author_github: @aragon999
---
# Storefront
* Changed the `wishlist-enabled` cookie from a session cookie to a cookie which is valid for 30 days, as otherwise the user would need to accept that cookie again, after he closed the browser
