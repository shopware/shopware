---
title: Added skip option to dal:refresh:index
issue: NEXT-15739
author: d.neustadt
author_email: d.neustadt@shopware.com 
author_github: dneustadt
---
# Core
* Added `skip` option to command `dal:refresh:index` which can be provided with a comma separated list of indexer and/or updater names to be skipped when indexing.
  
  List of skipable indexers and corresponding updaters:
  
  * `category.indexer`
      * `category.child-count`
      * `category.tree`
      * `category.breadcrumb`
      * `category.seo-url`
  * `customer.indexer`
      * `customer.many-to-many-id-field`
  * `landing_page.indexer`
      * `landing_page.many-to-many-id-field`
      * `landing_page.seo-url`
  * `media.indexer`
  * `media_folder.indexer`
      * `media_folder.child-count`
  * `media_folder_configuration.indexer`
  * `payment_method.indexer`
  * `product.indexer`
      * `product.inheritance`
      * `product.stock`
      * `product.variant-listing`
      * `product.child-count`
      * `product.many-to-many-id-field`
      * `product.category-denormalizer`
      * `product.cheapest-price`
      * `product.rating-averaget`
      * `product.stream`
      * `product.search-keyword`
      * `product.seo-url`
  * `product_stream.indexer`
  * `product_stream_mapping.indexer`
  * `promotion.indexer`
      * `promotion.exclusion`
      * `promotion.redemption`
  * `rule.indexer`
      * `rule.payload`
  * `sales_channel.indexer`
      * `sales_channel.many-to-many`
    
