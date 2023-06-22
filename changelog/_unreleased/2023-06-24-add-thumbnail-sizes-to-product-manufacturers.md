---
title: add thumbnail sizes to product manufacturers
issue: NEXT-0000
author: tinect
author_email: s.koenig@tinect.de
author_github: tinect
---

# Core

* Added three thumbnail sizes for media folder "Product Manufacturer Media": 200px, 360px and 1920px
* Added one more thumbnail size for media folder "Product Media" to fit the listing in default theme: 280px

___
# Storefront

* Added sizes attribute with 200px for thumbnail of manufacturer within block `element_manufacturer_logo_image` in `element/cms-element-manufacturer-logo.html.twig` 
* Added thumbnail usage within block `page_product_detail_manufacturer_logo` in `page/product-detail/headline.html.twig`

___
# Upgrade Information

* Update your thumbnails by running command: `media:generate-thumbnails`
