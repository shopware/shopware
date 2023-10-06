---
title: Limit amount of search keywords used for search
issue: NEXT-16546
---
# Core
* Changed `\Shopware\Core\Content\Product\SearchKeyword\ProductSearchTermInterpreter` to limit the number of search keywords to the best matching 8 keywords, to prevent exploding SQL queries.
