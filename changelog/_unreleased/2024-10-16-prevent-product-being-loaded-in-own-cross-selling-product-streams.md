---
title: Prevent product being loaded in own cross selling product streams
issue: NEXT-00000
author: Elias Lackner
author_email: lackner.elias@gmail.com
author_github: @lacknere
---
# Core
* Added filter to product stream criteria in `ProductCrossSellingRoute::loadByStream` to exclude the current product in product stream results.
