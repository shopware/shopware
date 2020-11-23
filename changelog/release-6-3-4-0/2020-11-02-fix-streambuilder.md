---
title: Fix stream builder
issue: NEXT-10946
author: Oliver Skroblin
author_email: o.skroblin@shopware.com 
author_github: Oliver Skroblin
---
# Core
* Changed `\Shopware\Core\Content\ProductStream\Service\ProductStreamBuilder`, the class uses now the generated `product_stream.api_filter` column to build the filters
* Changed `\Shopware\Core\Content\ProductStream\DataAbstractionLayer\ProductStreamIndexer`, the class now considers the `position` field to generate the `api_filter` value
* Added generic `\Shopware\Core\Framework\DataAbstractionLayer\Exception\EntityNotFoundException`
