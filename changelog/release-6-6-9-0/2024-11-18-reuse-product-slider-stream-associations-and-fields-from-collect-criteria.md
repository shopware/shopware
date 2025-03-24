---
title: Reuse product slider stream associations and fields from collect criteria
issue: NEXT-39483
author: Elias Lackner
author_email: lackner.elias@gmail.com
author_github: @lacknere
---
# Core
* Changed `ProductSliderCmsElementResolver` to reuse associations and fields from the criteria object created in `collect` method when fetching final products from product stream in `fetchProductsByIds`.
