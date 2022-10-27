---
title: Fix toggling automatic promotion in Order UX overrides intermediate changes
issue: NEXT-23672
author: Michel Bade
author_email: m.bade@shopware.com
author_github: @cyl3x
---
# Administration
* Added validation to promotion field and switch in `sw-order-promotion-field` to prevent using them with intermediate changes in order and throw a notification
