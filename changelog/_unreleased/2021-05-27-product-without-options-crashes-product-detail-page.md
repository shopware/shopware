---
title: Product without options crashes product detail page
issue: NEXT-15468
author: Jan-Marten de Boer
author_email: janmarten@elgentos.nl 
author_github: janmarten@elgentos.nl
---
# Core
* Changed method
  `\Shopware\Core\Content\Product\SalesChannel\Detail\ProductConfiguratorLoader::buildCurrentOptions`
  by short circuiting `$product->getOptionIds()` with `?? []` to support products
  without options. The method `$product->getOptionIds()` may return `null` which
  is an illegal value for the foreach it is used in.
