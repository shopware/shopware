---
title: Proper redirection of non logged-in customers when accessing checkout routes
issue: NEXT-0000
author: Max
author_email: max@swk-web.com
author_github: @aragon999
---
# Storefront
* Changed the routes `frontend.checkout.confirm.page`, `frontend.checkout.finish.page` and `frontend.checkout.finish.order` to redirect non logged-in customers to the login/registration page and afterwards back to the previous page
