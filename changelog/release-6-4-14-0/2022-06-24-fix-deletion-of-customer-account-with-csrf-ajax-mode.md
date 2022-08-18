---
title: Fix the deletion of customer account with csrf mode set to ajax
issue: NEXT-22123
author_github: Melzmann
author_email: melzer@cworx-media.de
---
# Storefront
* Added `data-form-csrf-handler="true"` to the form of `frontend.account.profile.delete` action in `page/account/profile/index.html.twig`