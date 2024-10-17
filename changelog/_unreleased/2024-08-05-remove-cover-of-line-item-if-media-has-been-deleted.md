---
title: Remove cover of line item if media has been deleted
issue: NEXT-0000
author: Max
author_email: max@swk-web.com
author_github: @aragon999
---
# Core
* Changed `ProductCartProcessor` to remove the cover of a product if the media has been deleted, which would otherwise result in an error if the customer tries to complete the order
