---
title: Fix save product with long description caused indexing error
issue: NEXT-17704
---
# Core
* Changed method `\Shopware\Core\Content\Product\SearchKeyword\ProductSearchKeywordAnalyzer::analyze` to split long text field by parts of 500 characters to prevent generating a huge keyword which caused database write error
