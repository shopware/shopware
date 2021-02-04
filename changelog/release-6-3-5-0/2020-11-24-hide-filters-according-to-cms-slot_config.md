---
title: hide filters according to CMS slot_config
issue: NEXT-11358
author: Markus Velt
author_email: m.velt@shopware.com 
author_github: @raknison
---
# Core
* Changed method `enrich` in `Core/Content/Product/Cms/ProductListingCmsElementResolver.php` to handle the filter
  settings for listings depending on `slot_config`
* Changed method `handleListingRequest`
  in `Core/Content/Product/SalesChannel/Listing/ProductListingFeaturesSubscriber.php` to handle filter settings given in
  the request
