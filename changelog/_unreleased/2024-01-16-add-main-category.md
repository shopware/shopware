---
title: Add main category
issue: NEXT-31342
author: oskroblin Skroblin
author_email: o.skroblin@shopware.com
---
# Core
* Added `mainCategories` association, with current sales channel id filter, in `\Shopware\Storefront\Framework\Seo\SeoUrlRoute\ProductPageSeoUrlRoute::prepareCriteria`

___
# Upgrade Information
## Main categories are now available in seo url templates
We added the `mainCategories` association in the `\Shopware\Storefront\Framework\Seo\SeoUrlRoute\ProductPageSeoUrlRoute::prepareCriteria` method. 
This association is filtered by the current sales channel id. You can now use the main categories in your seo url templates for product detail pages. 

```
{{ product.mainCategories.first.category.translated.name }}
```
