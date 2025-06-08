---
title: Improve seo indexer performance
issue: NEXT-17325
author: Soner Sayakci
author_email: s.sayakci@shopware.com
---
# Core

* Changed `\Shopware\Core\Content\Seo\SeoUrlPersister` to not set `updated_at` to set urls while changing `is_deleted` 

___

# Storefront
* Deprecated `product.mainCategory` variable in seo url product template, use `product.categories.sortByPosition().first.translated.name` instead
  * All additional needed information will be joined automatically loaded when needed in the template
* Added `active` product filtering to `\Shopware\Storefront\Framework\Seo\SeoUrlRoute\ProductPageSeoUrlRoute`
