---
title: Fixing bug table product_keyword_dictionary gets bigger and bigger
issue: NEXT-16890
---
# Core
* Added `\Shopware\Core\Content\Product\Cleanup\CleanupProductKeywordDictionaryTaskHandler`, which delete all keywords no longer used in table `product_keyword_dictionary`
